<?php

namespace App\Actions;

use App\Models\Channel;
use App\Services\Excel\ExcelDecryptor;
use App\Services\Excel\ExcelReader;
use App\Services\Excel\ExcelValidator;
use App\Services\Excel\MappingEngine;
use App\Services\Products\ProductMatcher;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsDate;
use Carbon\Carbon;


class ProcessChannelExcel
{
    public function __construct(
        private ExcelDecryptor $decryptor,
        private ExcelReader $reader,
        private MappingEngine $engine,
        private ProductMatcher $matcher,
        private ExcelValidator $validator,
    ) {}

    private function normalizeExcelDateTime($value): ?string
    {
        if ($value === null) return null;

        $s = trim((string)$value);
        if ($s === '') return null;

        // 1) 농협몰 같은 숫자 문자열 먼저 처리 (숫자여도 직렬값 아님!)
        if (preg_match('/^\d{14}$/', $s)) {
            $dt = Carbon::createFromFormat('YmdHis', $s);
            return $dt ? $dt->format('Y-m-d H:i:s') : null;
        }
        if (preg_match('/^\d{12}$/', $s)) { // YYYYMMDDhhmm
            $dt = Carbon::createFromFormat('YmdHi', $s);
            return $dt ? $dt->format('Y-m-d H:i:s') : null; // 초는 00
        }
        if (preg_match('/^\d{8}$/', $s)) { // YYYYMMDD
            $dt = Carbon::createFromFormat('Ymd', $s);
            return $dt ? $dt->startOfDay()->format('Y-m-d H:i:s') : null;
        }

        // 2) 엑셀 직렬값 (유효 범위: 0 ~ 2,958,465 = 9999-12-31)
        if (is_numeric($s)) {
            $n = (float)$s;
            if ($n >= 0 && $n <= 2958465) {
                try {
                    $dt = XlsDate::excelToDateTimeObject($n); // 타임존 변환 X
                    return Carbon::instance($dt)->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {}
            }
        }

        $candidates = [
            'Y-m-d H:i:s','Y-m-d H:i',
            'Y/m/d H:i:s','Y/m/d H:i',
            'Y.m.d H:i:s','Y.m.d H:i',
            'Y-m-d\TH:i:s','Y-m-d\TH:i',
            'Y-m-d','Y/m/d','Y.m.d',
            'n/j/Y g:i:s A','n/j/Y g:i A','Y-m-d h:i:s A', // 10:37:49 AM 같은 포맷
        ];
        foreach ($candidates as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $s); // ✅ tz 지정/변환 없음
                if ($dt !== false) return $dt->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {}
        }

        try {
            return Carbon::parse($s)->format('Y-m-d H:i:s'); // 느슨 해석
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function stripSenderNote(?string $addr): ?string
    {
        if ($addr === null) return null;

        $addr = preg_replace(
            '/\s*[.\-]*\s*[\(\[\{（]\s*발\s*송\s*인\s*[:：]\s*[^)\]\}）]+[\)\]\}）]\s*$/u',
            '',
            $addr
        );
        // 중복 공백 정리
        $addr = preg_replace('/\s{2,}/u', ' ', $addr);
        return trim($addr);
    }

    /**
     * @return array{preview:array<int,array<string,mixed>>, rows:array<int,array<string,mixed>>}
     * @throws \RuntimeException on validation failure
     */
    public function handle(Channel $channel, string $filePath, ?string $password = null): array
    {
        // 1) open (decrypt if needed)
        $xlsxPath = $filePath;
        if ($channel->is_excel_encrypted) {
            $dec = $this->decryptor->decrypt($filePath, (string)$password, (int)$channel->excel_data_start_row);
            $xlsxPath = $dec['xlsx'];
        }

        // 2) sheet-level validation (labels)
        $reader = IOFactory::createReaderForFile($xlsxPath);
        $reader->setReadDataOnly(true);
        $spread = $reader->load($xlsxPath);
        $sheet = $spread->getSheet(0);

        $valid = $this->validator->validateSheet($sheet, $channel);
        $spread->disconnectWorksheets(); unset($spread);

        if (!$valid['ok']) {
            $msg = "엑셀 양식 검증 실패";
            throw new \RuntimeException($msg . ' ('.json_encode($valid['errors'], JSON_UNESCAPED_UNICODE).')');
        }

        // 3) row data (A/B/C... map) from start row
        $rows = $this->reader->readAsColMap($xlsxPath, (int)$channel->excel_data_start_row);

        // 4) apply field mappings → standard record
        $maps = $channel->excelFieldMappings()->orderBy('id')->get();

        $out = [];
        foreach ($rows as $r) {
            $item = ['channel_code' => $channel->code];

            foreach ($maps as $m) {
                $item[$m->field_key] = $this->engine->apply($r, $m);
            }

            if (array_key_exists('ordered_at', $item)) {
                $item['ordered_at'] = $this->normalizeExcelDateTime($item['ordered_at']);
            }

            // 주소 꼬리표 "(발송인: XXX)" 제거
            if (!empty($item['receiver_addr1'])) {
                $item['receiver_addr1'] = $this->stripSenderNote($item['receiver_addr1']);
            }
            if (!empty($item['receiver_addr2'])) {
                $item['receiver_addr2'] = $this->stripSenderNote($item['receiver_addr2']);
            }
            if (!empty($item['receiver_addr_full'])) {
                $item['receiver_addr_full'] = $this->stripSenderNote($item['receiver_addr_full']);
            }

            // 보정: 구매자 정보 없으면 수취인 복사
            if (empty($item['buyer_name']) && !empty($item['receiver_name'])) {
                $item['buyer_name'] = $item['receiver_name'];
            }
            if (empty($item['buyer_phone']) && !empty($item['receiver_phone'])) {
                $item['buyer_phone'] = $item['receiver_phone'];
            }
            if (empty($item['buyer_postcode']) && !empty($item['receiver_postcode'])) {
                $item['buyer_postcode'] = $item['receiver_postcode'];
            }
            if (empty($item['buyer_addr_full']) && !empty($item['receiver_addr_full'])) {
                $item['buyer_addr_full'] = $item['receiver_addr_full'];
            }
            if (empty($item['buyer_addr1']) && !empty($item['receiver_addr1'])) {
                $item['buyer_addr1'] = $item['receiver_addr1'];
            }
            if (empty($item['buyer_addr2']) && !empty($item['receiver_addr2'])) {
                $item['buyer_addr2'] = $item['receiver_addr2'];
            }

            // 상품 매칭 (옵션 있으면 제품+옵션 둘 다, 없으면 제품만)
            $pid = $this->matcher->resolveProductId(
                $channel->id,
                $item['product_title'] ?? null,
                $item['option_title'] ?? null
            );

            // 수량/송장 정규화
            $qty = (int)($item['quantity'] ?? 1);
            $qty = $qty > 0 ? $qty : 1;
            $trk = trim((string)($item['tracking_no'] ?? ''));

            // orders 스키마에 맞춰 미리보기 키를 일부 표준화
            $item['_product_id']  = $pid;
            $item['_quantity']    = $qty;
            $item['_tracking_no'] = $trk;

            $out[] = $item;
        }

        $limit = (int)config('ofintranet.preview_rows', 20);
        return [
            'rows'    => $out,
            'preview' => array_slice($out, 0, $limit),
        ];
    }
}

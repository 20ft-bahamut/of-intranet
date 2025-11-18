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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
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

        // 1) 숫자날짜 문자열
        if (preg_match('/^\d{14}$/', $s)) { // YYYYMMDDhhmmss
            $dt = Carbon::createFromFormat('YmdHis', $s);
            return $dt ? $dt->format('Y-m-d H:i:s') : null;
        }
        if (preg_match('/^\d{12}$/', $s)) { // YYYYMMDDhhmm
            $dt = Carbon::createFromFormat('YmdHi', $s);
            return $dt ? $dt->format('Y-m-d H:i:s') : null;
        }
        if (preg_match('/^\d{8}$/', $s)) { // YYYYMMDD
            $dt = Carbon::createFromFormat('Ymd', $s);
            return $dt ? $dt->startOfDay()->format('Y-m-d H:i:s') : null;
        }

        // 2) 엑셀 직렬값
        if (is_numeric($s)) {
            $n = (float)$s;
            if ($n >= 0 && $n <= 2958465) {
                try {
                    $dt = XlsDate::excelToDateTimeObject($n);
                    return Carbon::instance($dt)->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {}
            }
        }

        // 3) 포맷 후보
        $candidates = [
            'Y-m-d H:i:s','Y-m-d H:i',
            'Y/m/d H:i:s','Y/m/d H:i',
            'Y.m.d H:i:s','Y.m.d H:i',
            'Y-m-d\TH:i:s','Y-m-d\TH:i',
            'Y-m-d','Y/m/d','Y.m.d',
            'n/j/Y g:i:s A','n/j/Y g:i A','Y-m-d h:i:s A',
        ];
        foreach ($candidates as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $s);
                if ($dt !== false) return $dt->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {}
        }

        // 4) 느슨 파서
        try {
            return Carbon::parse($s)->format('Y-m-d H:i:s');
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
        return trim(preg_replace('/\s{2,}/u', ' ', $addr));
    }

    /**
     * 엑셀을 읽어 표준 레코드로 정규화하고,
     * 각 행의 "원본 셀맵"을 raw_payload로 함께 반환한다.
     *
     * @return array{
     *   preview: array<int,array<string,mixed>>,
     *   rows: array<int,array<string,mixed>>,
     *   meta?: array<string,mixed>
     * }
     */
    public function handle(Channel $channel, string $filePath, ?string $password = null): array
    {
        // 1) 복호/열기
        $xlsxPath = $filePath;
        if ($channel->is_excel_encrypted) {
            $dec = $this->decryptor->decrypt($filePath, (string)$password, (int)$channel->excel_data_start_row);
            $xlsxPath = $dec['xlsx'];
        }

        // 2) 시트 검증
        $reader = IOFactory::createReaderForFile($xlsxPath);
        $reader->setReadDataOnly(true);
        $spread = $reader->load($xlsxPath);
        $sheet  = $spread->getSheet(0);
        $sheetTitle = $sheet->getTitle();

        $valid = $this->validator->validateSheet($sheet, $channel);
        // 리소스 해제
        $spread->disconnectWorksheets();
        unset($spread);

        if (!$valid['ok']) {
            $msg = "엑셀 양식 검증 실패";
            throw new \RuntimeException($msg . ' (' . json_encode($valid['errors'], JSON_UNESCAPED_UNICODE) . ')');
        }

        // 3) 시작행부터 "원본 셀맵(A,B,C…→값)" 읽기
        $startRow = (int) $channel->excel_data_start_row;
        $colMapRows = $this->reader->readAsColMap($xlsxPath, $startRow); // array<int, array{A:...,B:...}>

        // 4) 필드 매핑 적용 → 표준 레코드 구성
        $maps = $channel->excelFieldMappings()->orderBy('id')->get();

        $out = [];
        // readAsColMap가 몇 열까지 읽었는지 모를 수 있으므로, 최대열은 첫 행 기준으로 추정
        $highestColIndex = 0;
        if (!empty($colMapRows)) {
            $first = $colMapRows[0];
            foreach (array_keys($first) as $colLetter) {
                try {
                    $idx = Coordinate::columnIndexFromString($colLetter);
                    if ($idx > $highestColIndex) $highestColIndex = $idx;
                } catch (\Throwable $e) {}
            }
        }

        foreach ($colMapRows as $i => $r) {
            // 4-1) 원본 보존
            $originalCells = $r; // 안전하게 복사
            $rawPayloadJson = json_encode($originalCells, JSON_UNESCAPED_UNICODE);
            $rowNumber = $startRow + $i;

            // 4-2) 매핑/정규화
            $item = ['channel_code' => $channel->code];
            foreach ($maps as $m) {
                $item[$m->field_key] = $this->engine->apply($r, $m);
            }

            // 공백/빈문자 정규화
            foreach (['channel_order_no','product_title','option_title','tracking_no'] as $k) {
                if (array_key_exists($k, $item) && is_string($item[$k])) {
                    $item[$k] = trim($item[$k]);
                    if ($item[$k] === '') $item[$k] = null;
                }
            }
            // 수량 보정
            if (isset($item['quantity'])) {
                $item['quantity'] = (int) $item['quantity'];
                if ($item['quantity'] <= 0) $item['quantity'] = 1;
            }

            // 날짜 정규화
            if (array_key_exists('ordered_at', $item)) {
                $item['ordered_at'] = $this->normalizeExcelDateTime($item['ordered_at']);
            }

            // 주소 꼬리표 제거
            if (!empty($item['receiver_addr1']))      $item['receiver_addr1']      = $this->stripSenderNote($item['receiver_addr1']);
            if (!empty($item['receiver_addr2']))      $item['receiver_addr2']      = $this->stripSenderNote($item['receiver_addr2']);
            if (!empty($item['receiver_addr_full']))  $item['receiver_addr_full']  = $this->stripSenderNote($item['receiver_addr_full']);

            // 구매자 보정
            if (empty($item['buyer_name'])      && !empty($item['receiver_name']))      $item['buyer_name']      = $item['receiver_name'];
            if (empty($item['buyer_phone'])     && !empty($item['receiver_phone']))     $item['buyer_phone']     = $item['receiver_phone'];
            if (empty($item['buyer_postcode'])  && !empty($item['receiver_postcode']))  $item['buyer_postcode']  = $item['receiver_postcode'];
            if (empty($item['buyer_addr_full']) && !empty($item['receiver_addr_full'])) $item['buyer_addr_full'] = $item['receiver_addr_full'];
            if (empty($item['buyer_addr1'])     && !empty($item['receiver_addr1']))     $item['buyer_addr1']     = $item['receiver_addr1'];
            if (empty($item['buyer_addr2'])     && !empty($item['receiver_addr2']))     $item['buyer_addr2']     = $item['receiver_addr2'];

            // 상품 매칭 (❗️여기서 매칭 실패 시 ProductMatcher가 후보 자동 등록)
            $pid = $this->matcher->resolveProductId(
                $channel->id,
                $item['product_title'] ?? null,
                $item['option_title']  ?? null
            );

            // 수량/송장 정규화
            $qty = (int)($item['quantity'] ?? 1);
            $qty = $qty > 0 ? $qty : 1;
            $trk = $item['tracking_no'] ?? null;
            if (is_string($trk)) { $trk = trim($trk); if ($trk === '') $trk = null; }

            // 미리보기/후속 파이프라인용 헬퍼 키
            $item['_product_id']  = $pid;
            $item['_quantity']    = $qty;
            $item['_tracking_no'] = $trk;

            // ✅ 커밋 필수 3종: 원본/메타/해시 (정규화 정보 넣지 말 것)
            $item['_cells']      = $originalCells;
            $item['_row']        = $rowNumber;
            $item['_sheet']      = $sheetTitle;
            $item['raw_payload'] = $originalCells;
            $item['raw_meta']    = [
                'sheet'        => $sheetTitle,
                'row'          => $rowNumber,
                'channel_code' => $channel->code,
            ];
            $item['raw_hash']    = hash('sha256', $rawPayloadJson);

            $out[] = $item;
        }

        $limit = (int) config('ofintranet.preview_rows', 20);
        return [
            'rows'    => $out,
            'preview' => array_slice($out, 0, $limit),
            'meta'    => [
                'sheet'         => $sheetTitle,
                'start_row'     => $startRow,
                'highest_col'   => $highestColIndex ? Coordinate::stringFromColumnIndex($highestColIndex) : null,
                'channel_code'  => $channel->code,
            ],
        ];
    }
}

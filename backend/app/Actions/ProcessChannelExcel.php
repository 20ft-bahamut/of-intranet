<?php

namespace App\Actions;

use App\Models\Channel;
use App\Services\Excel\ExcelDecryptor;
use App\Services\Excel\ExcelReader;
use App\Services\Excel\ExcelValidator;
use App\Services\Excel\MappingEngine;
use App\Services\Products\ProductMatcher;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProcessChannelExcel
{
    public function __construct(
        private ExcelDecryptor $decryptor,
        private ExcelReader $reader,
        private MappingEngine $engine,
        private ProductMatcher $matcher,
        private ExcelValidator $validator,
    ) {}

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

            // 보정: 구매자 정보 없으면 수취인 복사
            if (empty($item['buyer_name']) && !empty($item['receiver_name'])) {
                $item['buyer_name'] = $item['receiver_name'];
            }
            if (empty($item['buyer_phone']) && !empty($item['receiver_phone'])) {
                $item['buyer_phone'] = $item['receiver_phone'];
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

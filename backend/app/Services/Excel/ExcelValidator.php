<?php

namespace App\Services\Excel;

use App\Models\Channel;
use Illuminate\Support\Arr;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExcelValidator
{
    /**
     * @return array{ok:bool, errors?:array<int,array{cell:string, expected:string, actual:string}>}
     */
    public function validateSheet(Worksheet $sheet, Channel $channel): array
    {
        $rules = $channel->excelValidationRules()->orderBy('id')->get();
        if ($rules->isEmpty()) {
            return ['ok' => true]; // 규칙 없으면 통과
        }

        $errors = [];
        foreach ($rules as $r) {
            $cellRef = trim($r->cell_ref);
            $expected = (string)$r->expected_label;
            $actual = (string)($sheet->getCell($cellRef, false)?->getValue() ?? '');

            // required가 아니고 빈 값이면 스킵
            if (!$r->is_required && ($actual === '' || $actual === null)) {
                continue;
            }

            // 비교는 공백/양끝 트림 후 문자 그대로 일치(대소문자 구분)
            if (trim($actual) !== trim($expected)) {
                $errors[] = [
                    'cell'     => $cellRef,
                    'expected' => $expected,
                    'actual'   => $actual,
                ];
            }
        }

        return empty($errors) ? ['ok' => true] : ['ok' => false, 'errors' => $errors];
    }
}

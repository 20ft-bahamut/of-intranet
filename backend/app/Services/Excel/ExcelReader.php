<?php

namespace App\Services\Excel;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ExcelReader
{
    /**
     * @return array<int, array<string, string>>  // 각 행: ['A'=>'...', 'B'=>'...']
     */
    public function readAsColMap(string $xlsxPath, int $startRow = 1): array
    {
        $reader = IOFactory::createReaderForFile($xlsxPath);
        $reader->setReadDataOnly(true);

        $spread = $reader->load($xlsxPath);
        /** @var Worksheet $sheet */
        $sheet = $spread->getSheet(0);

        $highestRow = (int) $sheet->getHighestRow();                // e.g. 500
        $highestCol = (string) $sheet->getHighestColumn();          // e.g. 'BA'
        $maxColIdx  = Coordinate::columnIndexFromString($highestCol); // e.g. 53

        $rows = [];
        for ($r = $startRow; $r <= $highestRow; $r++) {
            $row = [];
            for ($c = 1; $c <= $maxColIdx; $c++) {
                $colLetter = Coordinate::stringFromColumnIndex($c);
                $addr = $colLetter . $r; // 예: A3, BA12

                $val = null;
                try {
                    $cell = $sheet->getCell($addr, false); // createIfNotExists=false
                    if ($cell !== null) {
                        // 수식이 있으면 계산값, 아니면 원시값
                        $val = $cell->isFormula()
                            ? $cell->getCalculatedValue()
                            : $cell->getValue();
                    }
                } catch (\Throwable $e) {
                    // 깨진 셀/수식 오류 등은 빈값 처리
                    $val = null;
                }

                $row[$colLetter] = is_null($val) ? '' : trim((string)$val);
            }
            // 전열 공백이면 스킵
            if (!array_filter($row, fn($v) => $v !== '')) {
                continue;
            }
            $rows[] = $row;
        }

        // 메모리 해제
        $spread->disconnectWorksheets();
        unset($spread);

        return $rows;
    }
}

<?php

namespace App\Services\Excel;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ExcelReader
{
    private function isHtmlXls(string $path): bool
    {
        $head = @file_get_contents($path, false, null, 0, 2048);
        if ($head === false) return false;

        // 공백/개행이 앞에 있어도 잡히게
        return preg_match('/<\s*(html|table|thead|tr|td)\b/i', $head) === 1;
    }

    private function loadSpreadsheet(string $path): Spreadsheet
    {
        // 1) HTML로 위장된 xls 처리
        if ($this->isHtmlXls($path)) {
            $html = file_get_contents($path);

            // 한글 깨짐 방지: meta charset 주입
            if (stripos($html, 'charset=') === false) {
                $html = "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\n" . $html;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'of_htmlxls_') . '.html';
            file_put_contents($tmp, $html);

            // Html Reader 강제
            $reader = IOFactory::createReader('Html');
            $reader->setReadDataOnly(true);

            // PhpSpreadsheet 버전에 따라 있을 수도/없을 수도 있어 안전 처리
            if (method_exists($reader, 'setInputEncoding')) {
                $reader->setInputEncoding('UTF-8');
            }

            $spread = $reader->load($tmp);
            @unlink($tmp);
            return $spread;
        }

        // 2) 일반 엑셀
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        return $reader->load($path);
    }

    /**
     * ProcessChannelExcel에서 1회 로드 후 재사용하기 위함
     * @return array{spread:Spreadsheet, sheet:Worksheet}
     */
    public function loadFirstSheet(string $path): array
    {
        $spread = $this->loadSpreadsheet($path);
        $sheet = $spread->getSheet(0);
        return ['spread' => $spread, 'sheet' => $sheet];
    }

    /**
     * 기존 readAsColMap은 그대로 제공하되 로더를 위 fallback으로 교체
     * @return array<int, array<string, string>>
     */
    public function readAsColMap(string $path, int $startRow = 1): array
    {
        $loaded = $this->loadFirstSheet($path);
        try {
            return $this->readAsColMapFromSheet($loaded['sheet'], $startRow);
        } finally {
            $loaded['spread']->disconnectWorksheets();
            unset($loaded['spread']);
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function readAsColMapFromSheet(Worksheet $sheet, int $startRow = 1): array
    {
        $highestRow = (int) $sheet->getHighestRow();
        $highestCol = (string) $sheet->getHighestColumn();
        $maxColIdx  = Coordinate::columnIndexFromString($highestCol);

        $rows = [];
        for ($r = $startRow; $r <= $highestRow; $r++) {
            $row = [];
            for ($c = 1; $c <= $maxColIdx; $c++) {
                $colLetter = Coordinate::stringFromColumnIndex($c);
                $addr = $colLetter . $r;

                $val = null;
                try {
                    $cell = $sheet->getCell($addr, false);
                    if ($cell !== null) {
                        $val = $cell->isFormula()
                            ? $cell->getCalculatedValue()
                            : $cell->getValue();
                    }
                } catch (\Throwable $e) {
                    $val = null;
                }

                $row[$colLetter] = is_null($val) ? '' : trim((string)$val);
            }

            if (!array_filter($row, fn($v) => $v !== '')) continue;
            $rows[] = $row;
        }

        return $rows;
    }
}

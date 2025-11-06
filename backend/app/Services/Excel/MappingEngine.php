<?php

namespace App\Services\Excel;

use App\Models\ChannelExcelFieldMapping;

class MappingEngine
{
    /**
     * @param array<string,string> $rowCol ['A'=>'...', 'B'=>'...']
     * @param ChannelExcelFieldMapping $map
     */
    public function apply(array $rowCol, ChannelExcelFieldMapping $map): string
    {
        $type = $map->selector_type;
        $val  = $map->selector_value ?? '';

        return match ($type) {
            'col_ref'     => $this->valueFromColRef($rowCol, $val),
            'expr'        => $this->applyExpr($rowCol, $val),
            'header_text' => $this->valueFromColRef($rowCol, $val), // header -> col 변환은 업스트림에서 해결 시 여기도 col_ref 취급
            'regex'       => $this->applyRegex($rowCol, $val, $map->options ?? []),
            default       => '',
        };
    }

    private function valueFromColRef(array $rowCol, string $ref): string
    {
        $ref = strtoupper(trim($ref));
        return (string)($rowCol[$ref] ?? '');
    }

    /**
     * `${A}-${B}`, SPLIT(${C}, "-", 1), TRIM(), DIGITS(), COALESCE()
     */
    private function applyExpr(array $rowCol, string $expr): string
    {
        $s = $expr;

        // 1) ${A} 치환
        $s = preg_replace_callback('/\$\{([A-Z]{1,3})\}/', function($m) use ($rowCol) {
            $k = $m[1];
            return $rowCol[$k] ?? '';
        }, $s);

        // 2) 허용 함수 간단 파싱 (안전/제한적)
        // TRIM(text)
        $s = preg_replace_callback('/TRIM\((.*?)\)/', fn($m) => trim($this->stripQuotes($m[1])), $s);
        // DIGITS(text) -> 숫자만
        $s = preg_replace_callback('/DIGITS\((.*?)\)/', function($m){
            return preg_replace('/\D+/', '', $this->stripQuotes($m[1])) ?? '';
        }, $s);
        // COALESCE(a, b)
        $s = preg_replace_callback('/COALESCE\((.*?),(.*?)\)/', function($m){
            $a = trim($this->stripQuotes($m[1]));
            $b = trim($this->stripQuotes($m[2]));
            return $a !== '' ? $a : $b;
        }, $s);
        // SPLIT(text, "delim", idx)
        $s = preg_replace_callback('/SPLIT\((.*?),(.*?),(.*?)\)/', function($m){
            $text = $this->stripQuotes($m[1]);
            $del  = $this->stripQuotes($m[2]);
            $idx  = (int)trim($m[3]);
            $parts = explode($del, $text);
            return $parts[$idx] ?? '';
        }, $s);

        // 3) 백틱 템플릿 `` ... `` (표시용이라면 그냥 제거)
        $s = preg_replace('/`+/', '', $s);

        return trim((string)$s);
    }

    private function stripQuotes(string $v): string
    {
        $v = trim($v);
        if ((str_starts_with($v, '"') && str_ends_with($v, '"')) ||
            (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
            return substr($v, 1, -1);
        }
        return $v;
    }

    private function applyRegex(array $rowCol, string $pattern, array $opt): string
    {
        $hay = implode(' ', array_values($rowCol));
        $flags = '';
        if (str_contains($pattern, '(?i)')) { $flags = 'i'; $pattern = str_replace('(?i)', '', $pattern); }
        if (@preg_match('/'.$pattern.'/'.$flags, $hay, $m)) {
            return $m[1] ?? $m[0] ?? '';
        }
        return '';
    }
}

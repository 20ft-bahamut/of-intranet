<?php

namespace App\Services\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductMatcher
{
    /** @return int|null product_id */
    public function resolveProductId(int $channelId, ?string $listingTitle, ?string $optionTitle): ?int
    {
        $lt = $this->norm($listingTitle);
        $ot = $this->norm($optionTitle);

        // 제품명은 필수
        if ($lt === '') {
            return null;
        }

        // 옵션명이 있을 때 → 제품명 + 옵션명 둘 다 정확히 일치해야 함
        if ($ot !== '') {
            $row = DB::table('product_name_mappings')
                ->select('product_id')
                ->where('channel_id', $channelId)
                ->whereRaw('LOWER(TRIM(listing_title)) = ?', [$lt])
                ->whereRaw('LOWER(TRIM(option_title)) = ?', [$ot])
                ->first();

            if ($row?->product_id) {
                return (int)$row->product_id;
            }

            // fallback: LIKE 완화 (제품명/옵션명 모두 포함)
            $row = DB::table('product_name_mappings')
                ->select('product_id')
                ->where('channel_id', $channelId)
                ->whereRaw('LOWER(listing_title) LIKE ?', ['%'.$lt.'%'])
                ->whereRaw('LOWER(option_title) LIKE ?', ['%'.$ot.'%'])
                ->first();

            return $row?->product_id ? (int)$row->product_id : null;
        }

        // 옵션명이 비어있을 때 → 제품명만 비교 (옵션 컬럼은 완전히 무시)
        $row = DB::table('product_name_mappings')
            ->select('product_id')
            ->where('channel_id', $channelId)
            ->whereRaw('LOWER(TRIM(listing_title)) = ?', [$lt])
            ->first();

        if ($row?->product_id) {
            return (int)$row->product_id;
        }

        // fallback: 제품명 LIKE 완화
        $row = DB::table('product_name_mappings')
            ->select('product_id')
            ->where('channel_id', $channelId)
            ->whereRaw('LOWER(listing_title) LIKE ?', ['%'.$lt.'%'])
            ->first();

        return $row?->product_id ? (int)$row->product_id : null;
    }

    private function norm(?string $s): string
    {
        $s = trim((string)$s);
        // 괄호/중복공백 정리 등 추가 가능
        return mb_strtolower(preg_replace('/\s+/u',' ', $s));
    }
}

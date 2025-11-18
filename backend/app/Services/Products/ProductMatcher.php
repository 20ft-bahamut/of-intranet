<?php

namespace App\Services\Products;

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

        // 옵션 존재: 제품명 + 옵션명 정확 매칭 → 없으면 LIKE → 실패 시 후보 자동 등록
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

            if ($row?->product_id) {
                return (int)$row->product_id;
            }

            // ❗️매칭 실패: 후보 자동 등록
            $this->insertCandidate($channelId, $lt, $ot);
            return null;
        }

        // 옵션 없음: 제품명만 정확 → LIKE → 실패 시 후보 자동 등록(옵션 NULL)
        $row = DB::table('product_name_mappings')
            ->select('product_id')
            ->where('channel_id', $channelId)
            ->whereRaw('LOWER(TRIM(listing_title)) = ?', [$lt])
            ->first();

        if ($row?->product_id) {
            return (int)$row->product_id;
        }

        $row = DB::table('product_name_mappings')
            ->select('product_id')
            ->where('channel_id', $channelId)
            ->whereRaw('LOWER(listing_title) LIKE ?', ['%'.$lt.'%'])
            ->first();

        if ($row?->product_id) {
            return (int)$row->product_id;
        }

        // ❗️매칭 실패: 후보 자동 등록 (option_title = NULL)
        $this->insertCandidate($channelId, $lt, null);
        return null;
    }

    /**
     * 매칭 실패 시 후보 자동 등록.
     * - 동일 (channel_id, listing_title(norm), option_title(norm or NULL))가 있으면 건너뜀
     * - description에 "자동등록(매칭없음)" 표기
     */
    private function insertCandidate(int $channelId, string $normListing, ?string $normOption): void
    {
        // 이미 동일 후보/매핑이 있으면 패스
        $exists = DB::table('product_name_mappings')
            ->where('channel_id', $channelId)
            ->whereRaw('LOWER(TRIM(listing_title)) = ?', [$normListing])
            ->where(function ($q) use ($normOption) {
                if ($normOption === null || $normOption === '') {
                    $q->whereNull('option_title')->orWhere('option_title', '');
                } else {
                    $q->whereRaw('LOWER(TRIM(option_title)) = ?', [$normOption]);
                }
            })
            ->exists();

        if ($exists) return;

        // ProductMatcher::insertCandidate(...)
        DB::table('product_name_mappings')->insert([
            'product_id'    => null,                  // ✅ 후보는 NULL
            'channel_id'    => $channelId,
            'listing_title' => $normListing,          // norm() 처리된 문자열
            'option_title'  => ($normOption ?: null), // 빈문자 → NULL
            'description'   => '자동등록(매칭없음)',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

    }

    private function norm(?string $s): string
    {
        $s = trim((string)$s);
        if ($s === '') return '';
        // 괄호 제거/특수문자 축약 등 추가 가능
        return mb_strtolower(preg_replace('/\s+/u',' ', $s));
    }
}

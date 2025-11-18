<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductNameMapping extends Model
{
    protected $table = 'product_name_mappings';

    protected $fillable = [
        'product_id', 'channel_id', 'listing_title', 'option_title', 'description',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'channel_id' => 'integer',
        'last_backfilled_at' => 'datetime', // ← 추가
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** 미매핑만 */
    public function scopeUnmapped($q)
    {
        return $q->whereNull('product_id');
    }

    /** 검색(채널상품명/옵션/내부상품명/코드) */
    public function scopeSearch($q, ?string $term)
    {
        $term = trim((string)$term);
        if ($term === '') return $q;

        $like = "%{$term}%";
        return $q->where(function ($w) use ($like) {
            $w->where('listing_title', 'like', $like)
                ->orWhere('option_title', 'like', $like)
                ->orWhereHas('product', fn($p) =>
                $p->where('name', 'like', $like)->orWhere('code', 'like', $like)
                );
        });
    }

    /** 후보 우선 → 최신 */
    public function scopeDefaultOrder($q)
    {
        return $q->orderByRaw('product_id IS NULL DESC')->orderByDesc('id');
    }
}

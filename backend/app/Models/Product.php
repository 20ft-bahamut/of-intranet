<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name','code','max_merge_qty','spec','description','is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'max_merge_qty' => 'integer',
    ];

    // 우리가 쓰는 정식 관계
    public function nameMappings(): HasMany
    {
        return $this->hasMany(ProductNameMapping::class);
    }

    // 🔁 라라벨 중첩 바인딩이 기대하는 이름에 맞춘 "별칭" 관계
    // /products/{product}/.../{mapping} 에서 {mapping} 때문에 Product::mappings() 를 찾음
    public function mappings(): HasMany
    {
        return $this->nameMappings();
    }
}

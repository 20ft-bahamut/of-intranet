<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    protected $fillable = [
        'code','name','is_excel_encrypted','excel_data_start_row','is_active'
    ];
    // 엑셀 변환 프로필 (채널당 1건)
    public function excelTransformProfile(): HasOne
    {
        return $this->hasOne(ChannelExcelTransformProfile::class);
    }

    // 엑셀 검증 룰 (1:N) — 이미 쓰고 있다면 유지
    public function excelValidationRules(): HasMany
    {
        return $this->hasMany(ChannelExcelValidationRule::class);
    }
}

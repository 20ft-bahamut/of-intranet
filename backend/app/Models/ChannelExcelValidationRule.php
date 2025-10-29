<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelExcelValidationRule extends Model
{
    protected $fillable = [
        'channel_id',
        'cell_ref',        // 예: A1
        'expected_label',  // 예: 주문번호
        'is_required',     // 1/0
    ];

    protected $casts = [
        'channel_id'   => 'integer',
        'is_required'  => 'boolean',
    ];

    public $timestamps = true;

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }
}

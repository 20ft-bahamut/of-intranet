<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelExcelTransformProfile extends Model
{
    protected $fillable = [
        'channel_id',
        'tracking_col_ref', // 예: G 또는 G:G
        'courier_name',     // 예: 우체국택배
        'courier_code',     // 예: 9002
        'template_notes',
    ];

    protected $casts = [
        'channel_id' => 'integer',
    ];

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }
}

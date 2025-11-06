<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelExcelFieldMapping extends Model
{
    protected $fillable = [
        'channel_id','field_key','selector_type','selector_value','options'
    ];

    protected $casts = [
        'options' => 'array',
    ];

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }
}

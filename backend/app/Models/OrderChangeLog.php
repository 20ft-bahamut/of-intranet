<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderChangeLog extends Model
{
    protected $table = 'order_change_logs';

    // changed_at 컬럼 없음. created_at을 변경시각으로 사용
    protected $fillable = [
        'order_id','upload_id','source','field','old_value','new_value','changed_by',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderChangeLog extends Model
{
    protected $table = 'order_change_logs';

    protected $fillable = [
        'order_id','field','old_value','new_value','source','changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}

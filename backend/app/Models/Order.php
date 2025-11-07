<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    // app/Models/Order.php
    // app/Models/Order.php
    protected $fillable = [
        'channel_id','channel_order_no',
        'product_id','product_title','option_title','quantity','tracking_no',
        'buyer_name','buyer_phone','buyer_postcode','buyer_addr_full','buyer_addr1','buyer_addr2',
        'receiver_name','receiver_postcode','receiver_addr_full','receiver_addr1','receiver_addr2','receiver_phone',
        'shipping_request','customer_note','admin_memo',
        'ordered_at','status_src','status_std',
        'raw_payload','raw_meta','raw_hash',
    ];

    protected $casts = [
        'ordered_at' => 'datetime',
        'raw_meta'   => 'array', // json 자동 캐스팅
    ];

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductNameMapping extends Model
{
    protected $fillable = [
        'channel_id', 'product_id', 'listing_title', 'option_title', 'description',
    ];

    protected $casts = [
        'channel_id' => 'integer',
        'product_id' => 'integer',
    ];

    public function channel(){ return $this->belongsTo(Channel::class); }
    public function product(){ return $this->belongsTo(Product::class); }
}

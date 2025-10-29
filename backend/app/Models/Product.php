<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name','code','max_merge_qty','spec','description','is_active',
    ];

    protected $casts = [
        'max_merge_qty' => 'integer',
        'is_active'     => 'boolean',
    ];

    public $timestamps = true;
}

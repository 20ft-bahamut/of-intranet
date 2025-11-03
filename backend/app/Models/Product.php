<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name', 'code', 'max_merge_qty', 'spec', 'description', 'is_active',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'max_merge_qty' => 'integer',
    ];

    public function nameMappings()
    {
        return $this->hasMany(\App\Models\ProductNameMapping::class);
    }

}

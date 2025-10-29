<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    protected $fillable = [
        'code','name','is_excel_encrypted','excel_data_start_row','is_active'
    ];
}

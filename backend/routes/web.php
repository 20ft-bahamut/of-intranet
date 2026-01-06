<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BenchController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/bench', [BenchController::class, 'index']);


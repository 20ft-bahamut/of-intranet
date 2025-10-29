<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ChannelController;
use App\Http\Controllers\Api\V1\ChannelExcelValidationRuleController;
use App\Http\Controllers\Api\V1\ChannelExcelTransformProfileController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductNameMappingController;

Route::prefix('v1')->group(function () {
    Route::apiResource('channels', ChannelController::class);
    Route::apiResource('products', ProductController::class);

    // 채널별 엑셀 검증 규칙 (중첩 리소스)
    Route::get   ('channels/{channel}/excel-validations',                [ChannelExcelValidationRuleController::class, 'index']);
    Route::post  ('channels/{channel}/excel-validations',                [ChannelExcelValidationRuleController::class, 'store']);
    Route::put   ('channels/{channel}/excel-validations/{rule}',         [ChannelExcelValidationRuleController::class, 'update']);
    Route::patch ('channels/{channel}/excel-validations/{rule}',         [ChannelExcelValidationRuleController::class, 'update']);
    Route::delete('channels/{channel}/excel-validations/{rule}',         [ChannelExcelValidationRuleController::class, 'destroy']);

    // 단수 리소스: /channels/{channel}/excel-transform
    Route::get   ('channels/{channel}/excel-transform',  [ChannelExcelTransformProfileController::class, 'show']);
    Route::post  ('channels/{channel}/excel-transform',  [ChannelExcelTransformProfileController::class, 'store']);
    Route::put   ('channels/{channel}/excel-transform',  [ChannelExcelTransformProfileController::class, 'update']);
    Route::patch ('channels/{channel}/excel-transform',  [ChannelExcelTransformProfileController::class, 'update']);
    Route::delete('channels/{channel}/excel-transform',  [ChannelExcelTransformProfileController::class, 'destroy']);

    Route::get   ('products/{product}/product-name-mappings',                 [ProductNameMappingController::class, 'index']);
    Route::post  ('products/{product}/product-name-mappings',                 [ProductNameMappingController::class, 'store']);
    Route::put   ('products/{product}/product-name-mappings/{mapping}',       [ProductNameMappingController::class, 'update']);
    Route::patch ('products/{product}/product-name-mappings/{mapping}',       [ProductNameMappingController::class, 'update']);
    Route::delete('products/{product}/product-name-mappings/{mapping}',       [ProductNameMappingController::class, 'destroy']);
});

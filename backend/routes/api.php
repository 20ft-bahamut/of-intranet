<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\ChannelController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ChannelExcelValidationRuleController as ExcelValidationCtrl;
use App\Http\Controllers\Api\V1\ChannelExcelTransformProfileController as TransformCtrl;
use App\Http\Controllers\Api\V1\ProductNameMappingController as ProductNameMappingCtrl;
use App\Http\Controllers\Api\V1\ChannelExcelFieldMappingController as FieldMappingCtrl;
use App\Http\Controllers\Api\V1\OrderUploadController;

Route::prefix('v1')->scopeBindings()->group(function () {

    // Top-level
    Route::apiResource('channels', ChannelController::class);
    Route::apiResource('products', ProductController::class);

    // Channels.*
    Route::prefix('channels/{channel}')->group(function () {
        // excel-validations
        Route::get   ('excel-validations',                 [ExcelValidationCtrl::class, 'index']);
        Route::post  ('excel-validations',                 [ExcelValidationCtrl::class, 'store']);
        Route::match (['put','patch'],'excel-validations/{rule}', [ExcelValidationCtrl::class, 'update']);
        Route::delete('excel-validations/{rule}',          [ExcelValidationCtrl::class, 'destroy']);

        // excel-transform (singular)
        Route::get   ('excel-transform',                   [TransformCtrl::class, 'show']);
        Route::post  ('excel-transform',                   [TransformCtrl::class, 'store']);
        Route::match (['put','patch'],'excel-transform',   [TransformCtrl::class, 'update']);
        Route::delete('excel-transform',                   [TransformCtrl::class, 'destroy']);

        // field-mappings
        Route::get   ('field-mappings',                    [FieldMappingCtrl::class, 'index']);
        Route::post  ('field-mappings',                    [FieldMappingCtrl::class, 'store']);
        Route::get   ('field-mappings/{mapping}',          [FieldMappingCtrl::class, 'show']);
        Route::match (['put','patch'],'field-mappings/{mapping}', [FieldMappingCtrl::class, 'update']);
        Route::delete('field-mappings/{mapping}',          [FieldMappingCtrl::class, 'destroy']);
    });

    Route::post('channels/{channel}/orders/upload', [OrderUploadController::class, 'upload']);

    // Products.*
    Route::prefix('products/{product}')->group(function () {
        Route::get   ('product-name-mappings',                    [ProductNameMappingCtrl::class, 'index']);
        Route::post  ('product-name-mappings',                    [ProductNameMappingCtrl::class, 'store']);
        Route::match (['put','patch'],'product-name-mappings/{mapping}', [ProductNameMappingCtrl::class, 'update']);
        Route::delete('product-name-mappings/{mapping}',          [ProductNameMappingCtrl::class, 'destroy']);
    });
});

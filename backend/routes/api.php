<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\ChannelController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ChannelExcelValidationRuleController as ExcelValidationController;
use App\Http\Controllers\Api\V1\ChannelExcelTransformProfileController as TransformController;
use App\Http\Controllers\Api\V1\ProductNameMappingController as ProductNameMappingController;
use App\Http\Controllers\Api\V1\ChannelExcelFieldMappingController as FieldMappingController;
use App\Http\Controllers\Api\V1\OrderUploadController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\StatsController;

// ✅ 새로 추가
use App\Http\Controllers\Api\V1\ProductNameMappingAdminController as ProductNameMappingAdminController;

Route::prefix('v1')->scopeBindings()->group(function () {
    // 공통 메서드 세트
    $PUT_PATCH = ['put', 'patch'];

    // Top-level
    Route::apiResource('channels', ChannelController::class);
    Route::apiResource('products', ProductController::class);

    // Channels.*
    Route::prefix('channels/{channel}')->group(function () use ($PUT_PATCH) {
        // excel-validations
        Route::get('excel-validations', [ExcelValidationController::class, 'index']);
        Route::post('excel-validations', [ExcelValidationController::class, 'store']);
        Route::match($PUT_PATCH, 'excel-validations/{rule}', [ExcelValidationController::class, 'update']);
        Route::delete('excel-validations/{rule}', [ExcelValidationController::class, 'destroy']);

        // excel-transform (singular)
        Route::get('excel-transform', [TransformController::class, 'show']);
        Route::post('excel-transform', [TransformController::class, 'store']);
        Route::match($PUT_PATCH, 'excel-transform', [TransformController::class, 'update']);
        Route::delete('excel-transform', [TransformController::class, 'destroy']);

        // field-mappings
        Route::get('field-mappings', [FieldMappingController::class, 'index']);
        Route::post('field-mappings', [FieldMappingController::class, 'store']);
        Route::get('field-mappings/{mapping}', [FieldMappingController::class, 'show']);
        Route::match($PUT_PATCH, 'field-mappings/{mapping}', [FieldMappingController::class, 'update']);
        Route::delete('field-mappings/{mapping}', [FieldMappingController::class, 'destroy']);

        // Orders
        Route::post('orders/upload', [OrderUploadController::class, 'upload'])->name('channels.orders.upload');
        Route::post('orders/commit', [OrderUploadController::class, 'commit'])->name('channels.orders.commit');
    });

    // Products.*
    Route::prefix('products/{product}')->group(function () use ($PUT_PATCH) {
        Route::get('product-name-mappings', [ProductNameMappingController::class, 'index']);
        Route::post('product-name-mappings', [ProductNameMappingController::class, 'store']);
        Route::match($PUT_PATCH, 'product-name-mappings/{mapping}', [ProductNameMappingController::class, 'update']);
        Route::delete('product-name-mappings/{mapping}', [ProductNameMappingController::class, 'destroy']);
    });

    // ✅ 상품명 맵핑(관리자 전체 조회/수동 매핑/백필 등)
    Route::prefix('product-name-mappings')->group(function () {
        Route::get('/',        [ProductNameMappingAdminController::class, 'index']);    // 전체 or 필터(unmapped=1|0)
        Route::put('{mapping}/assign',   [ProductNameMappingAdminController::class, 'assign']);
        Route::put('{mapping}/unassign', [ProductNameMappingAdminController::class, 'unassign']);
        Route::post('{mapping}/backfill',[ProductNameMappingAdminController::class, 'backfill']);
        Route::delete('{mapping}',       [ProductNameMappingAdminController::class, 'destroy']);
    });

    // ✅ Orders (전체 목록 / 단건 / 일부 수정)
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::match($PUT_PATCH, 'orders/{order}', [OrderController::class, 'update'])->name('orders.update');

    // Dashboard Stats
    Route::prefix('stats')->group(function () {
        Route::get('overview',       [StatsController::class, 'overview']);       // ?date=YYYY-MM-DD
        Route::get('top-products',   [StatsController::class, 'topProducts']);    // ?from=..&to=..&channel=code&q=..&include_transitions=1&limit=10
        Route::get('by-channel',     [StatsController::class, 'byChannel']);      // ?from=..&to=..&channel=code&q=..&include_transitions=1
        Route::get('recent-orders',  [StatsController::class, 'recentOrders']);   // ?limit=10
    });
});

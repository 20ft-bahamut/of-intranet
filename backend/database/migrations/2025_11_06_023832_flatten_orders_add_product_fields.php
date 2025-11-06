<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            // 상품 식별/원본명/옵션/수량/송장
            $t->unsignedBigInteger('product_id')->nullable()->after('channel_order_no');
            $t->string('product_name', 255)->nullable()->after('product_id');   // 원본 상품명(매핑 전)
            $t->string('option_name', 255)->nullable()->after('product_name');  // 원본 옵션명(없으면 null)
            $t->unsignedInteger('quantity')->default(1)->after('option_name');
            $t->string('tracking_no', 64)->nullable()->after('quantity');

            // 조회/중복 방지에 유용한 복합 유니크
            // "채널 + 주문번호 + 상품"을 1행으로 본다. (옵션까지 구분하려면 여기에 option_name도 포함)
            $t->unique(['channel_id', 'channel_order_no', 'product_id'], 'orders_uq_channel_order_product');
        });

        // FK (선택)
        Schema::table('orders', function (Blueprint $t) {
            $t->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropForeign(['product_id']);
            $t->dropUnique('orders_uq_channel_order_product');

            $t->dropColumn(['product_id','product_name','option_name','quantity','tracking_no']);
        });
    }
};

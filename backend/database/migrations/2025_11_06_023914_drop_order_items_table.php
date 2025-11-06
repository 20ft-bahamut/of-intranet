<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ⚠️ 존재할 때만 삭제 (다른 DB에서 오류 방지)
        Schema::dropIfExists('order_items');
    }

    public function down(): void
    {
        // 되돌리기: 구조 복원 (필요 없으면 비워둬도 됨)
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('product_name')->nullable();
            $t->string('option_name')->nullable();
            $t->unsignedInteger('quantity')->default(1);
            $t->string('tracking_no', 64)->nullable();
            $t->timestamps();

            $t->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $t->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }
};

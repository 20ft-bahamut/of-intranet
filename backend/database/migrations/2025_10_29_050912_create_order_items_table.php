<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->integer('line_no')->default(1);
            $table->integer('quantity')->default(1);

            // 우체국 전용/확장
            $table->string('tracking_no_post', 50)->nullable(); // 우체국 송장
            $table->string('tracking_no', 50)->nullable();      // 일반 송장(확장)
            $table->string('courier_code', 20)->nullable();     // 9002 등(확장)

            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('total_price', 12, 2)->nullable();

            $table->json('raw_row_json'); // 원본 ROW JSON 전체

            // 생성 열: has_tracking (MariaDB 10.5+)
            $table->boolean('has_tracking')->storedAs(
                "CASE WHEN tracking_no_post IS NOT NULL OR tracking_no IS NOT NULL THEN 1 ELSE 0 END"
            );

            $table->timestamps();

            $table->unique(['order_id', 'line_no']);
            $table->index('product_id');
            $table->index('tracking_no_post');
            $table->index(['order_id', 'has_tracking']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }

};

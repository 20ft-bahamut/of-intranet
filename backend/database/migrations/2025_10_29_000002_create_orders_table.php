<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->string('channel_order_no', 100);

            $table->string('buyer_phone', 50)->nullable();
            $table->string('buyer_postcode', 10)->nullable();
            $table->string('buyer_addr_full', 400)->nullable();
            $table->string('buyer_addr1', 255)->nullable();
            $table->string('buyer_addr2', 255)->nullable();

            $table->string('receiver_name', 100)->nullable();
            $table->string('receiver_postcode', 10)->nullable();
            $table->string('receiver_addr_full', 400)->nullable();
            $table->string('receiver_addr1', 255)->nullable();
            $table->string('receiver_addr2', 255)->nullable();
            $table->string('receiver_phone', 50)->nullable();

            $table->string('shipping_request', 255)->nullable();
            $table->string('customer_note', 255)->nullable();
            $table->string('admin_memo', 255)->nullable();

            $table->dateTime('ordered_at')->nullable();
            $table->string('status_src', 100)->nullable(); // 원천 상태(워딩 보존)
            $table->string('status_std', 50)->nullable();  // 표준 상태(미사용이면 비워둠)

            $table->timestamps();

            $table->unique(['channel_id', 'channel_order_no']);
            $table->index(['channel_id', 'ordered_at']);
            $table->index('status_std');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }

};

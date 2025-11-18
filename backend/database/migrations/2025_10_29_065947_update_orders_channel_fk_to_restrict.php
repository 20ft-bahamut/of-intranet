<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // FK 이름은 DB에 실제 생성된 이름으로 맞춰야 함.
            // 보통은 `orders_channel_id_foreign`
            $table->dropForeign(['channel_id']);
            $table->foreign('channel_id')
                ->references('id')->on('by-channel')
                ->onDelete('restrict'); // 또는 ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['channel_id']);
            $table->foreign('channel_id')
                ->references('id')->on('by-channel')
                ->onDelete('cascade'); // 이전 상태가 cascade였다면 복구
        });
    }

};

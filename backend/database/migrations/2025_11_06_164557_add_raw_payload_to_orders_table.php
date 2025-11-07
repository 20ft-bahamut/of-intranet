<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_raw_payload_to_orders_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // MariaDB의 JSON은 LONGTEXT alias라서 longText로 저장 권장
            $table->longText('raw_payload')->nullable()->after('admin_memo'); // 원본 한 줄 전체(JSON 문자열)
            $table->json('raw_meta')->nullable()->after('raw_payload');       // 파일/시트/행/채널 등 메타
            $table->char('raw_hash', 64)->nullable()->after('raw_meta');      // 중복 방지/추적용 SHA-256
            $table->index(['channel_id', 'raw_hash'], 'orders_channel_rawhash_idx');
        });
    }
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_channel_rawhash_idx');
            $table->dropColumn(['raw_payload', 'raw_meta', 'raw_hash']);
        });
    }
};

<?php
// database/migrations/2025_11_07_000001_add_indexes_for_stats_safe.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private function indexExists(string $table, string $index): bool
    {
        $db = DB::getDatabaseName();
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1";
        return (bool) DB::selectOne($sql, [$db, $table, $index]);
    }

    public function up(): void
    {
        // 짧고 명확한 이름으로 통일 (MariaDB 64자 제한 여유)
        $idx1 = 'idx_orders_ordered_at';
        $idx2 = 'idx_orders_updated_at';
        $idx3 = 'idx_orders_chid_ordered';
        $idx4 = 'idx_orders_prodid_ordered';

        // 1) ordered_at
        if (!$this->indexExists('orders', $idx1)) {
            Schema::table('orders', function (Blueprint $t) use ($idx1) {
                $t->index(['ordered_at'], $idx1);
            });
        }

        // 2) updated_at
        if (!$this->indexExists('orders', $idx2)) {
            Schema::table('orders', function (Blueprint $t) use ($idx2) {
                $t->index(['updated_at'], $idx2);
            });
        }

        // 3) (channel_id, ordered_at)
        if (!$this->indexExists('orders', $idx3)) {
            Schema::table('orders', function (Blueprint $t) use ($idx3) {
                $t->index(['channel_id', 'ordered_at'], $idx3);
            });
        }

        // 4) (product_id, ordered_at)
        if (!$this->indexExists('orders', $idx4)) {
            Schema::table('orders', function (Blueprint $t) use ($idx4) {
                $t->index(['product_id', 'ordered_at'], $idx4);
            });
        }
    }

    public function down(): void
    {
        $indexes = [
            'idx_orders_ordered_at',
            'idx_orders_updated_at',
            'idx_orders_chid_ordered',
            'idx_orders_prodid_ordered',
        ];

        Schema::table('orders', function (Blueprint $t) use ($indexes) {
            foreach ($indexes as $idx) {
                // 존재할 때만 드롭 (일부 드라이버는 dropIndex가 조용히 실패함)
                try { $t->dropIndex($idx); } catch (\Throwable $e) {}
            }
        });
    }
};

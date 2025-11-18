<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_name_mappings', function (Blueprint $table) {
            // 최근 백필 실행 시각
            $table->timestamp('last_backfilled_at')->nullable()->after('updated_at');
            $table->index('last_backfilled_at', 'pnm_last_backfilled_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product_name_mappings', function (Blueprint $table) {
            $table->dropIndex('pnm_last_backfilled_at_idx');
            $table->dropColumn('last_backfilled_at');
        });
    }
};

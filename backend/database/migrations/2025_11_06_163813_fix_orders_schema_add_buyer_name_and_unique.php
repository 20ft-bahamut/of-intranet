<?php
// database/migrations/xxxx_xx_xx_xxxxxx_fix_orders_schema_add_buyer_name_and_unique.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // 1) buyer_name 추가 (전화/주소 옆이 자연스러움)
            if (!Schema::hasColumn('orders', 'buyer_name')) {
                $table->string('buyer_name', 100)->nullable()->after('tracking_no');
            }
        });

        // 2) 유니크 키 정리
        //    - (channel_id, channel_order_no) 유니크는 제거 (상품별 행을 허용해야 함)
        //    - (channel_id, channel_order_no, product_id) 유니크는 유지
        try {
            DB::statement('ALTER TABLE orders DROP INDEX orders_channel_id_channel_order_no_unique');
        } catch (\Throwable $e) {
            // 이미 없으면 무시
        }
    }

    public function down(): void
    {
        // 되돌림(필요시)
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'buyer_name')) {
                $table->dropColumn('buyer_name');
            }
        });

        try {
            DB::statement('ALTER TABLE orders ADD UNIQUE INDEX orders_channel_id_channel_order_no_unique (channel_id, channel_order_no)');
        } catch (\Throwable $e) {}
    }
};


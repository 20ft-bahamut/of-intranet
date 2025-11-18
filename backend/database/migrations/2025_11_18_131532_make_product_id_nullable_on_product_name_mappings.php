<?php
// database/migrations/2025_11_11_000001_make_product_id_nullable_on_product_name_mappings.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_name_mappings', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change(); // ✅ NULL 허용
        });
    }

    public function down(): void
    {
        Schema::table('product_name_mappings', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable(false)->change(); // 되돌림
        });
    }
};

<?php
// database/migrations/xxxx_xx_xx_xxxxxx_rename_product_columns_on_orders_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // MariaDB: CHANGE old new type null/default
            $table->renameColumn('product_name', 'product_title');
            $table->renameColumn('option_name',  'option_title');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('product_title', 'product_name');
            $table->renameColumn('option_title',  'option_name');
        });
    }
};

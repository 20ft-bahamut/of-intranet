<?php
// database/migrations/2025_11_04_000001_add_default_excel_password_to_channels.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('by-channel', function (Blueprint $t) {
            $t->string('default_excel_password', 255)->nullable()->after('is_active');
        });
    }
    public function down(): void {
        Schema::table('by-channel', function (Blueprint $t) {
            $t->dropColumn('default_excel_password');
        });
    }
};


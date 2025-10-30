<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_excel_transform_profiles', function (Blueprint $table) {
            $table->string('courier_name', 50)->nullable()->change();
            $table->string('courier_code', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('channel_excel_transform_profiles', function (Blueprint $table) {
            $table->string('courier_name', 50)->nullable(false)->change();
            $table->string('courier_code', 20)->nullable(false)->change();
        });
    }

};

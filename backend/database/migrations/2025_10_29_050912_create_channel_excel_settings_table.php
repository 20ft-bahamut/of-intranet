<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_excel_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->binary('excel_password_cipher')->nullable(); // 암호는 암호화 저장(옵션)
            $table->string('header_locator', 20)->nullable();    // fixed_row/auto 등
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index('channel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_excel_settings');
    }

};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_excel_transform_profiles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('channel_id')->constrained('by-channel')->cascadeOnDelete();
            $table->string('tracking_col_ref', 10);   // G 또는 G:G 형태
            $table->string('courier_name', 50);       // 우체국택배
            $table->string('courier_code', 20);       // 9002 등
            $table->string('template_notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_excel_transform_profiles');
    }

};

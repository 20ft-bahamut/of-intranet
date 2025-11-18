<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('by-channel', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 50)->unique();   // smartstore, coupang ...
            $table->string('name', 100);
            $table->boolean('is_excel_encrypted')->default(false);
            $table->integer('excel_data_start_row')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('by-channel');
    }

};

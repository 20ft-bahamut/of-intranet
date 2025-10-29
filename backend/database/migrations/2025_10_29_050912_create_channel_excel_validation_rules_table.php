<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_excel_validation_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->string('cell_ref', 10);               // A1, B2...
            $table->string('expected_label', 100);        // 주문번호, 수취인이름...
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['channel_id', 'cell_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_excel_validation_rules');
    }

};

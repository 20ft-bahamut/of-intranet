<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_name_mappings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('listing_title', 255);
            $table->string('option_title', 255)->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamps();

            // ★ 인덱스 이름을 짧게 지정
            $table->unique(
                ['channel_id', 'listing_title', 'option_title'],
                'pnm_channel_listing_option_uq'
            );

            // (선택) 조회 속도용 보조 인덱스
            $table->index(['channel_id', 'listing_title'], 'pnm_channel_listing_idx');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('product_name_mappings');
    }

};

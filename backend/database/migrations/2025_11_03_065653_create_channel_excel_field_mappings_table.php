<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('channel_excel_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            // 표준 필드 키: order_no, order_date, receiver_name, ...
            $table->string('field_key', 50);

            // 단일 선택자 or 표현식
            // enum: col_ref | header_text | regex | expr
            $table->enum('selector_type', ['col_ref','header_text','regex','expr'])->index();

            // 값 예시:
            //  - col_ref: "A" / "AA"
            //  - header_text: "상품주문번호"
            //  - regex: "(?i)상품주문번호"
            //  - expr: "`${order_date}-${order_no_raw}`" or "SPLIT(${C},\"-\",1)"
            $table->string('selector_value', 255);

            // 추가 옵션(JSON): {"delimiter":"-","trim":true}
            $table->json('options')->nullable();

            $table->timestamps();

            // FK & 인덱스
            $table->foreign('channel_id')
                ->references('id')->on('by-channel')
                ->onDelete('cascade');

            // 채널별 한 필드당 1개 매핑 고정
            $table->unique(['channel_id', 'field_key'], 'uq_cxfm_channel_field');

            // 검색 보조
            $table->index(['channel_id', 'field_key'], 'idx_cxfm_channel_field');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_excel_field_mappings');
    }
};

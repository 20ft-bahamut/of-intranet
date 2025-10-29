<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_name_mappings', function (Blueprint $table) {
            // 기존 유니크 이름이 길어서 직접 지정했을 수 있음. 둘 중 해당하는 걸로 드롭.
            $table->dropUnique('pnm_channel_listing_option_uq');
            // 혹은 기본 이름이었으면:
            // $table->dropUnique(['channel_id','listing_title','option_title']);

            // 새 유니크: (channel_id, product_id, listing_title, option_title)
            $table->unique(
                ['channel_id','product_id','listing_title','option_title'],
                'pnm_channel_product_listing_option_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::table('product_name_mappings', function (Blueprint $table) {
            $table->dropUnique('pnm_channel_product_listing_option_uq');
            $table->unique(
                ['channel_id','listing_title','option_title'],
                'pnm_channel_listing_option_uq'
            );
        });
    }

};

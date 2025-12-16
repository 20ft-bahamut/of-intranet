<?php
// database/migrations/xxxx_xx_xx_create_order_change_logs_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            $table->uuid('upload_id')->nullable()->index();   // 업로드 배치 식별자
            $table->string('source', 50)->nullable();         // 예: excel:smartstore

            $table->string('field', 64);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();

            $table->unsignedBigInteger('changed_by')->nullable(); // 업로드면 null
            $table->timestamps();

            $table->index(['order_id','field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_change_logs');
    }
};

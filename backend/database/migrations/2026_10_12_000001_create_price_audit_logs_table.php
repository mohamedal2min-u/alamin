<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // سجل تعديل الأسعار — للإضافة فقط، لا يُعدَّل ولا يُحذف (منع التزوير).
        Schema::create('price_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('record_type', 30);              // expense | water_log | sale
            $table->unsignedBigInteger('record_id');
            $table->decimal('old_price', 14, 4)->nullable(); // null = أول تسعير
            $table->decimal('new_price', 14, 4)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['farm_id', 'record_type', 'record_id']);
            $table->index(['farm_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_audit_logs');
    }
};

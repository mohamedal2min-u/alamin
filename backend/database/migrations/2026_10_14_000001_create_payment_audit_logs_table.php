<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // سجل تعديل المدفوعات/المستلم — للإضافة فقط، لا يُعدَّل ولا يُحذف.
        Schema::create('payment_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('record_type', 30);               // expense | water_log | sale
            $table->unsignedBigInteger('record_id');
            $table->decimal('old_amount', 14, 2)->nullable(); // المدفوع/المستلم قبل
            $table->decimal('new_amount', 14, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['farm_id', 'record_type', 'record_id']);
            $table->index(['farm_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_audit_logs');
    }
};

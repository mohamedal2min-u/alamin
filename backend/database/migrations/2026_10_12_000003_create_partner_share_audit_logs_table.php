<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // سجل تعديل حصص الشركاء — للإضافة فقط، لا يُعدَّل ولا يُحذف (منع التزوير).
        Schema::create('partner_share_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('old_percent', 5, 2)->nullable();
            $table->decimal('new_percent', 5, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['farm_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_share_audit_logs');
    }
};

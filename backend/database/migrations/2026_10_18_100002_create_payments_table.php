<?php

use App\Services\PaymentLedgerService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // سجل الدفعات المؤرَّخ: كل دفعة صف مستقل، ومجموعها = المدفوع/المستلم على السجل
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('payable_type', 20);              // expense | sale | water_log
            $table->unsignedBigInteger('payable_id');
            $table->decimal('amount', 14, 2);                 // سالب فقط لتصحيح قديم عبر قائمة المراجعة
            $table->date('paid_at');
            $table->string('method', 20)->default('cash');    // cash | transfer | other
            $table->text('note')->nullable();
            $table->boolean('is_opening')->default(false);    // رصيد افتتاحي منقول من المدفوع التراكمي
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['farm_id', 'payable_type', 'payable_id']);
            $table->index(['farm_id', 'contact_id', 'paid_at']);
            $table->index('contact_id');
        });

        // نقل المدفوع التراكمي الحالي كدفعة افتتاحية واحدة بتاريخ السجل (آمن عند إعادة التشغيل)
        app(PaymentLedgerService::class)->backfillOpening();
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

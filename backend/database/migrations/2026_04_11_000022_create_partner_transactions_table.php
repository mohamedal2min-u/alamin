<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->restrictOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->restrictOnDelete();
            $table->foreignId('flock_id')->nullable()->constrained('flocks')->nullOnDelete();
            $table->date('transaction_date');
            $table->string('transaction_type', 30);
            $table->decimal('amount', 14, 2);
            $table->string('description')->nullable();
            $table->string('reference_no', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['farm_id', 'partner_id', 'transaction_date'], 'idx_partner_txn_farm_partner');
        });

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE partner_transactions
            ADD CONSTRAINT chk_partner_txn_type
            CHECK (transaction_type IN ('deposit', 'withdraw', 'profit', 'loss', 'adjustment', 'settlement'))
        ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_transactions');
    }
};

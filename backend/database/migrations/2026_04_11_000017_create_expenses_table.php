<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->restrictOnDelete();
            $table->foreignId('flock_id')->nullable()->constrained('flocks')->nullOnDelete();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->date('entry_date');
            $table->string('expense_type', 50)->nullable();
            $table->decimal('quantity', 14, 3)->nullable();
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->decimal('total_amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('remaining_amount', 14, 2)->default(0);
            $table->string('payment_status', 30)->default('paid');
            $table->string('reference_no', 100)->nullable();
            $table->string('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('linked_inventory_transaction_id')
                ->nullable()
                ->constrained('inventory_transactions')
                ->nullOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['farm_id', 'flock_id', 'entry_date'], 'idx_expenses_farm_flock_date');
            $table->index('expense_category_id', 'idx_expenses_category');
            $table->index('payment_status', 'idx_expenses_payment_status');
        });

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE expenses
            ADD CONSTRAINT chk_expenses_total_amount
            CHECK (total_amount >= 0)
        ");
        }

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE expenses
            ADD CONSTRAINT chk_expenses_paid_amount
            CHECK (paid_amount >= 0)
        ");
        }

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE expenses
            ADD CONSTRAINT chk_expenses_remaining_amount
            CHECK (remaining_amount >= 0)
        ");
        }

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE expenses
            ADD CONSTRAINT chk_expenses_payment_status
            CHECK (payment_status IN ('paid', 'partial', 'debt'))
        ");
        }

        DB::statement("
            CREATE INDEX idx_expenses_unpaid
            ON expenses (farm_id, entry_date)
            WHERE payment_status IN ('partial', 'debt')
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};

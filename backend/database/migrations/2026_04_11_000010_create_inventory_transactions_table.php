<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('flock_id')->nullable()->constrained('flocks')->nullOnDelete();
            $table->date('transaction_date');
            $table->string('transaction_type', 30);
            $table->string('direction', 10);
            $table->string('source_module', 50)->nullable();
            $table->decimal('original_quantity', 14, 3)->nullable();
            $table->decimal('computed_quantity', 14, 3);
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->decimal('total_amount', 14, 2)->nullable();
            $table->string('payment_status', 30)->nullable();
            $table->string('supplier_name', 190)->nullable();
            $table->string('invoice_no', 100)->nullable();
            $table->string('invoice_attachment_path')->nullable();
            $table->string('reference_no', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['farm_id', 'item_id', 'transaction_date'], 'idx_inv_txn_farm_item_date');
        });

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE inventory_transactions
            ADD CONSTRAINT chk_inv_txn_type
            CHECK (transaction_type IN ('purchase', 'consumption', 'adjustment', 'return', 'transfer'))
        ");
        }

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE inventory_transactions
            ADD CONSTRAINT chk_inv_txn_direction
            CHECK (direction IN ('in', 'out'))
        ");
        }

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE inventory_transactions
            ADD CONSTRAINT chk_inv_txn_payment_status
            CHECK (payment_status IS NULL OR payment_status IN ('paid', 'unpaid', 'partial', 'debt'))
        ");
        }

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE inventory_transactions
            ADD CONSTRAINT chk_inv_txn_quantity
            CHECK (computed_quantity > 0)
        ");
        }

        // فهرس جزئي للحركات المرتبطة بفوج فقط
        DB::statement("
            CREATE INDEX idx_inv_txn_flock
            ON inventory_transactions (flock_id)
            WHERE flock_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};

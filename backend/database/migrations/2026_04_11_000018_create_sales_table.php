<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->restrictOnDelete();
            $table->foreignId('flock_id')->constrained('flocks')->restrictOnDelete();
            $table->date('sale_date');
            $table->string('reference_no', 100)->nullable();
            $table->string('buyer_name', 190)->nullable();
            $table->string('invoice_attachment_path')->nullable();
            $table->decimal('gross_amount', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2);
            $table->decimal('received_amount', 14, 2)->default(0);
            $table->decimal('remaining_amount', 14, 2)->default(0);
            $table->string('payment_status', 30)->default('paid');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['farm_id', 'flock_id', 'sale_date'], 'idx_sales_farm_flock_date');
        });

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE sales
            ADD CONSTRAINT chk_sales_payment_status
            CHECK (payment_status IN ('paid', 'partial', 'debt'))
        ");
        }

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE sales
            ADD CONSTRAINT chk_sales_gross_amount
            CHECK (gross_amount >= 0)
        ");
        }

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE sales
            ADD CONSTRAINT chk_sales_net_amount
            CHECK (net_amount >= 0)
        ");
        }

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE sales
            ADD CONSTRAINT chk_sales_discount
            CHECK (discount_amount >= 0)
        ");
        }

        DB::statement("
            CREATE INDEX idx_sales_unpaid
            ON sales (farm_id, sale_date)
            WHERE payment_status IN ('partial', 'debt')
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};

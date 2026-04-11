<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flock_feed_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->restrictOnDelete();
            $table->foreignId('flock_id')->constrained('flocks')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->date('entry_date');
            $table->decimal('quantity', 14, 3);
            $table->string('unit_label', 50)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('worker_id')->nullable()->constrained('users')->nullOnDelete();
            // ربط بحركة المخزون المقابلة — يُنشأ atomically في Action
            $table->foreignId('inventory_transaction_id')
                ->nullable()
                ->constrained('inventory_transactions')
                ->nullOnDelete();
            $table->timestampTz('editable_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['flock_id', 'entry_date'], 'idx_feed_logs_flock_date');
        });

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE flock_feed_logs
            ADD CONSTRAINT chk_feed_log_quantity
            CHECK (quantity > 0)
        ");
        }

        DB::statement("
            CREATE INDEX idx_feed_logs_editable
            ON flock_feed_logs (flock_id, editable_until)
            WHERE editable_until IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('flock_feed_logs');
    }
};

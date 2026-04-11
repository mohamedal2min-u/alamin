<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained('farms')->restrictOnDelete();
            $table->foreignId('flock_id')->constrained('flocks')->restrictOnDelete();
            $table->integer('birds_count');
            $table->decimal('total_weight_kg', 14, 3);
            $table->decimal('avg_weight_kg', 10, 3)->nullable();
            $table->decimal('unit_price_per_kg', 14, 4)->nullable();
            $table->decimal('line_total', 14, 2);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index('sale_id', 'idx_sale_items_sale');
        });

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE sale_items
            ADD CONSTRAINT chk_sale_items_birds_count
            CHECK (birds_count > 0)
        ");
        }

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE sale_items
            ADD CONSTRAINT chk_sale_items_weight
            CHECK (total_weight_kg > 0)
        ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('current_quantity', 14, 3)->default(0);
            $table->decimal('average_cost', 14, 4)->nullable();
            $table->timestampTz('last_in_at')->nullable();
            $table->timestampTz('last_out_at')->nullable();
            $table->string('status', 30)->nullable();
            $table->timestampsTz();

            $table->unique(['warehouse_id', 'item_id'], 'uidx_warehouse_items_wh_item');
            $table->index(['farm_id', 'warehouse_id', 'item_id'], 'idx_warehouse_items_farm_wh');
        });

        // فهرس جزئي: الأصناف ذات الرصيد الموجب فقط
        DB::statement("
            CREATE INDEX idx_warehouse_items_low_stock
            ON warehouse_items (farm_id, item_id)
            WHERE current_quantity > 0
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_items');
    }
};

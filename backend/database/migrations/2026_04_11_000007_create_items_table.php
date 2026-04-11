<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->restrictOnDelete();
            $table->foreignId('item_type_id')->constrained('item_types')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('input_unit', 50);
            $table->decimal('unit_value', 14, 3);
            $table->string('content_unit', 50);
            $table->decimal('minimum_stock', 14, 3)->nullable();
            $table->decimal('default_cost', 14, 4)->nullable();
            $table->string('status', 30)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['farm_id', 'name'], 'uidx_items_farm_name');
        });

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE items
            ADD CONSTRAINT chk_items_unit_value
            CHECK (unit_value > 0)
        ");
        }

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE items
            ADD CONSTRAINT chk_items_status
            CHECK (status IN ('active', 'inactive'))
        ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};

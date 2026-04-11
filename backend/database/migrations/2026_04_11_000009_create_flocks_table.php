<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->restrictOnDelete();
            $table->string('name', 190);
            $table->string('status', 30)->default('draft');
            $table->date('start_date');
            $table->date('close_date')->nullable();
            $table->integer('initial_count');
            $table->integer('current_age_days')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['farm_id', 'status', 'start_date'], 'idx_flocks_farm_status_date');
        });

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE flocks
            ADD CONSTRAINT chk_flocks_initial_count
            CHECK (initial_count > 0)
        ");
        }

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE flocks
            ADD CONSTRAINT chk_flocks_status
            CHECK (status IN ('draft', 'active', 'closed', 'cancelled'))
        ");
        }

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE flocks
            ADD CONSTRAINT chk_flocks_close_date
            CHECK (close_date IS NULL OR close_date >= start_date)
        ");
        }

        // القيد الأهم: فوج نشط واحد فقط لكل مدجنة — partial unique index
        DB::statement("
            CREATE UNIQUE INDEX uidx_flocks_one_active_per_farm
            ON flocks (farm_id)
            WHERE status = 'active'
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('flocks');
    }
};

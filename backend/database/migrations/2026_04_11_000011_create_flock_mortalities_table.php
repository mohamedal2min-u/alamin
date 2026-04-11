<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flock_mortalities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->restrictOnDelete();
            $table->foreignId('flock_id')->constrained('flocks')->restrictOnDelete();
            $table->date('entry_date');
            $table->integer('quantity');
            $table->string('reason', 190)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('worker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('editable_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['flock_id', 'entry_date'], 'idx_mortalities_flock_date');
        });

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE flock_mortalities
            ADD CONSTRAINT chk_mortality_quantity
            CHECK (quantity > 0)
        ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flock_mortalities');
    }
};

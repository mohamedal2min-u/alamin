<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flock_water_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->restrictOnDelete();
            $table->foreignId('flock_id')->constrained('flocks')->restrictOnDelete();
            $table->date('entry_date');
            $table->decimal('quantity', 14, 3)->nullable();
            $table->string('unit_label', 50)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('worker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('editable_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['flock_id', 'entry_date'], 'idx_water_logs_flock_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flock_water_logs');
    }
};

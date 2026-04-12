<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('flock_temperature_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flock_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->enum('time_of_day', ['morning', 'afternoon', 'evening']);
            $table->decimal('temperature', 5, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // A flock should have at most one log per time of day per day
            $table->unique(['flock_id', 'log_date', 'time_of_day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flock_temperature_logs');
    }
};

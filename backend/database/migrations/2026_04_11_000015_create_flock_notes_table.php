<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flock_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->restrictOnDelete();
            $table->foreignId('flock_id')->constrained('flocks')->restrictOnDelete();
            $table->string('note_type', 50)->default('general');
            $table->text('note_text');
            $table->date('entry_date')->nullable();
            $table->foreignId('worker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['flock_id', 'entry_date'], 'idx_flock_notes_flock_date');
        });

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE flock_notes
            ADD CONSTRAINT chk_flock_note_type
            CHECK (note_type IN ('general', 'instruction', 'operational', 'alert'))
        ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flock_notes');
    }
};

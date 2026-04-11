<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 190);
            $table->string('location')->nullable();
            $table->string('status', 30)->default('pending_setup');
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('started_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE farms
            ADD CONSTRAINT chk_farms_status
            CHECK (status IN ('pending_setup', 'active', 'suspended'))
        ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('farms');
    }
};

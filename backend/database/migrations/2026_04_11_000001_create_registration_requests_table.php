<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 190)->nullable();
            $table->string('whatsapp', 30);
            $table->string('password_hash');
            $table->string('location')->nullable();
            $table->string('farm_name', 190)->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index('status', 'idx_reg_requests_status');
        });

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE registration_requests
            ADD CONSTRAINT chk_reg_status
            CHECK (status IN ('pending', 'approved', 'rejected'))
        ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_requests');
    }
};

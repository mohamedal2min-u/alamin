<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // مستندات الفوج: صور أوراق الحساب والفواتير وأوراق القبان
        Schema::create('flock_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->foreignId('flock_id')->constrained('flocks')->cascadeOnDelete();
            $table->string('title', 190)->nullable();
            $table->string('path');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['farm_id', 'flock_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flock_documents');
    }
};

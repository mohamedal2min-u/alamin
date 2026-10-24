<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // سجل النشاطات للإدخالات اليومية — للإضافة فقط (إنشاء/تعديل/إلغاء مع القيم قبل وبعد)
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('flock_id')->nullable()->constrained('flocks')->nullOnDelete();
            $table->string('action', 20);                 // created | updated | voided
            $table->string('subject_type', 40);           // mortality | feed_log | medicine_log | water_log | note | temperature_log
            $table->unsignedBigInteger('subject_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['farm_id', 'id']);
            $table->index(['farm_id', 'flock_id']);
            $table->index('user_id');
            $table->index('flock_id');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};

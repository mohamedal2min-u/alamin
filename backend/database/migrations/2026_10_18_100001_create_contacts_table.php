<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // الموردون (علف، دواء، كتاكيت، صهريج ماء) والتجار المشترون — لكل مزرعة
        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->string('name', 190);
            $table->string('type', 20)->default('supplier'); // supplier | buyer | both
            $table->string('phone', 50)->nullable();
            $table->string('whatsapp', 50)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['farm_id', 'name']);
            $table->index(['farm_id', 'type', 'is_active']);
        });

        // ربط اختياري بالسجلات المالية؛ الحقول النصية القديمة (buyer_name / supplier_name) تبقى كما هي
        foreach (['expenses', 'inventory_transactions', 'sales', 'flock_water_logs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
                $table->index(['farm_id', 'contact_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['expenses', 'inventory_transactions', 'sales', 'flock_water_logs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropIndex(['farm_id', 'contact_id']);
                $table->dropConstrainedForeignId('contact_id');
            });
        }

        Schema::dropIfExists('contacts');
    }
};

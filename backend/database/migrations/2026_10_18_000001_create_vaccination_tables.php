<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // برنامج اللقاحات لكل مزرعة (قالب قابل للتعديل من مدير المزرعة)
        Schema::create('vaccine_schedule_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_example')->default(false); // القالب الافتراضي «مثال» حتى يعدّله المدير
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['farm_id', 'is_active']);
        });

        Schema::create('vaccine_schedule_template_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('vaccine_schedule_templates')->cascadeOnDelete();
            $table->unsignedSmallInteger('age_day');
            $table->string('title', 150);
            $table->string('method', 20); // drinking_water | spray | eye_drop | injection
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('withdrawal_days')->nullable();
            $table->timestamps();

            $table->index(['template_id', 'age_day']);
        });

        // لقاحات الفوج المولَّدة من القالب عند تفعيله
        Schema::create('flock_vaccinations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->foreignId('flock_id')->constrained('flocks')->cascadeOnDelete();
            $table->unsignedSmallInteger('age_day');
            $table->date('due_date');
            $table->string('title', 150);
            $table->string('method', 20);
            $table->text('notes')->nullable();            // ملاحظات القالب
            $table->string('status', 10)->default('due'); // due | done | skipped
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 1000)->nullable();     // ملاحظة عند التنفيذ/التخطي
            $table->unsignedSmallInteger('withdrawal_days')->nullable();
            $table->timestamps();

            $table->index(['flock_id', 'due_date']);
            $table->index(['farm_id', 'status', 'due_date']);
            $table->index('done_by');
        });

        // فترة سحب اختيارية لأصناف الأدوية (أيام)
        Schema::table('items', function (Blueprint $table): void {
            $table->unsignedSmallInteger('withdrawal_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            $table->dropColumn('withdrawal_days');
        });
        Schema::dropIfExists('flock_vaccinations');
        Schema::dropIfExists('vaccine_schedule_template_items');
        Schema::dropIfExists('vaccine_schedule_templates');
    }
};

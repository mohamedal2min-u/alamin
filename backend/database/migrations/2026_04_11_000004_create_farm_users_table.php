<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // الدور محفوظ في Spatie مع farm_id كـ team context — هذا الجدول للعضوية فقط
            $table->string('status', 30)->default('active');
            $table->boolean('is_primary')->default(false);
            $table->timestampTz('joined_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            // unique index يعمل أصلاً كـ regular index — لا حاجة لفهرس إضافي
            $table->unique(['farm_id', 'user_id'], 'uidx_farm_users_farm_user');
        });

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE farm_users
            ADD CONSTRAINT chk_farm_users_status
            CHECK (status IN ('active', 'inactive'))
        ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_users');
    }
};

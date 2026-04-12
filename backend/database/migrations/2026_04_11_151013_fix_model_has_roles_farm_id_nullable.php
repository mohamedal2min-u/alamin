<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إصلاح جدول model_has_roles ليدعم super_admin كدور عالمي (farm_id = null).
     *
     * المشكلة: الجدول أُنشئ على PostgreSQL بـ PRIMARY KEY يشمل farm_id
     * مما يمنع القيم الـ null. في SQLite (اختبارات) المايجريشن الأصلي
     * لـ Spatie ينشئ الجدول بشكل صحيح بالفعل، لذا لا حاجة لتدخل.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE model_has_roles DROP CONSTRAINT model_has_roles_pkey');
        DB::statement('ALTER TABLE model_has_roles ALTER COLUMN farm_id DROP NOT NULL');
        DB::statement('ALTER TABLE model_has_roles ADD CONSTRAINT model_has_roles_role_model_type_primary PRIMARY KEY (role_id, model_id, model_type)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE model_has_roles DROP CONSTRAINT model_has_roles_role_model_type_primary');
        DB::statement('ALTER TABLE model_has_roles ALTER COLUMN farm_id SET NOT NULL');
        DB::statement('ALTER TABLE model_has_roles ADD CONSTRAINT model_has_roles_pkey PRIMARY KEY (farm_id, role_id, model_id, model_type)');
    }
};

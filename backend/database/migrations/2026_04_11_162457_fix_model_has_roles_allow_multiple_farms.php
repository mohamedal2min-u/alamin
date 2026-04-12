<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Drop the old primary key that prevents users from having the same role across multiple farms
            DB::statement('ALTER TABLE model_has_roles DROP CONSTRAINT IF EXISTS model_has_roles_role_model_type_primary');
            
            // Add a unique constraint that considers farm_id. 
            // In PostgreSQL 15+, NULLS NOT DISTINCT treats NULLs as equal, 
            // so a user can't have the same global role (farm_id=NULL) multiple times.
            DB::statement('ALTER TABLE model_has_roles ADD CONSTRAINT model_has_roles_unique UNIQUE NULLS NOT DISTINCT (farm_id, role_id, model_id, model_type)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE model_has_roles DROP CONSTRAINT IF EXISTS model_has_roles_unique');
            DB::statement('ALTER TABLE model_has_roles ADD CONSTRAINT model_has_roles_role_model_type_primary PRIMARY KEY (role_id, model_id, model_type)');
        }
    }
};

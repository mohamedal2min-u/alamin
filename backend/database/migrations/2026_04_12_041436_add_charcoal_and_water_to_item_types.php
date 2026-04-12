<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('item_types')->insert([
            ['name' => 'فحم', 'code' => 'charcoal', 'is_system' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'ماء', 'code' => 'water', 'is_system' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('item_types')->whereIn('code', ['charcoal', 'water'])->delete();
    }
};

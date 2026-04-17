<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('flocks', function (Blueprint $table) {
            $table->decimal('chick_unit_price', 10, 2)->nullable()->after('initial_count');
            $table->decimal('total_chick_cost', 12, 2)->nullable()->after('chick_unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('flocks', function (Blueprint $table) {
            $table->dropColumn(['chick_unit_price', 'total_chick_cost']);
        });
    }
};

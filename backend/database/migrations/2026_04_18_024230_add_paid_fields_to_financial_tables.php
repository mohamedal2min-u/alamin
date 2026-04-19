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
            $table->decimal('chick_paid_amount', 12, 2)->default(0)->after('total_chick_cost');
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->decimal('paid_amount', 12, 2)->default(0)->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('flocks', function (Blueprint $table) {
            $table->dropColumn('chick_paid_amount');
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
    }
};

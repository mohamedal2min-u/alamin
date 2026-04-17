<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('flock_water_logs', function (Blueprint $table) {
            $table->decimal('total_amount', 12, 2)->nullable()->after('quantity');
            $table->decimal('paid_amount', 12, 2)->nullable()->after('total_amount');
            $table->string('payment_status', 20)->nullable()->after('paid_amount');
        });

        DB::statement("ALTER TABLE flock_water_logs ADD CONSTRAINT flock_water_logs_payment_status_check CHECK (payment_status IN ('paid', 'partial', 'unpaid') OR payment_status IS NULL)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE flock_water_logs DROP CONSTRAINT IF EXISTS flock_water_logs_payment_status_check");

        Schema::table('flock_water_logs', function (Blueprint $table) {
            $table->dropColumn(['total_amount', 'paid_amount', 'payment_status']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Drop old CHECK constraint
        DB::statement('ALTER TABLE expenses DROP CONSTRAINT IF EXISTS chk_expenses_payment_status');

        // Add new CHECK constraint with 'unpaid' replacing 'debt'
        DB::statement("
            ALTER TABLE expenses
            ADD CONSTRAINT chk_expenses_payment_status
            CHECK (payment_status IN ('paid', 'partial', 'unpaid'))
        ");

        // Update existing 'debt' rows to 'unpaid'
        DB::table('expenses')
            ->where('payment_status', 'debt')
            ->update(['payment_status' => 'unpaid']);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE expenses DROP CONSTRAINT IF EXISTS chk_expenses_payment_status');
        DB::statement("
            ALTER TABLE expenses
            ADD CONSTRAINT chk_expenses_payment_status
            CHECK (payment_status IN ('paid', 'partial', 'debt'))
        ");

        DB::table('expenses')
            ->where('payment_status', 'unpaid')
            ->update(['payment_status' => 'debt']);
    }
};

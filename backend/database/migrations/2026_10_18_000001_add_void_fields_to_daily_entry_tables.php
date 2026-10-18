<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** جداول الإدخالات اليومية التي تُعدَّل/تُلغى (إلغاء ناعم مع سبب) */
    private const TABLES = [
        'flock_mortalities',
        'flock_feed_logs',
        'flock_medicines',
        'flock_water_logs',
        'flock_notes',
        'flock_temperature_logs',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'deleted_at')) {
                    $table->softDeletes();
                }
                $table->text('void_reason')->nullable();
                $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->index('voided_by');
            });
        }

        // شراء النقص التلقائي الذي أنشأه سجل العلف (لعكسه عند الإلغاء/التعديل)
        Schema::table('flock_feed_logs', function (Blueprint $table): void {
            $table->foreignId('auto_purchase_transaction_id')->nullable()
                ->constrained('inventory_transactions')->nullOnDelete();
            $table->index('auto_purchase_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('flock_feed_logs', function (Blueprint $table): void {
            $table->dropForeign(['auto_purchase_transaction_id']);
            $table->dropIndex(['auto_purchase_transaction_id']);
            $table->dropColumn('auto_purchase_transaction_id');
        });

        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropForeign(['voided_by']);
                $table->dropIndex(['voided_by']);
                $table->dropColumn(['void_reason', 'voided_by']);
                if ($tableName !== 'flock_temperature_logs') {
                    $table->dropSoftDeletes();
                }
            });
        }
    }
};

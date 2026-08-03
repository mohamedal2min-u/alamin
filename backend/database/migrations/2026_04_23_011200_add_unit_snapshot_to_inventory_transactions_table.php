<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot the item's unit labels onto each transaction at write time. Previously these were
     * always read live from the related Item, so editing an item's unit later silently relabeled
     * every historical transaction as if it had always used the new unit. Nullable + no backfill:
     * existing rows keep falling back to the live item (see InventoryController), new rows are
     * self-contained.
     */
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->string('input_unit', 50)->nullable()->after('unit_price');
            $table->string('content_unit', 50)->nullable()->after('input_unit');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropColumn(['input_unit', 'content_unit']);
        });
    }
};

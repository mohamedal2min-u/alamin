<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix average_cost so it represents cost per BASE unit (kg, ml, etc.)
     * rather than cost per purchase unit (bag, box, etc.).
     *
     * average_cost was previously stored as unit_price (per bag),
     * but current_quantity is tracked in base units (kg).
     * Correct formula = weighted average of (unit_price / unit_value) per transaction.
     */
    public function up(): void
    {
        $warehouseItems = DB::table('warehouse_items')
            ->join('items', 'items.id', '=', 'warehouse_items.item_id')
            ->whereNotNull('warehouse_items.average_cost')
            ->select(
                'warehouse_items.id',
                'warehouse_items.farm_id',
                'warehouse_items.item_id',
                'items.unit_value',
            )
            ->get();

        foreach ($warehouseItems as $wi) {
            $unitValue = max((float) $wi->unit_value, 0.000001);

            $txns = DB::table('inventory_transactions')
                ->where('farm_id', $wi->farm_id)
                ->where('item_id', $wi->item_id)
                ->where('direction', 'in')
                ->whereNotNull('unit_price')
                ->orderBy('transaction_date')
                ->select('computed_quantity', 'unit_price')
                ->get();

            if ($txns->isEmpty()) {
                continue;
            }

            $totalQty   = 0.0;
            $totalValue = 0.0;

            foreach ($txns as $txn) {
                $qty         = (float) $txn->computed_quantity;
                $costPerBase = (float) $txn->unit_price / $unitValue;
                $totalValue += $qty * $costPerBase;
                $totalQty  += $qty;
            }

            $correctAvgCost = $totalQty > 0 ? $totalValue / $totalQty : 0;

            DB::table('warehouse_items')
                ->where('id', $wi->id)
                ->update(['average_cost' => $correctAvgCost]);
        }
    }

    public function down(): void
    {
        // Cannot reliably reverse — no-op
    }
};



$medicines = \App\Models\FlockMedicine::whereNull('inventory_transaction_id')->with('item')->get();
$feeds = \App\Models\FlockFeedLog::whereNull('inventory_transaction_id')->with('item')->get();

$totalSynced = 0;

\Illuminate\Support\Facades\DB::transaction(function () use ($medicines, $feeds, &$totalSynced) {
    $processLog = function($log, $sourceModule) use (&$totalSynced) {
        $item = $log->item;
        if (!$item) return;
        
        $realQty = $log->quantity * ($item->unit_value ?? 1);
        
        // Find active warehouse for the farm
        $warehouse = \App\Models\Warehouse::where('farm_id', $log->farm_id)->where('is_active', true)->first();
        if (!$warehouse) return;

        $warehouseItem = \App\Models\WarehouseItem::firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'item_id' => $item->id],
            ['farm_id' => $log->farm_id, 'current_quantity' => 0, 'average_cost' => $item->default_cost ?? 0, 'created_by' => $log->created_by, 'updated_by' => $log->updated_by]
        );

        $avgCost = (float)($warehouseItem->average_cost ?? 0);
        $totalCost = $avgCost * $realQty;

        // 1. Create ADDITION transaction (Simulate the worker adding it to the warehouse)
        \App\Models\InventoryTransaction::create([
            'farm_id'           => $log->farm_id,
            'warehouse_id'      => $warehouse->id,
            'item_id'           => $item->id,
            'transaction_date'  => $log->entry_date ?? now()->toDateString(),
            'transaction_type'  => 'purchase',
            'direction'         => 'in',
            'source_module'     => 'manual_sync', // Just a marker
            'original_quantity' => $log->quantity,
            'computed_quantity' => $realQty,
            'unit_price'        => $avgCost > 0 ? $avgCost : null,
            'total_amount'      => $totalCost > 0 ? $totalCost : null,
            'created_by'        => $log->created_by,
            'updated_by'        => $log->updated_by,
        ]);
        
        // Increment warehouse item
        $warehouseItem->increment('current_quantity', $realQty);

        // 2. Create CONSUMPTION transaction (The actual log withdrawal)
        $outTxn = \App\Models\InventoryTransaction::create([
            'farm_id'           => $log->farm_id,
            'warehouse_id'      => $warehouse->id,
            'item_id'           => $item->id,
            'flock_id'          => $log->flock_id,
            'transaction_date'  => $log->entry_date ?? now()->toDateString(),
            'transaction_type'  => 'consumption',
            'direction'         => 'out',
            'source_module'     => $sourceModule,
            'original_quantity' => $log->quantity,
            'computed_quantity' => $realQty,
            'unit_price'        => $avgCost > 0 ? $avgCost : null,
            'total_amount'      => $totalCost > 0 ? $totalCost : null,
            'created_by'        => $log->created_by,
            'updated_by'        => $log->updated_by,
        ]);
        
        // Decrement warehouse item
        $warehouseItem->decrement('current_quantity', $realQty);

        // 3. Link the log to the OUT transaction
        $log->inventory_transaction_id = $outTxn->id;
        $log->save();

        $totalSynced++;
    };

    foreach ($medicines as $med) {
        $processLog($med, 'flock_medicine');
    }
    
    foreach ($feeds as $feed) {
        $processLog($feed, 'flock_feed');
    }
});

echo "Total records synchronized: " . $totalSynced . "\n";

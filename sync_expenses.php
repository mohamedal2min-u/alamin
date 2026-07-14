
$transactions = \App\Models\InventoryTransaction::where('source_module', 'manual_sync')
    ->where('transaction_type', 'purchase')
    ->get();

$totalExpenses = 0;

\Illuminate\Support\Facades\DB::transaction(function () use ($transactions, &$totalExpenses) {
    foreach ($transactions as $txn) {
        // Find the linked out transaction to get the flock_id
        $outTxn = \App\Models\InventoryTransaction::where('source_module', 'like', 'flock_%')
            ->where('transaction_type', 'consumption')
            ->where('item_id', $txn->item_id)
            ->where('farm_id', $txn->farm_id)
            ->whereDate('transaction_date', $txn->transaction_date)
            ->where('created_at', '>=', now()->subMinutes(120)) // roughly matching the ones created recently
            ->first();
            
        // Actually, we can just get the flock_id from the latest flock if not found
        $flockId = $outTxn ? $outTxn->flock_id : \App\Models\Flock::where('farm_id', $txn->farm_id)->where('status', 'active')->value('id');

        // Check if an expense already exists to avoid duplicates
        $exists = \App\Models\Expense::where('linked_inventory_transaction_id', $txn->id)->exists();
        if ($exists) continue;
        
        $item = \App\Models\Item::find($txn->item_id);

        \App\Models\Expense::create([
            'farm_id' => $txn->farm_id,
            'flock_id' => $flockId,
            'expense_category_id' => 17, // شراء مخزون
            'entry_date' => $txn->transaction_date,
            'expense_type' => 'flock',
            'quantity' => $txn->computed_quantity,
            'unit_price' => $txn->unit_price ?? 0,
            'total_amount' => $txn->total_amount ?? 0,
            'paid_amount' => 0,
            'remaining_amount' => $txn->total_amount ?? 0,
            'payment_status' => 'unpaid',
            'reference_no' => null,
            'description' => 'دين شراء تلقائي: ' . ($item ? $item->name : 'صنف غير معروف'),
            'linked_inventory_transaction_id' => $txn->id,
            'worker_id' => $txn->created_by,
            'created_by' => $txn->created_by,
            'updated_by' => $txn->updated_by,
        ]);
        $totalExpenses++;
    }
});

echo "Total expenses created: " . $totalExpenses . "\n";

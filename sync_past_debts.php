

$activeFlocks = Flock::where('status', 'active')->pluck('id');

$consumptions = InventoryTransaction::whereIn('flock_id', $activeFlocks)
    ->where('transaction_type', 'consumption')
    ->where(function($q) {
        $q->whereNull('unit_price')->orWhere('unit_price', '<=', 0);
    })
    ->get();

$count = 0;
foreach ($consumptions as $txn) {
    // Check if an expense already exists for this item and date
    // Note: since auto-purchase links to the purchase transaction, not the consumption transaction
    // we need to be careful. Auto-purchase logic creates an Expense linked to the purchase transaction.
    // The consumption transaction has no explicit link.
    // Let's check if there is an expense on the same day for the same flock, with "دين شراء تلقائي" and same item.
    
    $item = $txn->item;
    if (!$item) continue;
    
    $expenseExists = Expense::where('flock_id', $txn->flock_id)
        ->where('entry_date', $txn->transaction_date)
        ->where('description', 'like', '%: ' . $item->name . '%')
        ->exists();

    if (!$expenseExists) {
        Expense::create([
            'farm_id' => $txn->farm_id,
            'flock_id' => $txn->flock_id,
            'expense_category_id' => 17, // شراء مخزون
            'entry_date' => $txn->transaction_date,
            'expense_type' => 'flock',
            'quantity' => $txn->original_quantity,
            'unit_price' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'payment_status' => 'unpaid',
            'description' => 'دين شراء قديم: ' . $item->name,
            'worker_id' => $txn->created_by,
            'created_by' => $txn->created_by,
            'updated_by' => $txn->updated_by,
        ]);
        $count++;
    }
}

echo "Created $count missing expenses for past consumptions.\n";


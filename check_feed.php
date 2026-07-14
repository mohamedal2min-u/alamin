
$logs = \App\Models\FlockFeedLog::with('item')->orderBy('id', 'desc')->take(5)->get();
foreach($logs as $l) {
    echo "ID: " . $l->id . " | Item: " . $l->item->name . " | Qty: " . $l->quantity . " | TXN: " . ($l->inventory_transaction_id ?: 'null') . "\n";
}


$medicines = \App\Models\FlockMedicine::where('entry_date', '2026-07-14')->with('item')->get();
foreach ($medicines as $med) {
    echo "ID: {$med->id} | Item: {$med->item->name} | Qty: {$med->quantity}\n";
}


import paramiko

HOST = '82.29.181.61'
USER = 'alamin-api'
PASS = 'a550055A!'
PATH = '/home/alamin-api/app/backend'

php_script = """
$medicines = \\App\\Models\\FlockMedicine::where('entry_date', '2026-07-14')
    ->with('item')
    ->get();

$duplicates = $medicines->groupBy('item_id')->map(function ($group) {
    // Keep the oldest one (first inserted)
    return $group->sortBy('id')->slice(1);
})->flatten();

$count = 0;
foreach ($duplicates as $med) {
    \\Illuminate\\Support\\Facades\\DB::transaction(function() use ($med) {
        if ($med->inventory_transaction_id) {
            $txn = \\App\\Models\\InventoryTransaction::find($med->inventory_transaction_id);
            if ($txn) {
                $whItem = \\App\\Models\\WarehouseItem::where('warehouse_id', $txn->warehouse_id)
                    ->where('item_id', $txn->item_id)
                    ->first();
                if ($whItem) {
                    $whItem->increment('current_quantity', $txn->computed_quantity);
                }
                $txn->delete();
            }
        }
        $med->delete();
    });
    $count++;
    echo "Deleted medicine id " . $med->id . " for item " . $med->item->name . "\\n";
}
echo "Total duplicates deleted: " . $count . "\\n";
"""

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASS, timeout=15)

cmd = f'cd {PATH} && php artisan tinker --execute="{php_script.replace("\"", "\\\"").replace("$", "\\$")}"'
stdin, stdout, stderr = client.exec_command(cmd)

print(stdout.read().decode())
print(stderr.read().decode())

client.close()

import { Client } from 'ssh2'

const HOST = '82.29.181.61'
const PASS = 'a550055A!'

const cmd = `cd /home/alamin-api/app/backend && php artisan tinker --execute="
\\$log = \\App\\Models\\FlockFeedLog::with('item','flock','inventoryTransaction')->find(73);
echo 'log id=73 item_id='.\\$log->item_id.' item='.\\$log->item->name.' qty='.\\$log->quantity.' inv_txn_id='.\\$log->inventory_transaction_id.PHP_EOL;
\\$txn = \\$log->inventoryTransaction;
if (\\$txn) { echo 'txn id='.\\$txn->id.' type='.\\$txn->transaction_type.' dir='.\\$txn->direction.' orig_qty='.\\$txn->original_quantity.' computed_qty='.\\$txn->computed_quantity.' unit_price='.\\$txn->unit_price.PHP_EOL; }
\\$wh = \\App\\Models\\WarehouseItem::where('item_id', \\$log->item_id)->first();
echo 'warehouse item id 32 (stage3) current_qty='.(\\$wh->current_quantity ?? 'N/A').' avg_cost='.(\\$wh->average_cost ?? 'N/A').PHP_EOL;
\\$wh2 = \\App\\Models\\WarehouseItem::where('item_id', 20)->first();
echo 'warehouse item id 20 (stage2) current_qty='.(\\$wh2->current_quantity ?? 'N/A').' avg_cost='.(\\$wh2->average_cost ?? 'N/A').PHP_EOL;
echo '---any auto-purchase expense today for flock---'.PHP_EOL;
\\$exps = \\App\\Models\\Expense::whereDate('created_at','>=', now()->subDay())->where('flock_id', \\$log->flock_id)->get();
foreach(\\$exps as \\$e) { echo 'expense id='.\\$e->id.' desc='.\\$e->description.' total='.\\$e->total_amount.' linked_txn='.\\$e->linked_inventory_transaction_id.PHP_EOL; }
"`

const conn = new Client()
conn.on('ready', () => {
  conn.exec(cmd, (err, stream) => {
    if (err) { console.error(err); conn.end(); return }
    stream.on('data', d => process.stdout.write(d.toString()))
    stream.stderr.on('data', d => process.stderr.write(d.toString()))
    stream.on('close', () => { conn.end() })
  })
}).connect({ host: HOST, port: 22, username: 'alamin-api', password: PASS })

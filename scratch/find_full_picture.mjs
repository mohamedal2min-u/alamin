import { Client } from 'ssh2'

const HOST = '82.29.181.61'
const PASS = 'a550055A!'

const cmd = `cd /home/alamin-api/app/backend && php artisan tinker --execute="
echo '---INVENTORY TXNS last 2 days (items 20,32)---'.PHP_EOL;
\\$txns = \\App\\Models\\InventoryTransaction::whereIn('item_id',[20,32])->whereDate('created_at','>=', now()->subDays(2))->orderBy('id')->get();
foreach(\\$txns as \\$t) { echo 'txn id='.\\$t->id.' item_id='.\\$t->item_id.' type='.\\$t->transaction_type.' dir='.\\$t->direction.' orig_qty='.\\$t->original_quantity.' computed_qty='.\\$t->computed_quantity.' total='.\\$t->total_amount.' created_at='.\\$t->created_at.PHP_EOL; }
echo '---EXPENSES last 2 days flock 3---'.PHP_EOL;
\\$exps = \\App\\Models\\Expense::whereDate('created_at','>=', now()->subDays(2))->where('flock_id',3)->orderBy('id')->get();
foreach(\\$exps as \\$e) { echo 'expense id='.\\$e->id.' desc='.\\$e->description.' qty='.\\$e->quantity.' unit_price='.\\$e->unit_price.' total='.\\$e->total_amount.' linked_txn='.\\$e->linked_inventory_transaction_id.' created_at='.\\$e->created_at.PHP_EOL; }
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

import { Client } from 'ssh2'

const HOST = '82.29.181.61'
const PASS = 'a550055A!'

const cmd = `cd /home/alamin-api/app/backend && php artisan tinker --execute="
echo 'feed_logs referencing item 32: '.\\App\\Models\\FlockFeedLog::where('item_id',32)->count().PHP_EOL;
echo 'inventory_transactions referencing item 32: '.\\App\\Models\\InventoryTransaction::where('item_id',32)->count().PHP_EOL;
echo 'warehouse_items referencing item 32: '.\\App\\Models\\WarehouseItem::where('item_id',32)->count().PHP_EOL;
echo 'flock_medicines referencing item 32: '.\\App\\Models\\FlockMedicine::where('item_id',32)->count().PHP_EOL;
\\$wh = \\App\\Models\\WarehouseItem::where('item_id',32)->get();
foreach(\\$wh as \\$w) { echo 'warehouse_item id='.\\$w->id.' qty='.\\$w->current_quantity.' avg_cost='.\\$w->average_cost.PHP_EOL; }
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

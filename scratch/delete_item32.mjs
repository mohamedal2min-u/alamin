import { Client } from 'ssh2'

const HOST = '82.29.181.61'
const PASS = 'a550055A!'

const cmd = `cd /home/alamin-api/app/backend && php artisan tinker --execute="
use Illuminate\\Support\\Facades\\DB;
DB::transaction(function () {
    \\$item = \\App\\Models\\Item::findOrFail(32);
    echo 'deleting item id=32 name='.\\$item->name.PHP_EOL;

    \\$wh = \\App\\Models\\WarehouseItem::where('item_id', 32)->get();
    foreach (\\$wh as \\$w) {
        if (\\$w->current_quantity != 0) { throw new \\Exception('warehouse_item '.\\$w->id.' has non-zero stock '.\\$w->current_quantity.', aborting'); }
        \\$w->delete();
        echo 'deleted warehouse_item id='.\\$w->id.PHP_EOL;
    }

    \\$item->delete();
    echo 'item 32 deleted'.PHP_EOL;
});
echo 'DONE'.PHP_EOL;
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

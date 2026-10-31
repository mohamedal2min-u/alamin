import { Client } from 'ssh2'

const HOST = '82.29.181.61'
const PASS = 'a550055A!'

const cmd = `cd /home/alamin-api/app/backend && php artisan tinker --execute="
\\$items = \\App\\Models\\Item::all(['id','name','farm_id','item_type_id']);
foreach(\\$items as \\$i) { echo 'item id='.\\$i->id.' name='.\\$i->name.' farm='.\\$i->farm_id.PHP_EOL; }
echo '---LOGS LAST 2 DAYS---'.PHP_EOL;
\\$logs = \\App\\Models\\FlockFeedLog::whereDate('created_at', '>=', now()->subDays(2))->with('item','flock')->get();
foreach(\\$logs as \\$l) {
    echo 'log id='.\\$l->id.' flock='.(\\$l->flock->name ?? \\$l->flock_id).' item_id='.\\$l->item_id.' item_name='.(\\$l->item->name ?? '?').' qty='.\\$l->quantity.' entry_date='.\\$l->entry_date.' created_at='.\\$l->created_at.' notes='.\\$l->notes.PHP_EOL;
}
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

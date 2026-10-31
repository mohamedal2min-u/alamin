import { Client } from 'ssh2'

const HOST = '82.29.181.61'
const PASS = 'a550055A!'

const cmd = `cd /home/alamin-api/app/backend && php artisan tinker --execute="
\\$rows = \\App\\Models\\Expense::with('flock','expenseCategory')
    ->whereHas('flock', fn(\\$q) => \\$q->where('name', 'فوج الصيف'))
    ->whereHas('expenseCategory', fn(\\$q) => \\$q->where('name', 'شراء كتاكيت'))
    ->get();
foreach (\\$rows as \\$r) {
    echo 'id='.\\$r->id.' flock='.\\$r->flock->name.' desc='.\\$r->description.' total='.\\$r->total_amount.' created_at='.\\$r->created_at.PHP_EOL;
}
echo 'count='.\\$rows->count().PHP_EOL;
"`

const conn = new Client()
conn.on('ready', () => {
  console.log('connected')
  conn.exec(cmd, (err, stream) => {
    if (err) { console.error(err); conn.end(); return }
    stream.on('data', d => process.stdout.write(d.toString()))
    stream.stderr.on('data', d => process.stderr.write(d.toString()))
    stream.on('close', () => { conn.end() })
  })
}).connect({ host: HOST, port: 22, username: 'alamin-api', password: PASS })

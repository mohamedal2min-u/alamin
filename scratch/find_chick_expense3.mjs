import { Client } from 'ssh2'

const HOST = '82.29.181.61'
const PASS = 'a550055A!'

const cmd = `cd /home/alamin-api/app/backend && php artisan tinker --execute="
\\$f = \\App\\Models\\Flock::find(3);
\\$expenses = \\App\\Models\\Expense::where('flock_id', 3)
    ->whereHas('expenseCategory', function(\\$q) { \\$q->where('name', 'شراء كتاكيت'); })
    ->get();
echo 'flock 3 chick expenses count: '.count(\\$expenses).PHP_EOL;
foreach(\\$expenses as \\$e) {
    echo 'expense id='.\\$e->id.' qty='.\\$e->quantity.' price='.\\$e->unit_price.' total='.\\$e->total_amount.' created_at='.\\$e->created_at.' updated_at='.\\$e->updated_at.PHP_EOL;
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

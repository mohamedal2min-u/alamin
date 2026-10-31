import { Client } from 'ssh2'

const HOST = '82.29.181.61'
const PASS = 'a550055A!'

const cmd = `cd /home/alamin-api/app/backend && php artisan tinker --execute="
\\$expenses = \\App\\Models\\Expense::whereDate('created_at', '>=', now()->subDays(2))->get();
foreach(\\$expenses as \\$e) {
    echo 'expense id='.\\$e->id.' category='.\\$e->expense_category_id.' flock='.\\$e->flock_id.' qty='.\\$e->quantity.' price='.\\$e->unit_price.' total='.\\$e->total_amount.' desc='.\\$e->description.PHP_EOL;
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

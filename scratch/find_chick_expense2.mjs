import { Client } from 'ssh2'

const HOST = '82.29.181.61'
const PASS = 'a550055A!'

const cmd = `cd /home/alamin-api/app/backend && php artisan tinker --execute="
\\$e = \\App\\Models\\Expense::with('flock')->find(1);
echo 'expense: qty='.\\$e->quantity.' unit_price='.\\$e->unit_price.' total='.\\$e->total_amount.' created_at='.\\$e->created_at.' updated_at='.\\$e->updated_at.PHP_EOL;
\\$f = \\$e->flock;
echo 'flock: id='.\\$f->id.' created_at='.\\$f->created_at.' updated_at='.\\$f->updated_at.' chick_unit_price='.var_export(\\$f->chick_unit_price,true).' total_chick_cost='.var_export(\\$f->total_chick_cost,true).' initial_count='.\\$f->initial_count.PHP_EOL;
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

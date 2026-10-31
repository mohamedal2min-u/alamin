import { Client } from 'ssh2'

const HOST = '82.29.181.61'
const PASS = 'a550055A!'

const cmd = `cd /home/alamin-api/app/backend && php artisan tinker --execute="
\\$flock = \\App\\Models\\Flock::find(3);
\\$userId = \\$flock->created_by;
\\$action = new \\App\\Actions\\Flock\\UpdateFlockAction();
\\$action->execute(\\$flock, \\$userId, [
    'chick_unit_price' => 0.5,
    'initial_count' => 7500
]);
echo 'Successfully updated flock and synced expense!'.PHP_EOL;
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

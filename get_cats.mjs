import { Client } from 'ssh2';

const conn = new Client();
conn.on('ready', () => {
  conn.exec('cd /home/alamin-api/app/backend && php artisan tinker --execute="echo json_encode(\\App\\Models\\ExpenseCategory::all()->toArray());"', (err, stream) => {
    if (err) throw err;
    stream.on('data', d => console.log(d.toString()));
    stream.on('close', () => conn.end());
  });
}).connect({host: '82.29.181.61', port: 22, username: 'alamin-api', password: 'a550055A!'});

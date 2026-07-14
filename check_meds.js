const { Client } = require('ssh2'); 
const conn = new Client(); 
conn.on('ready', () => { 
  conn.exec(`cd /home/alamin-api/app/backend && php artisan tinker --execute="\\$meds = \\App\\Models\\FlockMedicine::where('entry_date', '2026-07-14')->with('item')->get(); foreach(\\$meds as \\$m) { echo \\$m->id . ' - ' . \\$m->item->name . ' - ' . \\$m->quantity . \"\\n\"; }"`, (err, stream) => { 
    stream.on('data', d => process.stdout.write(d)); 
    stream.on('close', () => conn.end()); 
  }); 
}).connect({host: '82.29.181.61', port: 22, username: 'alamin-api', password: 'a550055A!'});

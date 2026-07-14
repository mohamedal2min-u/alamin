import { Client } from 'ssh2';
import fs from 'fs';
import { join } from 'path';

const conn = new Client();
conn.on('ready', () => {
  conn.sftp((err, sftp) => {
    if (err) throw err;
    const sourceFile = join(process.cwd(), 'delete_old_debts.php');
    const targetFile = '/tmp/delete_old_debts.php';
    sftp.fastPut(sourceFile, targetFile, (err) => {
      if (err) throw err;
      conn.exec(`cd /home/alamin-api/app/backend && php artisan tinker < ${targetFile}`, (err, stream) => {
        if (err) throw err;
        stream.on('data', d => process.stdout.write(d));
        stream.stderr.on('data', d => process.stderr.write(d));
        stream.on('close', () => conn.end());
      });
    });
  });
}).connect({host: '82.29.181.61', port: 22, username: 'alamin-api', password: 'a550055A!'});

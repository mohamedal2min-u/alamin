import { Client } from 'ssh2';

const HOST = '82.29.181.61';
const PASS = 'a550055A!';

const CMDS = [
  'echo "--- PM2 STATUS ---"',
  'pm2 status',
  'echo "--- PM2 LOGS (alamin-frontend) ---"',
  'pm2 logs alamin-frontend --lines 50 --nostream',
  'echo "--- DISK SPACE (maa) ---"',
  'df -h',
  'echo "--- NGINX ERRORS (maa) ---"',
  'tail -n 50 /home/maa/logs/nginx/error.log || tail -n 50 /var/log/nginx/error.log'
];

async function runDebug() {
  const conn = new Client();
  conn.on('ready', () => {
    console.log('Connected to server (user: maa) for diagnostics...');
    let i = 0;
    function next() {
      if (i >= CMDS.length) { conn.end(); return; }
      const cmd = CMDS[i++];
      console.log(`\nExecuting: ${cmd}`);
      conn.exec(cmd, (err, stream) => {
        if (err) { console.error(err); next(); return; }
        stream.on('data', d => process.stdout.write(d.toString()));
        stream.stderr.on('data', d => process.stderr.write(d.toString()));
        stream.on('close', () => next());
      });
    }
    next();
  }).on('error', (err) => {
    console.error('Connection error:', err);
  }).connect({ host: HOST, port: 22, username: 'maa', password: PASS });
}

runDebug();

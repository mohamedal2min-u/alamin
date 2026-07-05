import { Client } from 'ssh2';

const HOST = '82.29.181.61';
const PASS = 'a550055A!';

const CMDS = [
  'echo "--- TESTING FRONTEND (localhost:3000) ---"',
  'curl -I http://127.0.0.1:3000',
  'echo "--- TESTING BACKEND (api.alamin.se) ---"',
  'curl -I https://api.alamin.se'
];

async function runDebug() {
  const conn = new Client();
  conn.on('ready', () => {
    console.log('Connected to server for final verification...');
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
  }).connect({ host: HOST, port: 22, username: 'alamin-api', password: PASS });
}

runDebug();

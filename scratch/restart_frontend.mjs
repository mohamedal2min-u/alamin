import { Client } from 'ssh2';

const HOST = '82.29.181.61';
const PASS = 'a550055A!';

const CMDS = [
  'echo "--- PROJECT DIRECTORY ---"',
  'ls -la /home/maa/app/frontend',
  'echo "--- CHECKING PORT IN .ENV ---"',
  'grep "PORT" /home/maa/app/frontend/.env || echo "No PORT in .env"',
  'echo "--- STOPPING WRONG PM2 PROCESS ---"',
  'pm2 delete alamin-frontend',
  'echo "--- STARTING PROPERLY FROM PROJECT DIR ---"',
  'cd /home/maa/app/frontend && pm2 start npm --name "alamin-frontend" -- run start',
  'echo "--- WAITING AND CHECKING PORTS AGAIN ---"',
  'sleep 5 && ss -plnt | grep -E "3000|13000"'
];

async function runDebug() {
  const conn = new Client();
  conn.on('ready', () => {
    console.log('Connected to server (user: maa) to fix frontend startup...');
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

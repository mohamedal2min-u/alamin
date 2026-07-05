import { Client } from 'ssh2';

const HOST = '82.29.181.61';
const PASS = 'a550055A!';

const CMDS = [
  'echo "--- EXECUTING STARTUP COMMAND ---"',
  `echo "${PASS}" | sudo -S env PATH=$PATH:/usr/bin /usr/lib/node_modules/pm2/bin/pm2 startup systemd -u maa --hp /home/maa`
];

async function runSudoStartup() {
  const conn = new Client();
  conn.on('ready', () => {
    console.log('Connected to server to run sudo startup...');
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

runSudoStartup();

import { Client } from 'ssh2'
import fs from 'fs'

const deployContent = fs.readFileSync('deploy.mjs', 'utf8')
const hostMatch = deployContent.match(/const HOST = ['"]([^'"]+)['"]/)
const passMatch = deployContent.match(/const PASS = ['"]([^'"]+)['"]/)

const HOST = hostMatch ? hostMatch[1] : ''
const PASS = passMatch ? passMatch[1] : ''

const commands = [
  'pm2 list',
  'echo "=== CURL TEST 127.0.0.1 ==="',
  'curl -I -s http://127.0.0.1:3000/login | head -n 5 || echo "curl 127.0.0.1 failed"',
  'echo "=== CURL TEST localhost ==="',
  'curl -I -s http://localhost:3000/login | head -n 5 || echo "curl localhost failed"',
  'echo "=== CURL HTTP CODE ==="',
  'curl -s -o /dev/null -w "%{http_code}\\n" http://127.0.0.1:3000/login',
  'echo "=== LATEST PM2 LOGS ==="',
  'pm2 logs alamin-frontend --lines 15 --nostream'
]

const conn = new Client()
conn.on('ready', () => {
  const fullCmd = commands.join(' && ')
  conn.exec(fullCmd, (err, stream) => {
    if (err) { console.error(err); conn.end(); return }
    stream.on('data', d => process.stdout.write(d.toString()))
    stream.stderr.on('data', d => process.stderr.write(d.toString()))
    stream.on('close', () => conn.end())
  })
}).connect({ host: HOST, port: 22, username: 'maa', password: PASS })

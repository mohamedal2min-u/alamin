import { execSync } from 'child_process'

// Install ssh2 if needed
try { await import('ssh2') } catch {
  console.log('Installing ssh2...')
  execSync('npm install ssh2', { stdio: 'inherit' })
}

const { Client } = await import('ssh2')

const HOST = '82.29.181.61'
const USER = 'alamin-api'
const PASS = 'a550055A!'
const GIT_PATH = '/home/alamin-api/app'
const APP_PATH = '/home/alamin-api/app/backend'

const COMMANDS = [
  `cd ${GIT_PATH} && git pull origin main`,
  `cd ${APP_PATH} && php artisan config:cache`,
  `cd ${APP_PATH} && php artisan route:cache`,
  `cd ${APP_PATH} && php artisan migrate --force`,
  `cd ${APP_PATH} && php artisan optimize`,
]

const conn = new Client()

conn.on('ready', () => {
  console.log('Connected!\n')
  runNext(0)

  function runNext(i) {
    if (i >= COMMANDS.length) { conn.end(); console.log('\n✅ Done!'); return }
    const cmd = COMMANDS[i]
    console.log(`>>> ${cmd}`)
    conn.exec(cmd, (err, stream) => {
      if (err) { console.error(err); runNext(i+1); return }
      stream.on('data', d => process.stdout.write(d.toString()))
      stream.stderr.on('data', d => process.stderr.write(d.toString()))
      stream.on('close', () => { console.log(); runNext(i+1) })
    })
  }
}).connect({ host: HOST, port: 22, username: USER, password: PASS })

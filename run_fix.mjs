import { execSync } from 'child_process'

const { Client } = await import('ssh2')

const HOST = process.env.DEPLOY_HOST
const PASS = process.env.DEPLOY_PASSWORD

const BACKEND_CMDS = [
  'cd /home/alamin-api/app && git fetch origin && git reset --hard origin/main',
  'cd /home/alamin-api/app/backend && php artisan config:cache',
  'cd /home/alamin-api/app/backend && php artisan route:cache',
  'cd /home/alamin-api/app/backend && php fix_categories.php',
  'cd /home/alamin-api/app/backend && php artisan app:fix-debts',
]

function runSSH(user, commands) {
  return new Promise((resolve) => {
    const conn = new Client()
    conn.on('ready', () => {
      console.log(`\n🔗 Connected as ${user}\n`)
      let i = 0
      function next() {
        if (i >= commands.length) { conn.end(); resolve(); return }
        const cmd = commands[i++]
        console.log(`>>> ${cmd}`)
        conn.exec(cmd, (err, stream) => {
          if (err) { console.error(err); next(); return }
          stream.on('data', d => process.stdout.write(d.toString()))
          stream.stderr.on('data', d => process.stderr.write(d.toString()))
          stream.on('close', () => { console.log(); next() })
        })
      }
      next()
    }).connect({ host: HOST, port: 22, username: user, password: PASS })
  })
}

console.log('🔧 Running fixes on Backend...')
await runSSH('alamin-api', BACKEND_CMDS)
console.log('✅ Backend fixes done!')

import { execSync } from 'child_process'

// Install ssh2 if needed
try { await import('ssh2') } catch {
  console.log('Installing ssh2...')
  execSync('npm install ssh2', { stdio: 'inherit' })
}

const { Client } = await import('ssh2')

const HOST = '82.29.181.61'
const PASS = 'a550055A!'

const BACKEND_CMDS = [
  'cd /home/alamin-api/app && git pull origin main',
  'cd /home/alamin-api/app/backend && php artisan config:cache',
  'cd /home/alamin-api/app/backend && php artisan route:cache',
  'cd /home/alamin-api/app/backend && php artisan migrate --force',
  'cd /home/alamin-api/app/backend && php artisan optimize',
]

const FRONTEND_CMDS = [
  'cd /home/maa/app && git pull origin main',
  'cd /home/maa/app/frontend && npm install --production=false',
  'cd /home/maa/app/frontend && npm run build',
  'pm2 restart alamin-frontend',
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

const mode = process.argv[2] // 'backend', 'frontend', or undefined (both)

if (!mode || mode === 'backend') {
  console.log('🔧 Deploying Backend...')
  await runSSH('alamin-api', BACKEND_CMDS)
  console.log('✅ Backend done!')
}

if (!mode || mode === 'frontend') {
  console.log('\n🎨 Deploying Frontend...')
  await runSSH('maa', FRONTEND_CMDS)
  console.log('✅ Frontend done!')
}

console.log('\n🚀 All done!')

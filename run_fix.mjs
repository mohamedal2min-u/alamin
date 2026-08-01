import { Client } from 'ssh2'

const HOST = '82.29.181.61'
const PASS = 'a550055A!'

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

await runSSH('alamin-api', [
  'cd /home/alamin-api/app/backend && php artisan app:fix-debts'
])
console.log('Done running fix debts on server')

import { execSync } from 'child_process'

const { Client } = await import('ssh2')

const HOST = '82.29.181.61'
const PASS = 'a550055A!'

const BACKEND_CMDS = [
  `cd /home/alamin-api/app/backend && php artisan tinker --execute="\\$feedType = App\\Models\\ItemType::where('code', 'feed')->first(); \\$item = App\\Models\\Item::where('name', 'LIKE', '%مرحلة ثانية%')->first(); if (\\$item) { \\$item->item_type_id = \\$feedType->id; \\$item->status = 'active'; \\$item->save(); echo 'Fixed stage 2 feed'; }"`,
]

function runSSH(user, commands) {
  return new Promise((resolve) => {
    const conn = new Client()
    conn.on('ready', () => {
      let i = 0
      function next() {
        if (i >= commands.length) { conn.end(); resolve(); return }
        const cmd = commands[i++]
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

await runSSH('alamin-api', BACKEND_CMDS)

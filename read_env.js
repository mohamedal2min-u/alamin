const { execSync } = require('child_process');
const res = execSync('ssh -o StrictHostKeyChecking=no maa@82.29.181.61 "cat /home/maa/app/frontend/.env.production"');
console.log(res.toString());

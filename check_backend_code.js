const { execSync } = require('child_process');
const res = execSync('ssh -o StrictHostKeyChecking=no alamin-api@82.29.181.61 "cat /home/alamin-api/app/backend/app/Services/ReviewQueueService.php | grep -A 10 category_name"');
console.log(res.toString());

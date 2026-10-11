# نشر السيرفر من GitHub Actions

الـ workflow `Deploy to server` يعمل **يدوياً فقط** (Actions ثم Deploy to server ثم Run workflow).
يطلب كتابة `DEPLOY` للتأكيد، ولا يعمل إلا من فرع `main`. السكربتات الفعلية هنا:

- `backend.sh` (يشغَّل كمستخدم `alamin-api`): `git pull --ff-only` ثم `composer install --no-dev`
  ثم `migrate --force` ثم `optimize` ثم فحص أن نقطة الدخول لا ترجع 500.
- `frontend.sh` (يشغَّل كمستخدم `maa`): `git pull --ff-only` ثم `npm ci && npm run build`
  ثم `pm2 restart`. إن فشل البناء تُسترجع النسخة السابقة ولا تُعاد تشغيل الخدمة.

يتوقف النشر إن كان مستودع السيرفر فيه تعديلات غير مدفوعة.

## الإعداد لمرة واحدة

1. **مفتاحا نشر** (واحد لكل مستخدم) على جهازك:
   ```powershell
   ssh-keygen -t ed25519 -f $HOME\gh_deploy_backend  -N '""' -C github-deploy-backend
   ssh-keygen -t ed25519 -f $HOME\gh_deploy_frontend -N '""' -C github-deploy-frontend
   ```
2. **المفتاح العام** يُضاف في CloudPanel (Settings ثم SSH Keys):
   `gh_deploy_backend.pub` لموقع `api.alamin.se` (مستخدم `alamin-api`)،
   و`gh_deploy_frontend.pub` لموقع `ch.alamin.se` (مستخدم `maa`).
3. **بصمة السيرفر** (على السيرفر): `cat /etc/ssh/ssh_host_ed25519_key.pub`
4. **أسرار GitHub** (Settings ثم Secrets and variables ثم Actions ثم New repository secret):
   | الاسم | القيمة |
   |---|---|
   | `BACKEND_SSH_KEY`  | محتوى الملف الخاص `gh_deploy_backend` كاملاً |
   | `FRONTEND_SSH_KEY` | محتوى الملف الخاص `gh_deploy_frontend` كاملاً |
   | `SERVER_HOST_KEY`  | ناتج الخطوة 3 كاملاً (يبدأ بـ `ssh-ed25519`) |
5. اختياري (موصى به): Settings ثم Environments ثم `production` وأضف نفسك في **Required reviewers**،
   فيطلب GitHub موافقتك قبل أن تبدأ أي مهمة نشر.

## الأمان
- المفاتيح الخاصة تبقى في أسرار GitHub فقط، ويُحذف الملف المؤقت بعد كل تشغيل.
- يتحقق الاتصال من بصمة السيرفر (`StrictHostKeyChecking=yes`) ولا يثق بأي سيرفر آخر.
- لإلغاء الوصول: احذف المفتاح العام من CloudPanel.

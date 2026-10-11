#!/usr/bin/env bash
# يُشغَّل على السيرفر بحساب maa (عبر SSH من GitHub Actions).
# pm2 و node يأتيان من بيئة تسجيل الدخول (قبل set -u لأن ملفات البيئة قد تستعمل متغيرات غير معرّفة)
source ~/.profile 2>/dev/null || true
source ~/.bashrc 2>/dev/null || true
export PATH="$HOME/.local/bin:$HOME/.npm-global/bin:$PATH"

set -euo pipefail

# فحص مبكر قبل تغيير أي شيء
command -v pm2 >/dev/null || { echo "pm2 غير موجود في PATH للمستخدم maa" >&2; exit 1; }
command -v node >/dev/null || { echo "node غير موجود في PATH للمستخدم maa" >&2; exit 1; }

SITE=/home/maa/htdocs/ch.alamin.se
SERVICE=alamin-frontend
PORT=3000

cd "$SITE"

echo "== المستودع =="
if [ -n "$(git status --porcelain)" ]; then
  echo "المستودع على السيرفر فيه تعديلات غير مدفوعة؛ توقف النشر:" >&2
  git status --short >&2
  exit 1
fi
git fetch --quiet origin main
echo "قبل : $(git log --oneline -1)"
git pull --ff-only origin main
echo "بعد : $(git log --oneline -1)"

cd frontend
node -v

# نسخة احتياطية للرجوع إن فشل البناء
rm -rf .next.bak
[ -d .next ] && cp -a .next .next.bak

restore() {
  echo "فشل النشر: أرجع النسخة السابقة" >&2
  if [ -d .next.bak ]; then rm -rf .next && mv .next.bak .next; fi
}
trap restore ERR

echo "== الحزم والبناء =="
npm ci --no-audit --no-fund
npm run build

echo "== إعادة التشغيل =="
pm2 restart "$SERVICE"
sleep 4
code=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$PORT/login" || true)
echo "frontend /login HTTP: $code"
[ "$code" = "200" ]

trap - ERR
echo "تم نشر الـ frontend. النسخة السابقة محفوظة في frontend/.next.bak"

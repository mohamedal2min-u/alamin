#!/usr/bin/env bash
# يُشغَّل على السيرفر بحساب alamin-api (عبر SSH من GitHub Actions).
set -euo pipefail

APP=/home/alamin-api/app
PHP=/usr/bin/php8.3          # يجب أن يكون فيه pdo_pgsql (8.4 ليس فيه)
COMPOSER=/usr/local/bin/composer

cd "$APP"

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

cd backend
echo "== الحزم =="
COMPOSER_MEMORY_LIMIT=-1 "$PHP" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction

echo "== الترحيلات المعلّقة =="
"$PHP" artisan migrate:status --pending || true

echo "== تشغيل الترحيلات =="
"$PHP" artisan migrate --force

echo "== تخزين مؤقت =="
"$PHP" artisan optimize

echo "== فحص =="
echo "price-audit routes: $("$PHP" artisan route:list --path=accounting | grep -c price-audit || true)"
# -k: الهدف فحص PHP وقاعدة البيانات لا صلاحية الشهادة
code=$(curl -sk -o /dev/null -w "%{http_code}" -X POST https://api.alamin.se/api/auth/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"login":"deploy-check@example.invalid","password":"not-a-real-password"}' || true)
echo "login endpoint HTTP: $code (المتوقع 401 أو 422، و500 يعني مشكلة في قاعدة البيانات/PHP)"
case "$code" in 401|422|429) ;; *) echo "فحص الدخول فشل" >&2; exit 1;; esac

echo "تم نشر الباك إند."

#!/usr/bin/env bash
# نشر الباك إند والـ frontend بأمر واحد. يُشغَّل كـ root من طرفية CloudPanel:
#
#   cd /home/alamin-api/app && sudo -u alamin-api git pull --ff-only origin main && bash .github/deploy/deploy-all.sh
#
# الخيارات: bash .github/deploy/deploy-all.sh [both|backend|frontend]   (الافتراضي both)
#
# root يُستعمل فقط لتبديل المستخدم: الباك إند يعمل كمستخدم alamin-api، والـ frontend كمستخدم maa،
# فتبقى ملكية الملفات سليمة. السكربتان backend.sh و frontend.sh في هذا المجلد يمكن مراجعتهما.
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP=/home/alamin-api/app
MODE="${1:-both}"

[ "$(id -u)" = 0 ] || { echo "شغّله كـ root (طرفية CloudPanel)" >&2; exit 1; }
case "$MODE" in both|backend|frontend) ;; *) echo "الخيار غير صالح: $MODE (both|backend|frontend)" >&2; exit 1;; esac

echo "=== آخر ما في المستودع على السيرفر ==="
sudo -u alamin-api git -C "$APP" log --oneline -5
echo
echo "سيُنشر: $MODE"
echo "الباك إند يشغّل ترحيلات (migrate --force) على قاعدة بيانات الإنتاج."
read -r -p "للمتابعة اكتب DEPLOY: " answer
[ "$answer" = "DEPLOY" ] || { echo "أُلغي النشر."; exit 1; }

if [ "$MODE" = both ] || [ "$MODE" = backend ]; then
  echo
  echo "################ الباك إند (alamin-api) ################"
  sudo -u alamin-api -H bash -s < "$DIR/backend.sh"
fi

if [ "$MODE" = both ] || [ "$MODE" = frontend ]; then
  echo
  echo "################ الـ frontend (maa) ################"
  sudo -u maa -H bash -s < "$DIR/frontend.sh"
fi

echo
echo "=== اكتمل النشر ($MODE) ==="

'use client'

import { useCanRecordPayments, useCanSetPrices } from '@/lib/roles'

/**
 * يعرض محتواه لمن يحدد الأسعار فقط (المحاسب، ومدير النظام).
 * مدير المزرعة والمشرف يرون fallback بدلاً منه، فيُدخلان الكميات بلا أسعار.
 */
export function PriceOnly({ children, fallback = null }: { children: React.ReactNode; fallback?: React.ReactNode }) {
  return useCanSetPrices() ? <>{children}</> : <>{fallback}</>
}

/** يعرض محتواه لمن يسجّل الدفعات فقط (مدير المزرعة والمحاسب). */
export function PaymentOnly({ children, fallback = null }: { children: React.ReactNode; fallback?: React.ReactNode }) {
  return useCanRecordPayments() ? <>{children}</> : <>{fallback}</>
}

/** ملاحظة موحَّدة تظهر مكان حقول السعر المخفية. */
export function PriceByAccountantNote() {
  return (
    <p className="rounded-lg bg-slate-50 px-3 py-2 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
      السعر يحدده المحاسب لاحقاً، وسيظهر في قائمة المحاسبة.
    </p>
  )
}

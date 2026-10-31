// ألوان المال الموحّدة في كل التطبيق:
//   مقبوض (مصاري داخلة: مبيعات)   ← أخضر
//   مدفوع (مصاري طالعة: مصاريف)   ← أحمر
//   متبقي (دين لم يُسدَّد بعد)      ← برتقالي
export type MoneyDirection = 'in' | 'out'

export const MONEY_TONE = {
  in: {
    text: 'text-emerald-700 dark:text-emerald-400',
    badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    soft: 'bg-emerald-50 dark:bg-emerald-900/20',
  },
  out: {
    text: 'text-rose-700 dark:text-rose-400',
    badge: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
    soft: 'bg-rose-50 dark:bg-rose-900/20',
  },
  due: {
    text: 'text-orange-600 dark:text-orange-400',
    badge: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
    soft: 'bg-orange-50 dark:bg-orange-900/20',
  },
} as const

/** المبيعات مقبوضة، وكل ما عداها مدفوع */
export const directionOf = (type?: string | null): MoneyDirection => (type === 'sale' ? 'in' : 'out')

/** شارة حالة الدفع حسب الاتجاه: «مقبوض» أخضر، «مدفوع» أحمر، وما بقي عليه دين برتقالي */
export function paymentBadge(direction: MoneyDirection, status: string): { label: string; className: string } {
  const done = direction === 'in' ? 'مقبوض' : 'مدفوع'
  switch (status) {
    case 'paid':
      return { label: done, className: MONEY_TONE[direction].badge }
    case 'partial':
      return { label: `${done} جزئياً`, className: MONEY_TONE.due.badge }
    case 'unpaid':
    case 'debt':
      return { label: direction === 'in' ? 'غير مقبوض' : 'غير مدفوع', className: MONEY_TONE.due.badge }
    default:
      return { label: status, className: 'bg-slate-100 text-slate-600' }
  }
}

/** خريطة جاهزة لشارات المصاريف (مدفوع) بنفس شكل الخرائط القديمة {label, color} */
export const outBadges = (): Record<string, { label: string; color: string }> =>
  Object.fromEntries(['paid', 'partial', 'unpaid', 'debt'].map((k) => {
    const b = paymentBadge('out', k)
    return [k, { label: b.label, color: b.className }]
  }))

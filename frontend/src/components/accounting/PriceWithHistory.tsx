import type { ReviewItem } from '@/lib/api/accounting'
import { formatNumber } from '@/lib/utils'

// ── السعر الحالي مع الأسعار القديمة مشطوبة (لا تُحذف أبداً) ───────────────────
export function PriceWithHistory({ item }: { item: ReviewItem }) {
  // للمبيعات السعر المخزَّن للكيلو؛ يُعرض بسعر الطن
  const toDisplay = (v: number) => (item.type === 'sale' ? v * 1000 : v)
  const unit = item.type === 'sale' ? 'للطن' : 'للوحدة'
  const olds = (item.price_history ?? []).filter((h) => h.old_price != null)

  if (item.unit_price == null && olds.length === 0) {
    return <span>السعر: <strong className="text-emerald-700">لم يُحدَّد</strong></span>
  }

  return (
    <span className="inline-flex flex-wrap items-center gap-1.5">
      السعر ({unit}):
      {olds.map((h, i) => (
        <s
          key={i}
          className="text-red-400 decoration-red-500"
          title={`${h.by ?? ''} ${h.at ? new Date(h.at).toLocaleString('ar') : ''}`.trim()}
        >
          {formatNumber(toDisplay(h.old_price as number))}
        </s>
      ))}
      {item.unit_price != null && (
        <strong className="text-slate-800">{formatNumber(toDisplay(item.unit_price))}</strong>
      )}
    </span>
  )
}

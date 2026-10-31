import { paymentBadge } from '@/lib/moneyTone'
import { cn } from '@/lib/utils'


interface Props {
  status: string
  className?: string
  /** المبيعات مقبوضة (أخضر) افتراضياً؛ المصاريف مدفوعة (أحمر) */
  direction?: 'in' | 'out'
}

export function PaymentStatusBadge({ status, className, direction = 'in' }: Props) {
  const cfg = paymentBadge(direction, status)
  return (
    <span className={cn('rounded-full px-2.5 py-0.5 text-xs font-semibold', cfg.className, className)}>
      {cfg.label}
    </span>
  )
}


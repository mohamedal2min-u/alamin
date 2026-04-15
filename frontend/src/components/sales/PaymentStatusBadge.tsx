import { cn } from '@/lib/utils'

const STATUS_MAP: Record<string, { label: string; className: string }> = {
  paid:    { label: 'مدفوع',       className: 'bg-emerald-100 text-emerald-700' },
  partial: { label: 'جزئي',        className: 'bg-amber-100 text-amber-700' },
  debt:    { label: 'غير مدفوع',   className: 'bg-red-100 text-red-700' },
}

interface Props {
  status: string
  className?: string
}

export function PaymentStatusBadge({ status, className }: Props) {
  const cfg = STATUS_MAP[status] ?? { label: status, className: 'bg-slate-100 text-slate-600' }
  return (
    <span className={cn('rounded-full px-2.5 py-0.5 text-xs font-semibold', cfg.className, className)}>
      {cfg.label}
    </span>
  )
}

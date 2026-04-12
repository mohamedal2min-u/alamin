import { Badge } from '@/components/ui/Badge'
import type { FlockStatus } from '@/types/flock'

const STATUS_MAP: Record<
  FlockStatus,
  { label: string; variant: 'success' | 'info' | 'neutral' | 'danger' }
> = {
  active:    { label: 'نشط',   variant: 'success' },
  draft:     { label: 'مسودة', variant: 'info' },
  closed:    { label: 'مغلق',  variant: 'neutral' },
  cancelled: { label: 'ملغى',  variant: 'danger' },
}

export function FlockStatusBadge({ status }: { status: FlockStatus }) {
  const { label, variant } = STATUS_MAP[status] ?? { label: status, variant: 'neutral' }
  
  return (
    <div className="flex items-center gap-1.5">
      {status === 'active' && <div className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-status-glow" />}
      <Badge variant={variant}>{label}</Badge>
    </div>
  )
}

import Link from 'next/link'
import { Card, CardContent } from '@/components/ui/Card'
import { FlockStatusBadge } from './FlockStatusBadge'
import { formatDate, formatNumber } from '@/lib/utils'
import { Bird, Calendar, ArrowLeft } from 'lucide-react'
import type { Flock } from '@/types/flock'

export function FlockCard({ flock }: { flock: Flock }) {
  return (
    <Link href={`/flocks/${flock.id}`} className="block">
      <Card className="h-full transition hover:border-primary-300 hover:shadow-md">
        <CardContent className="py-5">
          {/* Title row */}
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2">
                <Bird className="h-4 w-4 shrink-0 text-primary-600" />
                <h3 className="truncate font-semibold text-slate-900">{flock.name}</h3>
              </div>
              <div className="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
                <Calendar className="h-3.5 w-3.5" />
                <span>{formatDate(flock.start_date)}</span>
                {flock.current_age_days !== null && (
                  <span className="text-slate-400">· يوم {flock.current_age_days}</span>
                )}
              </div>
            </div>
            <FlockStatusBadge status={flock.status} />
          </div>

          {/* Stats grid */}
          <div className="mt-4 grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-3 text-center">
            <div>
              <p className="text-xs text-slate-500">العدد الأولي</p>
              <p className="mt-0.5 font-semibold text-slate-900">
                {formatNumber(flock.initial_count)}
              </p>
            </div>
            <div>
              <p className="text-xs text-slate-500">العمر</p>
              <p className="mt-0.5 font-semibold text-slate-900">
                {flock.current_age_days !== null ? `${flock.current_age_days} يوم` : '—'}
              </p>
            </div>
          </div>

          <div className="mt-3 flex items-center justify-end text-xs font-medium text-primary-600">
            <span>تفاصيل الفوج</span>
            <ArrowLeft className="ms-1 h-3.5 w-3.5" />
          </div>
        </CardContent>
      </Card>
    </Link>
  )
}

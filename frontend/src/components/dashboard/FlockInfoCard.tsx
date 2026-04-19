// frontend/src/components/dashboard/FlockInfoCard.tsx
'use client'

import Link from 'next/link'
import { Bird, Calendar, Hash, Users, Activity, HeartPulse } from 'lucide-react'
import { FlockStatusBadge } from '@/components/flocks/FlockStatusBadge'
import { formatDate, formatNumber } from '@/lib/utils'
import type { Flock } from '@/types/flock'

interface Props {
  flock: Flock
}

export function FlockInfoCard({ flock }: Props) {
  const mortalityRate =
    flock.initial_count > 0
      ? ((flock.total_mortality / flock.initial_count) * 100).toFixed(1)
      : '0.0'

  return (
    <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
      {/* Header row - Name and Status */}
      <div className="flex items-center justify-between px-5 py-4 bg-slate-50/50 dark:bg-slate-800/50">
        <div className="flex items-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-100 text-primary-600">
            <Bird className="h-6 w-6" />
          </div>
          <div>
            <Link
              href={`/flocks/${flock.id}`}
              className="text-lg font-extrabold text-slate-900 hover:text-primary-700 transition-colors"
            >
              {flock.name}
            </Link>
            <div className="flex items-center gap-2 mt-0.5 text-xs text-slate-500">
              <Calendar className="h-3 w-3" />
              <span>بدأ في {formatDate(flock.start_date)}</span>
            </div>
          </div>
        </div>
        <FlockStatusBadge status={flock.status} />
      </div>

      {/* Primary Stats Grid */}
      <div className="grid grid-cols-2 gap-px bg-slate-100 border-y border-slate-100">
        <StatCell 
          label="العمر الحالي" 
          value={flock.current_age_days !== null ? `${flock.current_age_days} يوم` : '—'} 
          icon={<Activity className="h-4 w-4 text-emerald-500" />}
          bg="bg-white"
        />
        <StatCell 
          label="العدد المتبقي" 
          value={formatNumber(flock.remaining_count)} 
          icon={<Users className="h-4 w-4 text-primary-600" />}
          bg="bg-white"
          highlight
        />
      </div>

      {/* Secondary Stats Row */}
      <div className="grid grid-cols-2 divide-x divide-x-reverse divide-slate-100 dark:divide-slate-700 px-2 py-3">
        <div className="flex flex-col items-center py-1">
          <span className="text-[10px] font-bold text-slate-400 uppercase mb-1">العدد الأولي</span>
          <div className="flex items-center gap-1.5">
            <Hash className="h-3 w-3 text-slate-400" />
            <span className="text-sm font-bold text-slate-700">{formatNumber(flock.initial_count)}</span>
          </div>
        </div>
        <div className="flex flex-col items-center py-1">
          <span className="text-[10px] font-bold text-slate-400 uppercase mb-1">إجمالي النفوق</span>
          <div className="flex items-center gap-1.5">
            <HeartPulse className="h-3 w-3 text-red-400" />
            <span className="text-sm font-bold text-red-600">{formatNumber(flock.total_mortality)}</span>
            <span className="text-[10px] font-medium text-red-400 bg-red-50 px-1.5 py-0.5 rounded-full">%{mortalityRate}</span>
          </div>
        </div>
      </div>
    </div>
  )
}

function StatCell({ label, value, icon, bg, highlight }: { label: string; value: string; icon: React.ReactNode; bg: string; highlight?: boolean }) {
  return (
    <div className={`flex flex-col items-center justify-center p-5 text-center ${bg} ${highlight ? 'ring-1 ring-inset ring-primary-50 bg-primary-50/30' : ''}`}>
      <div className="flex items-center gap-2 mb-1">
        {icon}
        <span className="text-xs font-bold text-slate-500">{label}</span>
      </div>
      <span className={`text-2xl font-black tabular-nums ${highlight ? 'text-primary-700' : 'text-slate-900'}`}>{value}</span>
    </div>
  )
}


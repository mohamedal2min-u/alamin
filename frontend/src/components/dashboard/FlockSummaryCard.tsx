'use client'

import Link from 'next/link'
import { Calendar } from 'lucide-react'
import { FlockStatusBadge } from '@/components/flocks/FlockStatusBadge'
import { formatDate, formatNumber } from '@/lib/utils'

interface FlockSummaryCardProps {
  currentFlock: any
  mortalityRate: string
}

export function FlockSummaryCard({ currentFlock, mortalityRate }: FlockSummaryCardProps) {
  if (!currentFlock) return null

  return (
    <div className="rounded-2xl bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 overflow-hidden shadow-sm">
      {/* Flock Identity Row */}
      <div className="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 dark:border-slate-700">
        <div className="flex items-center gap-3 min-w-0">
          <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-50">
            <img src="/logo.png" alt="Logo" className="h-5 w-5 object-contain" />
          </div>
          <div className="min-w-0">
            <Link 
              href={`/flocks/${currentFlock.id}`} 
              className="block text-sm font-extrabold text-slate-900 transition-colors duration-200 hover:text-primary-700 truncate"
            >
              {currentFlock.name}
            </Link>
            <div className="flex items-center gap-1 text-[10px] font-semibold text-slate-400 mt-0.5">
              <Calendar className="h-2.5 w-2.5 text-slate-300" />
              <span>{formatDate(currentFlock.start_date)}</span>
            </div>
          </div>
        </div>
        <FlockStatusBadge status={currentFlock.status} />
      </div>

      {/* Metrics — Simple 4-column row */}
      <div className="grid grid-cols-4 divide-x divide-x-reverse divide-slate-100 dark:divide-slate-700">
        <MetricCell 
          label="العمر" 
          value={currentFlock.current_age_days ?? '—'} 
          unit="يوم" 
          valueColor="text-slate-900" 
        />
        <MetricCell 
          label="المتبقي" 
          value={formatNumber(currentFlock.remaining_count)} 
          unit="طير" 
          valueColor="text-primary-600" 
        />
        <MetricCell 
          label="الأولي" 
          value={formatNumber(currentFlock.initial_count)} 
          valueColor="text-slate-600" 
        />
        <MetricCell 
          label="النفوق" 
          value={formatNumber(currentFlock.total_mortality)} 
          unit={`%${mortalityRate}`} 
          valueColor="text-rose-600" 
        />
      </div>
    </div>
  )
}

function MetricCell({ label, value, unit, valueColor = 'text-slate-900' }: { 
  label: string; value: string | number; unit?: string; valueColor?: string 
}) {
  return (
    <div className="flex flex-col items-center justify-center py-3.5 px-2 text-center">
      <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wide mb-1">
        {label}
      </span>
      <span className={`text-lg font-extrabold tabular-nums leading-none ${valueColor}`}>
        {value}
      </span>
      {unit && (
        <span className="text-[9px] font-semibold text-slate-400 mt-0.5">
          {unit}
        </span>
      )}
    </div>
  )
}


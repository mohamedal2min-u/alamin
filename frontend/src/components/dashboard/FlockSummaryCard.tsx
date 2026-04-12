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
    <div
      className="relative overflow-hidden rounded-2xl border border-slate-200/60 bg-white transition-all duration-300"
      style={{ boxShadow: 'var(--shadow-raised)' }}
    >
      {/* ── Header Area ── */}
      <div className="relative flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-5 py-3">
        <div className="flex items-center gap-2.5 min-w-0">
          <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-600 p-1 text-white">
            <img src="/logo.png" alt="Logo" className="h-full w-full object-contain brightness-0 invert" />
          </div>
          <div className="min-w-0">
            <Link 
              href={`/flocks/${currentFlock.id}`} 
              className="block text-sm font-bold tracking-tight text-slate-900 transition-colors duration-200 hover:text-emerald-700 truncate"
            >
              {currentFlock.name}
            </Link>
            <div className="flex items-center gap-1 text-[10px] font-semibold text-slate-400">
              <Calendar className="h-2.5 w-2.5 text-slate-300" />
              <span>{formatDate(currentFlock.start_date)}</span>
            </div>
          </div>
        </div>
        <div className="scale-90 origin-left">
          <FlockStatusBadge status={currentFlock.status} />
        </div>
      </div>

      {/* ── Main Metrics Grid ── */}
      <div className="relative grid grid-cols-2 divide-x divide-x-reverse divide-slate-100 border-b border-slate-100">
        {/* Current Age */}
        <div className="flex flex-col items-center justify-center p-4 text-center">
          <div className="mb-0.5 flex items-center gap-1 text-amber-600/70">
             <span className="text-[9px] font-bold tracking-widest leading-none uppercase">العمر الحالي</span>
          </div>
          <div className="flex items-baseline gap-0.5">
            <span className="text-2xl font-bold tabular-nums text-slate-900 leading-none">
              {currentFlock.current_age_days ?? '—'}
            </span>
            <span className="text-[10px] font-semibold text-slate-400">يوم</span>
          </div>
        </div>

        {/* Remaining Count */}
        <div className="flex flex-col items-center justify-center p-4 text-center">
          <div className="mb-0.5 flex items-center gap-1 text-emerald-600/70">
            <span className="text-[9px] font-bold tracking-widest leading-none uppercase">الطيور المتبقية</span>
          </div>
          <div className="flex items-baseline gap-0.5">
            <span className="text-2xl font-bold tabular-nums text-emerald-600 leading-none">
              {formatNumber(currentFlock.remaining_count)}
            </span>
            <span className="text-[10px] font-semibold text-slate-400">طير</span>
          </div>
        </div>
      </div>

      {/* ── Footer Detail Metrics ── */}
      <div className="grid grid-cols-2 bg-slate-50/30">
        <div className="flex items-center justify-between px-5 py-2 border-l border-slate-100">
          <span className="text-[10px] font-semibold text-slate-400">الأولي</span>
          <span className="text-xs font-bold tabular-nums text-slate-600">{formatNumber(currentFlock.initial_count)}</span>
        </div>
        
        <div className="flex items-center justify-between px-5 py-2">
          <div className="flex items-center gap-1">
             <span className="text-[10px] font-semibold text-rose-400">النفوق</span>
             <span className="text-[9px] font-bold text-rose-600 opacity-60">%{mortalityRate}</span>
          </div>
          <span className="text-xs font-bold tabular-nums text-rose-600">{formatNumber(currentFlock.total_mortality)}</span>
        </div>
      </div>
    </div>
  )
}

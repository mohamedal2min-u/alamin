'use client'

import { cn } from '../../lib/utils'
import { Bird, Skull, Wheat, Syringe, Home } from 'lucide-react'

interface Props {
  flock?: {
    name: string
    initial_count: number
    remaining_count: number
    current_age_days: number
    start_date: string
  }
  summary?: any
  isLoading?: boolean
  viewDate?: string
  onStatClick: (type: 'mortality' | 'feed' | 'medicine' | 'remaining') => void
}

export function WorkerProgressHeader({ flock, summary, isLoading, viewDate, onStatClick }: Props) {
  // Format the date for Arabic display
  const arabicDate = flock?.start_date ? new Date(flock.start_date).toLocaleDateString('ar-EG', {
    day: 'numeric',
    month: 'numeric',
    year: 'numeric'
  }) : '2023/10/01'

  // Helper to build equation string (e.g. "5 + 6")
  const getEq = (entries: any[]) => entries?.length > 0 ? entries.map(e => e.quantity).join(' + ') : '0'
  
  return (
    <div className="space-y-4">
      {/* ── Stitch Refined Header ── */}
      <div className="flex flex-col gap-3">
        {/* Title Row */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2 text-emerald-900">
             <div className="flex h-8 w-8 items-center justify-center rounded-lg border-2 border-emerald-900/10 bg-emerald-50/50">
              <Home className="h-4 w-4 text-emerald-900" strokeWidth={3} />
            </div>
            <h2 className="text-xl font-black tracking-tight">
              {flock?.name ?? 'فوج التسمين'}
            </h2>
          </div>
          <div className="flex items-center gap-1.5 text-emerald-600">
            <div className="h-2 w-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]" />
            <span className="text-xs font-black">مستقر</span>
          </div>
        </div>

        {/* Divider */}
        <div className="h-px w-full bg-slate-100" />

        {/* Meta Grid Row */}
        <div className="flex items-center justify-between px-1">
          <div className="flex items-center gap-4 text-[11px] font-bold">
            <div className="flex items-center gap-2">
              <span className="text-slate-400">البدء:</span>
              <span className="text-slate-700 tabular-nums">{arabicDate}</span>
            </div>
            <div className="h-3 w-px bg-slate-200" />
            <div className="flex items-center gap-2">
              <span className="text-slate-400">الإجمالي:</span>
              <span className="text-slate-700 tabular-nums">{flock?.initial_count?.toLocaleString() ?? '20,000'}</span>
            </div>
          </div>

          <div className="rounded-full bg-emerald-50/70 px-4 py-1.5 border border-emerald-100/50">
            <div className="flex items-center gap-2 text-[11px] font-bold">
              <span className="text-emerald-600/70">المتبقي:</span>
              <span className="text-emerald-700 text-sm font-black tabular-nums">{flock?.remaining_count?.toLocaleString() ?? '18,500'}</span>
            </div>
          </div>
        </div>
      </div>

      {/* ── KPI Entry Grid ── */}
      <div className="grid grid-cols-2 gap-4 pt-2">
        <StatBox
          label="نفوق"
          equation={getEq(summary?.mortalities?.entries)}
          value={summary?.mortalities?.total ?? 0}
          color="red"
          icon={<Skull className="h-4 w-4" />}
          isLoading={isLoading}
          onClick={() => onStatClick('mortality')}
        />
        <StatBox
          label="علف"
          equation={getEq(summary?.feed?.entries)}
          value={summary?.feed?.total ?? 0}
          color="amber"
          icon={<Wheat className="h-4 w-4" />}
          isLoading={isLoading}
          onClick={() => onStatClick('feed')}
        />
        <StatBox
          label="دواء"
          equation={getEq(summary?.medicines?.entries)}
          value={summary?.medicines?.total ?? 0}
          color="pink"
          icon={<Syringe className="h-4 w-4" />}
          isLoading={isLoading}
          onClick={() => onStatClick('medicine')}
        />
        <StatBox
          label="أخرى"
          equation={`${flock?.current_age_days ?? 0} يوم`}
          value={summary?.temperatures?.entries?.[0]?.temperature ?? 0}
          unit="°C"
          color="green"
          icon={<Bird className="h-4 w-4" />}
          isLoading={isLoading}
          onClick={() => onStatClick('remaining')}
        />
      </div>
    </div>
  )
}

function StatBox({ label, equation, value, unit, color, icon, isLoading, onClick }: { 
  label: string, 
  equation: string, 
  value: number, 
  unit?: string,
  color: 'red' | 'amber' | 'pink' | 'green', 
  icon: React.ReactNode, 
  isLoading?: boolean,
  onClick: () => void 
}) {
  if (isLoading) {
    return (
      <div className="h-[120px] animate-pulse rounded-[2rem] bg-slate-50 border border-slate-100" />
    )
  }

  const bgColors = {
    red:   'bg-red-50/30 border-red-100/50',
    amber: 'bg-amber-50/30 border-amber-100/50',
    pink:  'bg-pink-50/30 border-pink-100/50',
    green: 'bg-emerald-50/30 border-emerald-100/50',
  }

  const iconColors = {
    red:   'bg-white text-red-600 shadow-sm border-red-50',
    amber: 'bg-white text-amber-600 shadow-sm border-amber-50',
    pink:  'bg-white text-pink-600 shadow-sm border-pink-50',
    green: 'bg-white text-emerald-600 shadow-sm border-emerald-50',
  }

  return (
    <button 
      onClick={onClick}
      className={cn(
        "flex flex-col gap-4 rounded-[2rem] p-5 border text-right transition-all duration-300 active:scale-95 hover:shadow-md",
        bgColors[color]
      )}
    >
      <div className="flex items-start justify-between">
         <span className="text-[11px] font-bold text-slate-400">
          {label}
        </span>
        <div className={cn("flex h-10 w-10 items-center justify-center rounded-2xl border", iconColors[color])}>
          {icon}
        </div>
      </div>
      
      <div className="flex flex-col">
        <span className="text-[12px] font-bold text-slate-400 tabular-nums">
          {equation}
        </span>
        <div className="flex items-baseline gap-2">
          <span className="text-3xl font-black text-slate-900 tabular-nums tracking-tighter">
            {value}
          </span>
          {unit && <span className="text-xs font-bold text-slate-400">{unit}</span>}
          <span className="text-xl font-black text-slate-300 leading-none">
            =
          </span>
        </div>
      </div>
    </button>
  )
}

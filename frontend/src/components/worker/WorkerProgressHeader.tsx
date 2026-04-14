'use client'

import { clsx, type ClassValue} from 'clsx'
import { twMerge } from 'tailwind-merge'

function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}
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
      <div className="premium-glass rounded-[2rem] p-5 shadow-premium">
        {/* Title Row */}
        <div className="flex items-center justify-between pb-4">
          <div className="flex items-center gap-3">
             <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-emerald-sm">
              <Home className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-xl font-extrabold tracking-tight text-slate-900">
                {flock?.name ?? 'فوج التسمين'}
              </h2>
              <div className="flex items-center gap-1.5 text-emerald-600">
                <div className="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
                <span className="text-[10px] font-bold uppercase tracking-wider">الحالة: مستقر</span>
              </div>
            </div>
          </div>
        </div>

        {/* Divider */}
        <div className="h-px w-full bg-slate-100" />

        {/* Meta Grid Row */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-5 text-[11px] font-bold">
            <div className="flex flex-col gap-0.5">
              <span className="text-slate-400">تاريخ البدء</span>
              <span className="text-slate-900 tabular-nums">{arabicDate}</span>
            </div>
            <div className="h-6 w-px bg-slate-200" />
            <div className="flex flex-col gap-0.5">
              <span className="text-slate-400">العدد الأصلي</span>
              <span className="text-slate-900 tabular-nums">{flock?.initial_count?.toLocaleString() ?? '20,000'}</span>
            </div>
          </div>

          <div className="relative overflow-hidden rounded-2xl bg-emerald-600 px-5 py-2 shadow-emerald">
            <div className="absolute inset-0 animate-shimmer" />
            <div className="relative flex flex-col items-center">
              <span className="text-[9px] font-bold uppercase text-emerald-100 tracking-widest">المتبقي حالياً</span>
              <span className="text-lg font-black text-white tabular-nums tracking-tighter">
                {flock?.remaining_count?.toLocaleString() ?? '18,500'}
              </span>
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
        "flex flex-col gap-4 rounded-[2rem] p-5 border text-right transition-all duration-300 active:scale-95 shadow-sm hover:shadow-premium hover:-translate-y-1",
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

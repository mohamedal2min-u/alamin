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
      <div className="grid grid-cols-2 gap-2 sm:gap-4 pt-2">
        <StatBox
          label="نفوق"
          equation={getEq(summary?.mortalities?.entries)}
          value={summary?.mortalities?.total ?? 0}
          color="red"
          icon={<Skull className="h-6 w-6" />}
          isLoading={isLoading}
          onClick={() => onStatClick('mortality')}
        />
        <StatBox
          label="علف"
          equation={getEq(summary?.feed?.entries)}
          value={summary?.feed?.total ?? 0}
          color="amber"
          icon={<Wheat className="h-6 w-6" />}
          isLoading={isLoading}
          onClick={() => onStatClick('feed')}
        />
        <StatBox
          label="دواء"
          equation={getEq(summary?.medicines?.entries)}
          value={summary?.medicines?.total ?? 0}
          color="indigo"
          icon={<Syringe className="h-6 w-6" />}
          isLoading={isLoading}
          onClick={() => onStatClick('medicine')}
        />
        <StatBox
          label="أخرى"
          equation={`${flock?.current_age_days ?? 0} يوم`}
          value={summary?.temperatures?.entries?.[0]?.temperature ?? 0}
          unit="°C"
          color="green"
          icon={<Bird className="h-6 w-6" />}
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
  color: 'red' | 'amber' | 'indigo' | 'green', 
  icon: React.ReactNode, 
  isLoading?: boolean,
  onClick: () => void 
}) {
  if (isLoading) {
    return (
      <div className="h-[140px] animate-pulse rounded-[2rem] sm:rounded-[2.5rem] bg-slate-50 border border-slate-100" />
    )
  }

  const themes = {
    red: {
      bg: 'bg-gradient-to-br from-rose-100/50 to-white',
      border: 'border-rose-200/60',
      shadow: 'shadow-lg shadow-rose-500/10',
      iconBg: 'bg-rose-100 text-rose-600',
      text: 'text-rose-600',
      glow: 'hover:shadow-rose-500/20 hover:border-rose-300'
    },
    amber: {
      bg: 'bg-gradient-to-br from-amber-100/50 to-white',
      border: 'border-amber-200/60',
      shadow: 'shadow-lg shadow-amber-500/10',
      iconBg: 'bg-amber-100 text-amber-600',
      text: 'text-amber-600',
      glow: 'hover:shadow-amber-500/20 hover:border-amber-300'
    },
    indigo: {
      bg: 'bg-gradient-to-br from-indigo-100/50 to-white',
      border: 'border-indigo-200/60',
      shadow: 'shadow-lg shadow-indigo-500/10',
      iconBg: 'bg-indigo-100 text-indigo-600',
      text: 'text-indigo-600',
      glow: 'hover:shadow-indigo-500/20 hover:border-indigo-300'
    },
    green: {
      bg: 'bg-gradient-to-br from-emerald-100/50 to-white',
      border: 'border-emerald-200/60',
      shadow: 'shadow-lg shadow-emerald-500/10',
      iconBg: 'bg-emerald-100 text-emerald-600',
      text: 'text-emerald-700',
      glow: 'hover:shadow-emerald-500/20 hover:border-emerald-300'
    }
  }

  const theme = themes[color]

  return (
    <button 
      onClick={onClick}
      className={cn(
        "group relative flex flex-col gap-5 rounded-[2rem] sm:rounded-[2.5rem] p-4 sm:p-6 border text-right transition-all duration-500 active:scale-95",
        theme.bg,
        theme.border,
        theme.shadow,
        theme.glow
      )}
    >
      <div className="flex items-start justify-between">
        <div className={cn(
          "flex h-12 w-12 items-center justify-center rounded-2xl transition-all duration-500 group-hover:scale-110 group-hover:rotate-3", 
          theme.iconBg
        )}>
          {icon}
        </div>
        <span className="text-[11px] font-extrabold text-slate-400 uppercase tracking-widest mt-1">
          {label}
        </span>
      </div>
      
      <div className="flex items-baseline justify-between w-full mt-2">
        <span className="text-[13px] font-bold text-slate-400 tabular-nums">
          {equation}
        </span>
        <div className="flex items-baseline gap-2">
          {unit && <span className="text-sm font-bold text-slate-400">{unit}</span>}
          <span className={cn("text-4xl font-black tabular-nums tracking-tighter transition-all duration-500", theme.text)}>
            {value}
          </span>
        </div>
      </div>

      {/* Subtle Glow Overlay */}
      <div className="absolute inset-x-0 bottom-0 h-1 rounded-full bg-gradient-to-r from-transparent via-current to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-20" />
    </button>
  )
}

'use client'

import { cn } from '@/lib/utils'
import { Skull, Wheat, Syringe, ThermometerSun, Calendar } from 'lucide-react'
import { FlockStatusBadge } from '@/components/flocks/FlockStatusBadge'
import { formatDate, formatNumber } from '@/lib/utils'

interface Props {
  flock?: {
    id: number
    name: string
    initial_count: number
    remaining_count: number
    current_age_days: number
    start_date: string
    status?: any
    total_mortality?: number
  }
  summary?: any
  isLoading?: boolean
  viewDate?: string
  onStatClick: (type: 'mortality' | 'feed' | 'medicine' | 'remaining') => void
}

export function WorkerProgressHeader({ flock, summary, isLoading, viewDate, onStatClick }: Props) {
  const getEq = (entries: any[]) => entries?.length > 0 ? entries.map(e => e.quantity).join(' + ') : '—'

  const mortalityRate = flock 
    ? (flock.initial_count > 0 
        ? ((flock.total_mortality ?? 0) / flock.initial_count * 100).toFixed(1)
        : '0.0')
    : '0.0'

  return (
    <div className="space-y-3">
      {/* ── Flock Identity Card ── */}
      <div className="rounded-2xl bg-white border border-emerald-100 overflow-hidden shadow-sm">
        {/* Top: Logo + Name + Status */}
        <div className="flex items-center justify-between px-4 py-3 border-b border-emerald-50/50">
          <div className="flex items-center gap-3 text-right min-w-0">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-[0.85rem] bg-gradient-to-br from-emerald-500 to-emerald-600 shadow-md">
              <img src="/logo.png" alt="Logo" className="h-6 w-6 object-contain brightness-0 invert" />
            </div>
            <div className="min-w-0">
              <h2 className="text-base font-black text-emerald-950 leading-tight truncate">
                {flock?.name ?? 'فوج التسمين'}
              </h2>
              <div className="flex items-center gap-1 text-[10px] font-bold text-emerald-600/70 mt-0.5">
                <Calendar className="h-3 w-3" />
                <span>{flock ? formatDate(flock.start_date) : '...'}</span>
              </div>
            </div>
          </div>
          <FlockStatusBadge status={flock?.status ?? 'active'} />
        </div>

        {/* Metrics: 4 columns */}
        <div className="grid grid-cols-4 divide-x divide-x-reverse divide-emerald-50/50">
          <MetricItem label="العمر" value={flock?.current_age_days ?? '—'} sub="يوم" color="text-emerald-950" />
          <MetricItem label="المتبقي" value={formatNumber(flock?.remaining_count ?? 0)} sub="طير" color="text-emerald-600" />
          <MetricItem label="الأولي" value={formatNumber(flock?.initial_count ?? 0)} color="text-slate-600" />
          <MetricItem label="النفوق" value={formatNumber(flock?.total_mortality ?? 0)} sub={`${mortalityRate}%`} color="text-rose-600" />
        </div>
      </div>

      {/* ── Quick Action Stat Grid ── */}
      <div className="grid grid-cols-2 gap-2.5">
        <StatBox
          label="نفوق اليوم"
          equation={getEq(summary?.mortalities?.entries)}
          value={summary?.mortalities?.total ?? 0}
          color="red"
          icon={<Skull className="h-5 w-5" />}
          isLoading={isLoading}
          onClick={() => onStatClick('mortality')}
        />
        <StatBox
          label="استهلاك العلف"
          equation={getEq(summary?.feed?.entries)}
          value={summary?.feed?.total ?? 0}
          color="amber"
          icon={<Wheat className="h-5 w-5" />}
          isLoading={isLoading}
          onClick={() => onStatClick('feed')}
        />
        <StatBox
          label="أدوية / إضافات"
          equation={getEq(summary?.medicines?.entries)}
          value={summary?.medicines?.total ?? 0}
          color="indigo"
          icon={<Syringe className="h-5 w-5" />}
          isLoading={isLoading}
          onClick={() => onStatClick('medicine')}
        />
        <StatBox
          label="حرارة / مياه"
          equation={`${flock?.current_age_days ?? 0} يوم`}
          value={summary?.temperatures?.entries?.[0]?.temperature ?? 0}
          unit="°C"
          color="green"
          icon={<ThermometerSun className="h-5 w-5" />}
          isLoading={isLoading}
          onClick={() => onStatClick('remaining')}
        />
      </div>
    </div>
  )
}

function MetricItem({ label, value, sub, color }: { label: string; value: string | number; sub?: string; color: string }) {
  return (
    <div className="flex flex-col items-center justify-center py-2.5 px-1 text-center">
      <span className="text-[9px] font-bold text-slate-400 mb-0.5">{label}</span>
      <span className={cn("text-lg font-black tabular-nums leading-none", color)}>{value}</span>
      {sub && <span className="text-[9px] font-bold text-slate-400 mt-0.5">{sub}</span>}
    </div>
  )
}

function StatBox({ label, equation, value, unit, color, icon, isLoading, onClick }: { 
  label: string
  equation: string 
  value: number
  unit?: string
  color: 'red' | 'amber' | 'indigo' | 'green'
  icon: React.ReactNode 
  isLoading?: boolean
  onClick: () => void 
}) {
  const themes = {
    red: {
      bg: 'bg-gradient-to-br from-rose-50 to-white',
      border: 'border-rose-200/50',
      iconBg: 'bg-rose-100 text-rose-500',
      text: 'text-rose-600',
    },
    amber: {
      bg: 'bg-gradient-to-br from-amber-50 to-white',
      border: 'border-amber-200/50',
      iconBg: 'bg-amber-100 text-amber-500',
      text: 'text-amber-600',
    },
    indigo: {
      bg: 'bg-gradient-to-br from-indigo-50 to-white',
      border: 'border-indigo-200/50',
      iconBg: 'bg-indigo-100 text-indigo-500',
      text: 'text-indigo-600',
    },
    green: {
      bg: 'bg-gradient-to-br from-emerald-50 to-white',
      border: 'border-emerald-200/50',
      iconBg: 'bg-emerald-100 text-emerald-500',
      text: 'text-emerald-700',
    }
  }

  const t = themes[color]

  return (
    <button 
      onClick={onClick}
      disabled={isLoading}
      className={cn(
        "group relative flex flex-col rounded-2xl p-3.5 border text-right active:scale-[0.97] touch-manipulation",
        t.bg, t.border
      )}
    >
      {/* Top: Icon + Label */}
      <div className="flex items-center justify-between mb-3">
        <div className={cn("flex h-10 w-10 items-center justify-center rounded-xl", t.iconBg,
          isLoading && "animate-pulse opacity-50"
        )}>
          {icon}
        </div>
        <span className="text-[10px] font-extrabold text-slate-400">{label}</span>
      </div>
      
      {/* Bottom: Value + Equation */}
      <div className="flex items-end justify-between w-full">
        {isLoading ? (
          <div className="h-3.5 w-12 bg-slate-100 rounded animate-pulse" />
        ) : (
          <span className="text-[11px] font-bold text-slate-400 tabular-nums">{equation}</span>
        )}
        <div className="flex items-baseline gap-1">
          {unit && <span className="text-xs font-bold text-slate-400">{unit}</span>}
          {isLoading ? (
            <div className="h-8 w-10 bg-slate-100 rounded-lg animate-pulse" />
          ) : (
            <span className={cn("text-3xl font-black tabular-nums tracking-tighter", t.text)}>
              {value}
            </span>
          )}
        </div>
      </div>
    </button>
  )
}

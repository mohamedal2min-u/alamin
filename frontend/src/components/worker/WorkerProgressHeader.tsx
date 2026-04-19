'use client'

import { cn } from '@/lib/utils'
import { Skull, Wheat, Syringe, ThermometerSun, Calendar, Receipt, UserCircle } from 'lucide-react'
import { FlockStatusBadge } from '@/components/flocks/FlockStatusBadge'
import { formatDate, formatNumber } from '@/lib/utils'
import { profileApi } from '@/lib/api/profile'
import { useAuthStore } from '@/stores/auth.store'
import { useState } from 'react'

interface Props {
  flock?: {
    id: number
    name: string
    initial_count: number
    remaining_count: number
    current_age_days: number | null
    start_date: string
    status?: any
    total_mortality?: number
  }
  summary?: any
  isLoading?: boolean
  viewDate?: string
  role?: 'worker' | 'manager'
  onStatClick: (type: 'mortality' | 'feed' | 'medicine' | 'remaining' | 'expense') => void
}

export function WorkerProgressHeader({ flock, summary, isLoading, viewDate, role = 'worker', onStatClick }: Props) {
  const { user, setUser } = useAuthStore()
  const [isUploading, setIsUploading] = useState(false)

  const handleAvatarUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file || !user) return

    setIsUploading(true)
    try {
      const res = await profileApi.uploadAvatar(file)
      setUser({ ...user, avatar_url: res.avatar_url })
    } catch (error) {
      console.error('Failed to upload avatar', error)
      alert('حدث خطأ أثناء رفع الصورة')
    } finally {
      setIsUploading(false)
      // reset input
      e.target.value = ''
    }
  }

  const getEq = (entries: any[]) => entries?.length > 0 ? entries.map(e => e.quantity).join(' + ') : '—'

  const mortalityRate = flock 
    ? (flock.initial_count > 0 
        ? ((flock.total_mortality ?? 0) / flock.initial_count * 100).toFixed(1)
        : '0.0')
    : '0.0'

  return (
    <div className="space-y-3">
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
          color="emerald"
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
        {role === 'worker' ? (
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
        ) : (
          <StatBox
            label="المصروفات اليومية"
            equation={`${summary?.expenses?.entries?.length ?? 0} حركة`}
            value={summary?.expenses?.total ?? 0}
            color="green"
            icon={<Receipt className="h-5 w-5" />}
            isLoading={isLoading}
            onClick={() => onStatClick('expense')}
          />
        )}
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
  color: 'red' | 'emerald' | 'indigo' | 'green'
  icon: React.ReactNode 
  isLoading?: boolean
  onClick: () => void 
}) {
  const themes = {
    red: {
      bg: 'bg-rose-50/60 dark:bg-slate-800',
      border: 'border-rose-200/60 dark:border-rose-900/50',
      iconBg: 'bg-rose-100 text-rose-500 dark:bg-rose-900/40 dark:text-rose-400',
      text: 'text-rose-600 dark:text-rose-400',
    },
    emerald: {
      bg: 'bg-emerald-50/60 dark:bg-slate-800',
      border: 'border-emerald-200/60 dark:border-emerald-900/50',
      iconBg: 'bg-emerald-100 text-emerald-500 dark:bg-emerald-900/40 dark:text-emerald-400',
      text: 'text-emerald-600 dark:text-emerald-400',
    },
    indigo: {
      bg: 'bg-indigo-50/60 dark:bg-slate-800',
      border: 'border-indigo-200/60 dark:border-indigo-900/50',
      iconBg: 'bg-indigo-100 text-indigo-500 dark:bg-indigo-900/40 dark:text-indigo-400',
      text: 'text-indigo-600 dark:text-indigo-400',
    },
    green: {
      bg: 'bg-primary-50/60 dark:bg-slate-800',
      border: 'border-primary-200/60 dark:border-primary-900/50',
      iconBg: 'bg-primary-100 text-primary-500 dark:bg-primary-900/40 dark:text-primary-400',
      text: 'text-primary-700 dark:text-primary-400',
    },
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
        <span className="text-[10px] font-extrabold text-slate-500 dark:text-slate-400">{label}</span>
      </div>
      
      {/* Bottom: Value + Equation */}
      <div className="flex items-end justify-between w-full">
        {isLoading ? (
          <div className="h-3.5 w-12 bg-slate-100 rounded animate-pulse" />
        ) : (
          <span className="text-[11px] font-bold text-slate-500 dark:text-slate-400 tabular-nums">{equation}</span>
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


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
  onDateChange?: (date: string) => void
  onStatClick: (type: 'mortality' | 'feed' | 'medicine' | 'remaining' | 'expense') => void
}

export function WorkerProgressHeader({ flock, summary, isLoading, viewDate, role = 'worker', onDateChange, onStatClick }: Props) {
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

  const today = new Date().toISOString().split('T')[0]
  const isNotToday = viewDate && viewDate !== today

  return (
    <div className="space-y-3">
      {/* ── Date Selection Header ── */}
      <div className="flex items-center justify-between px-1">
        <div className="flex items-center gap-2.5">
          <div className="relative">
            <input 
              type="date" 
              value={viewDate}
              onChange={(e) => onDateChange?.(e.target.value)}
              className="absolute inset-0 opacity-0 cursor-pointer z-10"
            />
            <div className={cn(
              "flex h-9 w-9 items-center justify-center rounded-xl border transition-all duration-300",
              isNotToday 
                ? "bg-amber-50 border-amber-200 text-amber-500 shadow-sm" 
                : "bg-emerald-50 border-emerald-100 text-emerald-500"
            )}>
              <Calendar className="h-4.5 w-4.5" />
            </div>
          </div>
          <div className="flex flex-col -space-y-0.5">
            <span className="text-[10px] font-extrabold text-slate-400 uppercase tracking-tight">سجل التاريخ</span>
            <span className={cn(
              "text-sm font-black tracking-tight",
              isNotToday ? "text-amber-600" : "text-emerald-950"
            )}>
              {isNotToday ? viewDate : 'اليوم (الآن)'}
            </span>
          </div>
        </div>

        {isNotToday && (
          <button 
            onClick={() => onDateChange?.(today)}
            className="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-slate-100 text-slate-500 text-[10px] font-black active:scale-95 transition-all"
          >
            العودة لليوم
          </button>
        )}
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
            color="orange"
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
  color: 'red' | 'amber' | 'indigo' | 'green' | 'orange'
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
    },
    orange: {
      bg: 'bg-gradient-to-br from-orange-50 to-white',
      border: 'border-orange-200/50',
      iconBg: 'bg-orange-100 text-orange-500',
      text: 'text-orange-600',
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

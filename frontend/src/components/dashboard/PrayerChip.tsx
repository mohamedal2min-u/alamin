'use client'

import Link from 'next/link'
import { useSyncExternalStore } from 'react'
import { BellRing, ChevronLeft, MapPin } from 'lucide-react'
import { FIXED_CALC, PRAYERS, REGION_NAME, formatRegionTime, prayerTimesFor, regionDate, upcomingPrayers } from '@/lib/prayer/times'
import { cn } from '@/lib/utils'
import { useTick } from '@/hooks/useTick'

function remaining(ms: number): string {
  const mins = Math.max(0, Math.round(ms / 60_000))
  const h = Math.floor(mins / 60)
  const m = mins % 60
  if (h === 0) return `${m} دقيقة`
  return m === 0 ? `${h} ساعة` : `${h} س ${m} د`
}

/** بطاقة الأذان في أسفل الرئيسية: الصلاة القادمة والعدّ التنازلي وأوقات اليوم الخمسة بتوقيت إدلب — جبل الزاوية */
export function PrayerChip() {
  const mounted = useSyncExternalStore(() => () => {}, () => true, () => false)
  const now = useTick(60_000) // دقيقة واحدة، وتتوقف عند إخفاء الشاشة

  if (!mounted) return <div className="h-44" />

  const times = prayerTimesFor(regionDate(now), FIXED_CALC)
  const next = upcomingPrayers(now, FIXED_CALC, 2)[0]
  const isNextToday = (key: string) => next && next.key === key && next.at.getTime() === times[key as keyof typeof times].getTime()

  return (
    <Link
      href="/prayer"
      className="block overflow-hidden rounded-3xl bg-gradient-to-br from-primary-700 via-primary-800 to-primary-950 p-4 text-white shadow-lg shadow-primary-900/20 transition-transform active:scale-[0.985]"
    >
      <div className="flex items-center justify-between">
        <span className="flex items-center gap-2 text-xs font-extrabold">
          <span className="relative flex h-8 w-8 items-center justify-center rounded-xl bg-white/15">
            <BellRing className="h-4 w-4" />
            <span className="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-emerald-400 ring-2 ring-primary-800" />
          </span>
          الأذان
        </span>
        <span className="flex items-center gap-1 text-[11px] font-bold text-primary-100">
          <MapPin className="h-3 w-3" /> {REGION_NAME}
          <ChevronLeft className="h-3.5 w-3.5" />
        </span>
      </div>

      {next && (
        <div className="mt-3 flex items-end justify-between gap-3">
          <div>
            <p className="text-[11px] font-semibold text-primary-100">الصلاة القادمة</p>
            <p className="mt-0.5 text-2xl font-black leading-none">{next.label}</p>
          </div>
          <div className="text-left">
            <p className="text-3xl font-black tabular-nums leading-none">{formatRegionTime(next.at)}</p>
            <p className="mt-1 text-[11px] font-bold text-primary-100">بعد {remaining(next.at.getTime() - now.getTime())}</p>
          </div>
        </div>
      )}

      <div className="mt-4 grid grid-cols-5 gap-1.5">
        {PRAYERS.map((p) => (
          <div key={p.key} className={cn('rounded-xl px-1 py-2 text-center', isNextToday(p.key) ? 'bg-white text-primary-900' : 'bg-white/10')}>
            <p className={cn('text-[10px] font-bold', isNextToday(p.key) ? 'text-primary-700' : 'text-primary-100')}>{p.label}</p>
            <p className="mt-0.5 text-[11px] font-extrabold tabular-nums">{formatRegionTime(times[p.key]).replace(' ', ' ')}</p>
          </div>
        ))}
      </div>
    </Link>
  )
}

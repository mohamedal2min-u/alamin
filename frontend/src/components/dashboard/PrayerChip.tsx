'use client'

import Link from 'next/link'
import { useSyncExternalStore } from 'react'
import { BellOff, BellRing, ChevronLeft } from 'lucide-react'
import { usePrayerStore } from '@/lib/prayer/store'
import { formatRegionTime, upcomingPrayers, type PrayerKey } from '@/lib/prayer/times'
import { cn } from '@/lib/utils'
import { useTick } from '@/hooks/useTick'

function remaining(ms: number): string {
  const mins = Math.max(0, Math.round(ms / 60_000))
  const h = Math.floor(mins / 60)
  const m = mins % 60
  if (h === 0) return `${m} د`
  return m === 0 ? `${h} س` : `${h} س ${m} د`
}

/** شريط صغير في الرئيسية: حالة الأذان والصلاة القادمة بتوقيت إدلب — جبل الزاوية */
export function PrayerChip() {
  const mounted = useSyncExternalStore(() => () => {}, () => true, () => false)
  const enabled = usePrayerStore((s) => s.enabled)
  const method = usePrayerStore((s) => s.method)
  const hanafiAsr = usePrayerStore((s) => s.hanafiAsr)
  const adjust = usePrayerStore((s) => s.adjust)
  const prayers = usePrayerStore((s) => s.prayers)
  const now = useTick(60_000) // دقيقة واحدة، وتتوقف عند إخفاء الشاشة

  if (!mounted) return <div className="h-14" />

  const upcoming = upcomingPrayers(now, { method, hanafiAsr, adjust }, 2)
  // القادمة بين المفعّلة إن كان الأذان يعمل، وإلا القادمة مطلقاً
  const next = (enabled ? upcoming.find((p) => prayers[p.key as PrayerKey]) : undefined) ?? upcoming[0]

  return (
    <Link
      href="/prayer"
      className={cn(
        'flex items-center gap-3 rounded-2xl border px-3.5 py-2.5 transition-transform active:scale-[0.98]',
        enabled
          ? 'border-primary-200 bg-primary-50/70 dark:border-primary-900/50 dark:bg-primary-900/20'
          : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800',
      )}
    >
      <span className={cn('relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl', enabled ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-400 dark:bg-slate-700')}>
        {enabled ? <BellRing className="h-5 w-5" /> : <BellOff className="h-5 w-5" />}
        {enabled && <span className="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-emerald-400 ring-2 ring-white dark:ring-slate-800" />}
      </span>

      <div className="min-w-0 flex-1">
        <p className="truncate text-[11px] font-bold text-slate-500 dark:text-slate-400">
          {enabled ? 'الأذان مفعّل · جبل الزاوية' : 'الأذان متوقف · اضغط للتفعيل'}
        </p>
        {next && (
          <p className="mt-0.5 truncate text-sm font-extrabold text-slate-800 dark:text-slate-100">
            {next.label} <span className="tabular-nums">{formatRegionTime(next.at)}</span>
          </p>
        )}
      </div>

      {next && (
        <span className="shrink-0 rounded-full bg-white px-2.5 py-1 text-[11px] font-extrabold tabular-nums text-primary-700 shadow-sm dark:bg-slate-700 dark:text-primary-300">
          بعد {remaining(next.at.getTime() - now.getTime())}
        </span>
      )}
      <ChevronLeft className="h-4 w-4 shrink-0 text-slate-300" />
    </Link>
  )
}

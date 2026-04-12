'use client'

import { useState, useEffect } from 'react'
import { AlertCircle, Zap, Bird } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { flocksApi } from '@/lib/api/flocks'
import { useFarmStore } from '@/stores/farm.store'
import { useLayoutStore } from '@/stores/layout.store'
import { FlockSummaryCard } from '@/components/dashboard/FlockSummaryCard'
import { OperationalInfoCard } from '@/components/dashboard/OperationalInfoCard'
import { QuickEntryCard } from '@/components/dashboard/QuickEntryCard'
import { DaySummaryCard } from '@/components/dashboard/DaySummaryCard'
import type { TodaySummary } from '@/types/dashboard'

export default function DashboardPage() {
  const { currentFarm } = useFarmStore()
  const { setPageTitle, setPageSubtitle } = useLayoutStore()
  const [activating, setActivating] = useState(false)
  const [activateError, setActivateError] = useState<string | null>(null)

  // ── Global Header Info ──
  useEffect(() => {
    setPageTitle('لوحة التحكم')
    setPageSubtitle(currentFarm?.name || null)
  }, [currentFarm, setPageTitle, setPageSubtitle])

  // ── Live Data: Flocks ──
  const {
    data: flocks = [],
    isLoading: loadingFlocks,
    isError: hasError,
    refetch: refetchFlocks,
  } = useQuery({
    queryKey: ['flocks', currentFarm?.id],
    queryFn: () => flocksApi.list().then(res => res.data),
    enabled: !!currentFarm,
    refetchInterval: 30_000,
  })

  // ── Derived State ──
  const activeFlock = flocks.find((f: any) => f.status === 'active') ?? null
  const draftFlock = !activeFlock ? (flocks.find((f: any) => f.status === 'draft') ?? null) : null
  const currentFlock = activeFlock ?? draftFlock
  const isActive = currentFlock?.status === 'active'

  // ── Live Data: Today's Summary ──
  const {
    data: summary,
    isLoading: loadingSummary,
    refetch: refetchSummary,
  } = useQuery({
    queryKey: ['today-summary', activeFlock?.id],
    queryFn: () => flocksApi.todaySummary(activeFlock!.id).then(res => res.data),
    enabled: !!activeFlock,
    refetchInterval: 30_000,
  })

  const handleEntrySuccess = () => {
    refetchSummary()
    refetchFlocks()
  }

  const handleActivate = async () => {
    if (!draftFlock) return
    setActivating(true)
    setActivateError(null)
    try {
      await flocksApi.update(draftFlock.id, { status: 'active' })
      refetchFlocks()
    } catch {
      setActivateError('تعذّر تفعيل الفوج، حاول مجدداً')
    } finally {
      setActivating(false)
    }
  }

  const mortalityRate = currentFlock && currentFlock.initial_count > 0
    ? ((currentFlock.total_mortality / currentFlock.initial_count) * 100).toFixed(1)
    : '0.0'

  return (
    <div className="mx-auto max-w-2xl space-y-5 pb-24 sm:pb-8 pt-4" dir="rtl">
      
      {/* ── Global Error ── */}
      {hasError && (
        <div className="flex items-center gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 text-red-700">
          <AlertCircle className="h-5 w-5 shrink-0" />
          <p className="text-sm font-bold">تعذّر تحديث البيانات حالياً</p>
        </div>
      )}

      {/* ── Loading Skeleton ── */}
      {loadingFlocks && flocks.length === 0 && (
        <div className="space-y-4">
          <div className="h-32 animate-pulse rounded-2xl bg-slate-200/60" />
          <div className="h-64 animate-pulse rounded-2xl bg-slate-200/60" />
          <div className="h-36 animate-pulse rounded-2xl bg-slate-200/60" />
        </div>
      )}

      {/* ── Empty States ── */}
      {!loadingFlocks && !currentFarm && (
        <EmptyState title="لم تُحدَّد مزرعة" subtitle="اختر مزرعة من القائمة للمتابعة" />
      )}

      {/* ── Main Dashboard Content ── */}
      {!loadingFlocks && currentFlock ? (
        <>
          <FlockSummaryCard currentFlock={currentFlock} mortalityRate={mortalityRate} />

          {/* Draft Notification */}
          {draftFlock && (
            <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5 overflow-hidden relative" style={{ boxShadow: 'var(--shadow-card)' }}>
              <div className="relative z-10 flex items-start gap-4">
                <div className="w-11 h-11 rounded-2xl bg-white flex items-center justify-center shrink-0" style={{ boxShadow: 'var(--shadow-card)' }}>
                  <Zap className="h-5 w-5 text-amber-500" />
                </div>
                <div className="flex-1 min-w-0">
                  <h3 className="font-bold text-amber-900 text-sm">الفوج في انتظار التفعيل</h3>
                  <p className="mt-1 text-xs font-medium text-amber-700 leading-relaxed">
                    فعّل الفوج للبدء في تسجيل البيانات التشغيلية اليومية ومزامنة التقارير.
                  </p>
                  {activateError && <p className="mt-2 text-[10px] font-bold text-red-600">{activateError}</p>}
                  <button onClick={handleActivate} disabled={activating} className="mt-3 inline-flex items-center justify-center rounded-xl bg-amber-600 px-5 py-2 text-xs font-bold text-white transition-colors duration-200 hover:bg-amber-700 active:scale-[0.98] disabled:opacity-50">
                    {activating ? 'جارٍ التفعيل...' : 'تفعيل الفوج الآن'}
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* Operation Tools */}
          {isActive && (
            <div className="space-y-5">
              <OperationalInfoCard ageDays={currentFlock.current_age_days} birdCount={currentFlock.remaining_count} />
              <QuickEntryCard flockId={currentFlock.id} onSuccess={handleEntrySuccess} />
              <DaySummaryCard summary={summary ?? emptyTodaySummary()} loading={loadingSummary} />
            </div>
          )}
        </>
      ) : (
        !loadingFlocks && !hasError && currentFarm && (
          <EmptyState title="لا توجد أفواج نشطة" subtitle="يمكنك إدارة وأرشفة الأفواج من خلال صفحة الأفواج في القائمة الجانبية" />
        )
      )}
    </div>
  )
}

function EmptyState({ title, subtitle }: { title: string; subtitle: string }) {
  return (
    <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white py-16 px-4 text-center" style={{ boxShadow: 'var(--shadow-card)' }}>
      <div className="w-14 h-14 rounded-2xl bg-slate-50 flex items-center justify-center mb-4 p-3">
        <img src="/logo.png" alt="Logo" className="h-full w-full object-contain" />
      </div>
      <h3 className="text-sm font-bold text-slate-700">{title}</h3>
      <p className="mt-1.5 text-xs text-slate-400 font-medium max-w-[200px] leading-relaxed">{subtitle}</p>
    </div>
  )
}

function emptyTodaySummary(): TodaySummary {
  return {
    date: new Date().toISOString().slice(0, 10),
    mortalities: { entries: [], total: 0 },
    feed:        { entries: [], total: 0 },
    medicines:   { entries: [], total: 0 },
    expenses:    { entries: [], total: 0 },
    water:       { entries: [], total: 0 },
    temperatures: { entries: [] },
  }
}

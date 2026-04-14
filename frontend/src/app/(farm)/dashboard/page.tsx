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
import type { Flock } from '@/types/flock'

export default function DashboardPage() {
  const { currentFarm } = useFarmStore()
  const { setPageTitle, setPageSubtitle } = useLayoutStore()
  const [activating, setActivating] = useState(false)
  const [activateError, setActivateError] = useState<string | null>(null)

  useEffect(() => {
    setPageTitle('لوحة التحكم')
    setPageSubtitle(currentFarm?.name || null)
  }, [currentFarm, setPageTitle, setPageSubtitle])

  const {
    data: flocks = [],
    isLoading: loadingFlocks,
    isError: hasError,
    refetch: refetchFlocks,
  } = useQuery<Flock[]>({
    queryKey: ['flocks', currentFarm?.id],
    queryFn: () => flocksApi.list().then((res): Flock[] => res.data),
    enabled: !!currentFarm,
    refetchInterval: 30_000,
  })

  const activeFlock = flocks.find((f) => f.status === 'active') ?? null
  const draftFlock = !activeFlock ? (flocks.find((f) => f.status === 'draft') ?? null) : null
  const currentFlock = activeFlock ?? draftFlock
  const isActive = currentFlock?.status === 'active'

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
    <div className="space-y-5 px-5 pt-5 pb-8" dir="rtl">
      
      {/* Error */}
      {hasError && (
        <div className="flex items-center gap-3 rounded-2xl bg-red-50 p-4 text-red-600">
          <AlertCircle className="h-5 w-5 shrink-0" />
          <p className="text-sm font-bold">تعذّر تحديث البيانات</p>
        </div>
      )}

      {/* Loading */}
      {loadingFlocks && flocks.length === 0 && (
        <div className="space-y-4 pt-2">
          <div className="h-28 animate-pulse rounded-2xl bg-slate-100" />
          <div className="h-20 animate-pulse rounded-2xl bg-slate-50" />
          <div className="h-40 animate-pulse rounded-2xl bg-slate-50" />
        </div>
      )}

      {/* Empty: No Farm */}
      {!loadingFlocks && !currentFarm && (
        <EmptyState title="لم تُحدَّد مزرعة" subtitle="اختر مزرعة من القائمة للمتابعة" />
      )}

      {/* Main Content */}
      {!loadingFlocks && currentFlock ? (
        <>
          {/* Flock Summary */}
          <FlockSummaryCard currentFlock={currentFlock} mortalityRate={mortalityRate} />

          {/* Draft Activation */}
          {draftFlock && (
            <div className="rounded-2xl bg-amber-50/80 border border-amber-100 p-5">
              <div className="flex items-start gap-3.5">
                <div className="w-10 h-10 rounded-xl bg-white flex items-center justify-center shrink-0 shadow-sm">
                  <Zap className="h-5 w-5 text-amber-500" />
                </div>
                <div className="flex-1 min-w-0">
                  <h3 className="font-extrabold text-amber-900 text-sm">الفوج في انتظار التفعيل</h3>
                  <p className="mt-1 text-[12px] font-medium text-amber-700/80 leading-relaxed">
                    فعّل الفوج للبدء في تسجيل البيانات التشغيلية.
                  </p>
                  {activateError && <p className="mt-2 text-[11px] font-bold text-red-600">{activateError}</p>}
                  <button 
                    onClick={handleActivate} 
                    disabled={activating} 
                    className="mt-3 inline-flex items-center justify-center rounded-xl bg-amber-600 px-5 py-2.5 text-xs font-bold text-white active:scale-[0.98] disabled:opacity-50 transition-all"
                  >
                    {activating ? 'جارٍ التفعيل...' : 'تفعيل الفوج'}
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* Active Flock Tools */}
          {isActive && (
            <div className="space-y-5">
              {/* Quick Entry — Primary Action Area */}
              <QuickEntryCard flockId={currentFlock.id} onSuccess={handleEntrySuccess} />

              {/* Operational Info */}
              <OperationalInfoCard ageDays={currentFlock.current_age_days} birdCount={currentFlock.remaining_count} />

              {/* Day Summary */}
              <DaySummaryCard summary={summary ?? emptyTodaySummary()} loading={loadingSummary} />
            </div>
          )}
        </>
      ) : (
        !loadingFlocks && !hasError && currentFarm && (
          <EmptyState title="لا توجد أفواج نشطة" subtitle="أنشئ فوجاً جديداً من صفحة الأفواج" />
        )
      )}
    </div>
  )
}

function EmptyState({ title, subtitle }: { title: string; subtitle: string }) {
  return (
    <div className="flex flex-col items-center justify-center rounded-3xl bg-slate-50/50 py-16 px-4 text-center">
      <div className="w-14 h-14 rounded-2xl bg-white flex items-center justify-center mb-4 shadow-sm border border-slate-100">
        <img src="/logo.png" alt="Logo" className="h-8 w-8 object-contain" />
      </div>
      <h3 className="text-sm font-extrabold text-slate-800">{title}</h3>
      <p className="mt-1.5 text-xs text-slate-400 font-medium max-w-[220px] leading-relaxed">{subtitle}</p>
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

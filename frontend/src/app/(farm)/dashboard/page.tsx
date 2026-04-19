'use client'

import { useState, useEffect } from 'react'
import { AlertCircle, Zap, Bird, Calendar } from 'lucide-react'
import { useQuery, keepPreviousData } from '@tanstack/react-query'
import { flocksApi } from '@/lib/api/flocks'
import { useFarmStore } from '@/stores/farm.store'
import { useLayoutStore } from '@/stores/layout.store'
import { useIsReadOnly } from '@/lib/roles'
import { WorkerProgressHeader } from '@/components/worker/WorkerProgressHeader'
import { WorkerGuidelinesCard } from '@/components/worker/WorkerGuidelinesCard'
import { WorkerTaskChecklist } from '@/components/worker/WorkerTaskChecklist'
import { WorkerHistoryList } from '@/components/worker/WorkerHistoryList'
import { WorkerEntryDialog } from '@/components/worker/WorkerEntryDialog'
import { DayEntriesModal, type DayEntryType } from '@/components/dashboard/DayEntriesModal'
import type { TodaySummary } from '@/types/dashboard'
import type { Flock } from '@/types/flock'

export default function DashboardPage() {
  const { currentFarm, activeFlock: cachedFlock, setActiveFlock } = useFarmStore()
  const { setPageTitle, setPageSubtitle } = useLayoutStore()
  const isReadOnly = useIsReadOnly()
  const [activating, setActivating] = useState(false)
  const [activateError, setActivateError] = useState<string | null>(null)

  const [activeEntryTab, setActiveEntryTab] = useState<'mortality' | 'feed' | 'medicine' | 'temp' | 'expense' | null>(null)
  const [entryExtra, setEntryExtra] = useState<Record<string, unknown> | null>(null)
  const [detailType, setDetailType] = useState<DayEntryType | null>(null)

  const getTodayISO = () => {
    const now = new Date()
    return now.getFullYear() + '-' +
           String(now.getMonth() + 1).padStart(2, '0') + '-' +
           String(now.getDate()).padStart(2, '0')
  }

  const [viewDate, setViewDate] = useState(getTodayISO())

  useEffect(() => {
    setPageTitle('الرئيسية')
    setPageSubtitle(currentFarm?.name || null)
  }, [currentFarm, setPageTitle, setPageSubtitle])

  // ── استخدم cachedFlock فوراً للقضاء على waterfall ───────────────────────────
  const isCacheValid = cachedFlock?.farm_id === currentFarm?.id

  const {
    data: flocks = [],
    isLoading: loadingFlocks,
    isError: hasError,
    refetch: refetchFlocks,
  } = useQuery<Flock[]>({
    queryKey: ['flocks', currentFarm?.id],
    queryFn: () => flocksApi.list().then((res): Flock[] => res.data),
    enabled: !!currentFarm,
    staleTime: 60_000,
    gcTime: 10 * 60 * 1000,
  })

  const activeFlock = flocks.find((f) => f.status === 'active') ?? null
  const draftFlock = !activeFlock ? (flocks.find((f) => f.status === 'draft') ?? null) : null

  // عرض البيانات المؤقتة من الـ cache أثناء تحميل الـ flocks
  const currentFlock = activeFlock ?? draftFlock
    ?? (isCacheValid && loadingFlocks ? cachedFlock : null)
  const isActive = currentFlock?.status === 'active'

  // حفظ الفوج النشط في الـ store لتسريع التحميل في المرة القادمة
  useEffect(() => {
    if (activeFlock) setActiveFlock(activeFlock)
    else if (!loadingFlocks && flocks.length > 0) setActiveFlock(null)
  }, [activeFlock, loadingFlocks])

  // activeFlockId متاح فوراً من الـ cache عند أول تحميل
  const activeFlockId = activeFlock?.id
    ?? (isCacheValid && cachedFlock?.status === 'active' ? cachedFlock.id : undefined)

  const {
    data: summary,
    isLoading: loadingSummary,
    refetch: refetchSummary,
  } = useQuery({
    queryKey: ['today-summary', activeFlockId, viewDate],
    queryFn: () => flocksApi.todaySummary(activeFlockId!, viewDate).then(res => res.data),
    enabled: !!activeFlockId,
    staleTime: 60_000,
    gcTime: 10 * 60 * 1000,
    placeholderData: keepPreviousData,
  })

  const {
    data: history,
    isLoading: loadingHistory,
    isRefetching: refetchingHistory,
    refetch: refetchHistory,
  } = useQuery({
    queryKey: ['flock-history', activeFlockId],
    queryFn: () => flocksApi.getHistory(activeFlockId!),
    enabled: !!activeFlockId,
    staleTime: 60_000,
    gcTime: 10 * 60 * 1000,
    placeholderData: keepPreviousData,
  })

  const handleEntrySuccess = () => {
    refetchSummary()
    refetchFlocks()
    refetchHistory()
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

  const handleTaskClick = (type: 'mortality' | 'feed' | 'medicine' | 'temp' | 'expense', extra?: Record<string, unknown>) => {
    setEntryExtra(extra ?? null)
    setActiveEntryTab(type)
  }

  const handleStatClick = (type: 'feed' | 'medicine' | 'mortality' | 'remaining' | 'expense') => {
    if (type === 'remaining') return
    if (isReadOnly) {
      setDetailType(type as DayEntryType)
    } else {
      setActiveEntryTab(type as 'mortality' | 'feed' | 'medicine' | 'expense')
    }
  }

  return (
    <div className="space-y-4 px-3 pt-3 pb-8" dir="rtl">
      
      {/* Error */}
      {hasError && (
        <div className="flex items-center gap-3 rounded-xl bg-red-50 p-4 text-red-600">
          <AlertCircle className="h-4 w-4 shrink-0" />
          <p className="text-xs font-bold">تعذّر تحديث البيانات</p>
        </div>
      )}

      {/* Loading */}
      {loadingFlocks && flocks.length === 0 && (
        <div className="space-y-4 pt-1">
          <div className="rounded-2xl bg-white border border-slate-100 p-4 animate-pulse space-y-3">
            <div className="flex items-center gap-3">
              <div className="h-9 w-9 rounded-xl bg-slate-100" />
              <div className="space-y-1.5 flex-1">
                <div className="h-3.5 w-20 bg-slate-100 rounded" />
                <div className="h-2.5 w-14 bg-slate-100 rounded" />
              </div>
              <div className="h-5 w-12 bg-slate-100 rounded-full" />
            </div>
            <div className="grid grid-cols-4 gap-2">
              {[1, 2, 3, 4].map(i => <div key={i} className="h-10 bg-slate-50 rounded-xl" />)}
            </div>
          </div>
          <div className="grid grid-cols-2 gap-2.5">
            {[1, 2, 3, 4].map(i => <div key={i} className="h-28 rounded-2xl bg-slate-50 animate-pulse" />)}
          </div>
        </div>
      )}

      {/* Empty: No Farm */}
      {!loadingFlocks && !currentFarm && (
        <EmptyState title="لم تُحدَّد مزرعة" subtitle="اختر مزرعة من القائمة للمتابعة" />
      )}

      {/* Main Content */}
      {!loadingFlocks && currentFlock ? (
        <>
          {/* Draft Activation */}
          {draftFlock && !isReadOnly && (
            <div className="rounded-2xl bg-emerald-50/80 border border-emerald-100 p-5">
              <div className="flex items-start gap-3.5">
                <div className="w-10 h-10 rounded-xl bg-white flex items-center justify-center shrink-0 shadow-sm">
                  <Zap className="h-5 w-5 text-emerald-500" />
                </div>
                <div className="flex-1 min-w-0">
                  <h3 className="font-extrabold text-emerald-900 text-sm">الفوج في انتظار التفعيل</h3>
                  <p className="mt-1 text-[12px] font-medium text-emerald-700/80 leading-relaxed">
                    فعّل الفوج للبدء في تسجيل البيانات التشغيلية.
                  </p>
                  {activateError && <p className="mt-2 text-[11px] font-bold text-red-600">{activateError}</p>}
                  <button 
                    onClick={handleActivate} 
                    disabled={activating} 
                    className="mt-3 inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white active:scale-[0.98] disabled:opacity-50 transition-all shadow-sm shadow-emerald-200"
                  >
                    {activating ? 'جارٍ التفعيل...' : 'تفعيل الفوج'}
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* Active Flock Tools */}
          {isActive && (
            <div className="space-y-4">
              {/* Date Selector */}
              <div className="flex items-center justify-between bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-3 rounded-2xl shadow-sm">
                <div className="flex items-center gap-2.5">
                  <div className="h-10 w-10 flex items-center justify-center rounded-xl bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400">
                    <Calendar className="h-5 w-5" />
                  </div>
                  <div>
                    <p className="text-[10px] font-bold text-slate-400 mb-0.5">عرض بيانات يوم</p>
                    <input 
                      type="date" 
                      value={viewDate}
                      max={getTodayISO()}
                      onChange={(e) => setViewDate(e.target.value)}
                      className="bg-transparent text-sm font-black text-slate-800 dark:text-slate-100 focus:outline-none cursor-pointer"
                    />
                  </div>
                </div>
                {viewDate !== getTodayISO() && (
                  <button 
                    onClick={() => setViewDate(getTodayISO())}
                    className="text-[10px] font-bold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-500/10 px-3 py-1.5 rounded-lg active:scale-95 transition-all"
                  >
                    العودة لليوم
                  </button>
                )}
              </div>

              {/* Stat Grid Header */}
              <WorkerProgressHeader 
                flock={currentFlock}
                summary={summary}
                isLoading={loadingSummary}
                viewDate={viewDate}
                role="manager"
                onStatClick={handleStatClick}
              />

              {/* Guidelines */}
              <WorkerGuidelinesCard 
                ageDays={currentFlock.current_age_days ?? 0} 
                birdCount={currentFlock.remaining_count} 
              />

              {/* Task Status */}
              <WorkerTaskChecklist
                summary={summary ?? emptyTodaySummary()}
                onTaskClick={isReadOnly ? undefined : (type) => handleTaskClick(type as any)}
              />

              {/* History */}
              <WorkerHistoryList
                history={history?.data ?? []}
                isLoading={loadingHistory}
                isRefreshing={refetchingHistory}
                role={isReadOnly ? 'worker' : 'manager'}
              />

              {/* Entry Dialog - hidden for read-only partners */}
              {!isReadOnly && (
                <WorkerEntryDialog
                  flockId={currentFlock.id}
                  activeTab={activeEntryTab}
                  initialExtra={entryExtra}
                  entryDate={viewDate}
                  onClose={() => setActiveEntryTab(null)}
                  onSuccess={handleEntrySuccess}
                />
              )}

              {/* Day Entries Detail Modal - shown when clicking stat cards */}
              {summary && (
                <DayEntriesModal
                  type={detailType}
                  date={viewDate}
                  summary={summary}
                  onClose={() => setDetailType(null)}
                />
              )}
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
    <div className="flex flex-col items-center justify-center rounded-[2rem] bg-slate-50/50 py-16 px-4 text-center border border-dashed border-slate-200">
      <div className="w-16 h-16 rounded-2xl bg-white flex items-center justify-center mb-5 shadow-sm border border-slate-100">
        <Bird className="h-8 w-8 text-slate-400" />
      </div>
      <h3 className="text-sm font-black text-slate-800">{title}</h3>
      <p className="mt-1.5 text-xs text-slate-500 font-medium max-w-[220px] leading-relaxed">{subtitle}</p>
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


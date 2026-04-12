// frontend/src/app/(farm)/worker/page.tsx
'use client'

import { useEffect, useState, useMemo } from 'react'
import { AlertCircle, Bird } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { flocksApi } from '@/lib/api/flocks'
import { useFarmStore } from '@/stores/farm.store'
import { useLayoutStore } from '@/stores/layout.store'
import { WorkerProgressHeader } from '@/components/worker/WorkerProgressHeader'
import { WorkerGuidelinesCard } from '@/components/worker/WorkerGuidelinesCard'
import { WorkerTaskChecklist } from '@/components/worker/WorkerTaskChecklist'
import { WorkerEntryDialog } from '@/components/worker/WorkerEntryDialog'
import { DaySummaryCard } from '@/components/dashboard/DaySummaryCard'
import type { TodaySummary } from '@/types/dashboard'

export default function WorkerPage() {
  const { currentFarm } = useFarmStore()
  const { setPageTitle, setPageSubtitle } = useLayoutStore()
  
  const [activeEntryTab, setActiveEntryTab] = useState<'mortality' | 'feed' | 'medicine' | null>(null)
  const [entryExtra, setEntryExtra] = useState<any>(null)

  useEffect(() => {
    setPageTitle('تسجيل الوردية')
    setPageSubtitle(currentFarm?.name || null)
  }, [currentFarm, setPageTitle, setPageSubtitle])

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

  const activeFlock = flocks.find((f: any) => f.status === 'active') ?? null
  const isActive = activeFlock !== null

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

  const handleTaskClick = (type: any, extra?: any) => {
    setEntryExtra(extra)
    setActiveEntryTab(type)
  }

  const progress = useMemo(() => {
    if (!summary) return { completed: 0, total: 2 }
    const tasks = [
      summary.mortalities.entries.length > 0,
      summary.feed.entries.length > 0,
      summary.medicines.entries.length > 0,
    ]
    return {
      completed: tasks.filter(Boolean).length,
      total: tasks.length
    }
  }, [summary])

  return (
    <div className="mx-auto max-w-2xl space-y-6 pb-24 pt-4 px-4 sm:px-0" dir="rtl">
      {hasError && (
        <div className="flex items-center gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 text-red-700">
          <AlertCircle className="h-5 w-5" />
          <p className="text-sm font-bold">تعذّر تحديث البيانات حالياً</p>
        </div>
      )}

      {loadingFlocks && flocks.length === 0 && (
        <div className="space-y-6">
          <div className="h-28 animate-pulse rounded-[2.5rem] bg-slate-100" />
          <div className="grid grid-cols-3 gap-4">
            {[1, 2, 3].map(i => <div key={i} className="h-16 animate-pulse rounded-2xl bg-slate-100" />)}
          </div>
          <div className="space-y-3">
            {[1, 2, 3, 4].map(i => <div key={i} className="h-20 animate-pulse rounded-3xl bg-slate-100" />)}
          </div>
        </div>
      )}

      {isActive && activeFlock && (
        <>
          <WorkerProgressHeader 
            completed={progress.completed} 
            total={progress.total} 
            flock={activeFlock ? {
              name: activeFlock.name,
              initial_count: activeFlock.initial_count,
              remaining_count: activeFlock.remaining_count,
              current_age_days: activeFlock.current_age_days ?? 0,
              start_date: activeFlock.start_date,
            } : undefined}
          />

          <WorkerGuidelinesCard 
            ageDays={activeFlock.current_age_days ?? 0} 
            birdCount={activeFlock.remaining_count} 
          />

          <div className="space-y-2">
            <h3 className="px-2 text-xs font-black uppercase tracking-[0.2em] text-slate-400">قائمة المهام اليومية</h3>
            <WorkerTaskChecklist 
              summary={summary ?? emptyTodaySummary()} 
              onTaskClick={handleTaskClick} 
            />
          </div>
          
          {summary && (summary.mortalities.total > 0 || summary.feed.total > 0 || summary.temperatures.entries.length > 0) && (
            <DaySummaryCard 
              summary={summary} 
              loading={loadingSummary} 
            />
          )}

          <WorkerEntryDialog 
            flockId={activeFlock.id}
            activeTab={activeEntryTab}
            initialExtra={entryExtra}
            onClose={() => setActiveEntryTab(null)}
            onSuccess={handleEntrySuccess}
          />
        </>
      )}

      {!loadingFlocks && !isActive && (
        <div className="flex flex-col items-center justify-center rounded-[2.5rem] border border-dashed border-slate-200 bg-white py-24 px-6 text-center shadow-sm">
          <div className="w-20 h-20 rounded-3xl bg-slate-50 flex items-center justify-center mb-6 shadow-inner">
            <Bird className="h-10 w-10 text-slate-300" />
          </div>
          <h3 className="text-lg font-black text-slate-900 tracking-tight">لا يوجد فوج نشط</h3>
          <p className="mt-2 text-sm text-slate-500 font-medium max-w-[280px] leading-relaxed">
            بانتظار تفعيل فوج جديد من قبل الإدارة للبدء في المهام اليومية
          </p>
        </div>
      )}
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

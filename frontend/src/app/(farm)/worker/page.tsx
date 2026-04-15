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
import { WorkerHistoryList } from '@/components/worker/WorkerHistoryList'
import { WorkerEntryDialog } from '@/components/worker/WorkerEntryDialog'
import type { TodaySummary } from '@/types/dashboard'
import type { Flock } from '@/types/flock'

export default function WorkerPage() {
  const { currentFarm } = useFarmStore()
  const { setPageTitle, setPageSubtitle } = useLayoutStore()

  const [activeEntryTab, setActiveEntryTab] = useState<'mortality' | 'feed' | 'medicine' | null>(null)
  const [entryExtra, setEntryExtra] = useState<Record<string, unknown> | null>(null)

  useEffect(() => {
    setPageTitle('لوحة العامل')
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
    staleTime: 5000, 
    gcTime: 10 * 60 * 1000,
  })

  const activeFlock = flocks.find((f) => f.status === 'active') ?? null
  const isActive = activeFlock !== null

  const now = new Date()
  const todayDate = now.getFullYear() + '-' +
                    String(now.getMonth() + 1).padStart(2, '0') + '-' +
                    String(now.getDate()).padStart(2, '0')

  const {
    data: summary,
    isLoading: isSummaryLoading,
    refetch: refetchSummary,
  } = useQuery({
    queryKey: ['today-summary', activeFlock?.id, todayDate],
    queryFn: () => flocksApi.todaySummary(activeFlock!.id, todayDate).then(res => res.data),
    enabled: !!activeFlock,
    refetchInterval: 10_000,
    staleTime: 5000,
    gcTime: 10 * 60 * 1000,
    refetchOnWindowFocus: true,
  })

  const {
    data: history,
    isLoading: loadingHistory,
    isRefetching: refetchingHistory,
    refetch: refetchHistory,
  } = useQuery({
    queryKey: ['flock-history', activeFlock?.id],
    queryFn: () => flocksApi.getHistory(activeFlock!.id),
    enabled: !!activeFlock,
    refetchInterval: 10_000,
    staleTime: 5000,
    gcTime: 10 * 60 * 1000,
    refetchOnWindowFocus: true,
  })


  const handleEntrySuccess = () => {
    refetchSummary()
    refetchFlocks()
    refetchHistory()
  }

  const handleTaskClick = (type: 'mortality' | 'feed' | 'medicine', extra?: Record<string, unknown>) => {
    setEntryExtra(extra ?? null)
    setActiveEntryTab(type)
  }

  const handleStatClick = (type: 'feed' | 'medicine' | 'mortality' | 'remaining') => {
    if (type === 'remaining') {
      handleTaskClick('temp')
      return
    }
    handleTaskClick(type)
  }

  return (
    <div className="space-y-4 px-3 pt-3 pb-6" dir="rtl">
      {/* Error */}
      {hasError && (
        <div className="flex items-center gap-2 rounded-xl bg-red-50 p-3 text-red-600">
          <AlertCircle className="h-4 w-4 shrink-0" />
          <p className="text-xs font-bold">تعذّر تحديث البيانات</p>
        </div>
      )}

      {/* Initial Loading Skeleton */}
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

          <div className="flex gap-2.5 overflow-hidden">
            {[1, 2, 3].map(i => <div key={i} className="min-w-[140px] h-28 rounded-2xl bg-slate-50 animate-pulse" />)}
          </div>
        </div>
      )}

      {/* Active View */}
      {isActive && activeFlock && (
        <div className="space-y-4">
          {/* 1. Stat Grid Header */}
          <WorkerProgressHeader 
            flock={activeFlock}
            summary={summary}
            isLoading={isSummaryLoading}
            viewDate={todayDate}
            onStatClick={handleStatClick}
          />

          {/* 2. Guidelines */}
          <WorkerGuidelinesCard 
            ageDays={activeFlock.current_age_days ?? 0} 
            birdCount={activeFlock.remaining_count} 
          />

          {/* 3. Task Status */}
          <WorkerTaskChecklist 
            summary={summary ?? emptyTodaySummary()} 
            onTaskClick={() => {}}
          />

          {/* 4. History */}
          <WorkerHistoryList 
            history={history?.data ?? []} 
            isLoading={loadingHistory}
            isRefreshing={refetchingHistory}
          />
          
          {/* Entry Dialog */}
          <WorkerEntryDialog 
            flockId={activeFlock.id}
            activeTab={activeEntryTab}
            initialExtra={entryExtra}
            onClose={() => setActiveEntryTab(null)}
            onSuccess={handleEntrySuccess}
          />
        </div>
      )}

      {/* Empty State */}
      {!loadingFlocks && !isActive && (
        <div className="flex flex-col items-center justify-center rounded-[2rem] bg-emerald-50/50 py-16 px-6 text-center border border-dashed border-emerald-100">
          <div className="w-16 h-16 rounded-2xl bg-white flex items-center justify-center mb-5 shadow-sm border border-emerald-50">
            <Bird className="h-8 w-8 text-emerald-500" />
          </div>
          <h3 className="text-base font-black text-emerald-950">لا يوجد فوج نشط متاح</h3>
          <p className="mt-2 text-xs text-emerald-600/70 font-medium max-w-[240px] leading-relaxed">
            عند تفعيل الفوج من قبل الإدارة، ستتمكن من تسجيل البيانات اليومية هنا.
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

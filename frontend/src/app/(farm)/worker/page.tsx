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
  })

  const activeFlock = flocks.find((f) => f.status === 'active') ?? null
  const isActive = activeFlock !== null

  const todayDate = new Date().getFullYear() + '-' + 
                    String(new Date().getMonth() + 1).padStart(2, '0') + '-' + 
                    String(new Date().getDate()).padStart(2, '0')

  const {
    data: summary,
    isLoading: isSummaryLoading,
    refetch: refetchSummary,
  } = useQuery({
    queryKey: ['today-summary', activeFlock?.id, todayDate],
    queryFn: () => flocksApi.todaySummary(activeFlock!.id, todayDate).then(res => res.data),
    enabled: !!activeFlock,
    refetchInterval: 30_000,
  })

  const handleEntrySuccess = () => {
    refetchSummary()
    refetchFlocks()
  }

  const handleTaskClick = (type: 'mortality' | 'feed' | 'medicine', extra?: Record<string, unknown>) => {
    setEntryExtra(extra ?? null)
    setActiveEntryTab(type)
  }

  const progress = useMemo(() => {
    if (!summary) return { completed: 0, total: 3 }
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

  const handleStatClick = (type: 'feed' | 'medicine' | 'mortality' | 'remaining') => {
    if (type === 'remaining') return
    handleTaskClick(type)
  }

  return (
    <div className="space-y-6 px-5 pt-5 pb-8" dir="rtl">
      {/* ── Error Notification ── */}
      {hasError && (
        <div className="flex items-center gap-3 rounded-2xl bg-red-50 p-4 text-red-600">
          <AlertCircle className="h-5 w-5 shrink-0" />
          <p className="text-sm font-bold">تعذّر تحديث البيانات</p>
        </div>
      )}

      {/* ── Loading Skeleton ── */}
      {loadingFlocks && flocks.length === 0 && (
        <div className="space-y-5 pt-2">
          <div className="h-44 animate-pulse rounded-[2rem] bg-slate-100" />
          <div className="h-32 animate-pulse rounded-[2rem] bg-slate-50" />
          <div className="space-y-3 pt-4">
            {[1, 2, 3].map(i => <div key={i} className="h-20 animate-pulse rounded-2xl bg-slate-50" />)}
          </div>
        </div>
      )}

      {/* ── Active Worker View (Stitch Ordered Layout) ── */}
      {isActive && activeFlock && (
        <div className="space-y-6">
          {/* 1. Stat Grid Header (Clickable Entry Points) */}
          <WorkerProgressHeader 
            flock={{
              name: activeFlock.name,
              initial_count: activeFlock.initial_count,
              remaining_count: activeFlock.remaining_count,
              current_age_days: activeFlock.current_age_days ?? 0,
              start_date: activeFlock.start_date,
            }}
            summary={summary}
            isLoading={isSummaryLoading}
            viewDate={todayDate}
            onStatClick={handleStatClick}
          />

          {/* 2. Operational Guidelines Banner */}
          <WorkerGuidelinesCard 
            ageDays={activeFlock.current_age_days ?? 0} 
            birdCount={activeFlock.remaining_count} 
          />

          {/* 3. Task Status Summary Card */}
          <WorkerTaskChecklist 
            summary={summary ?? emptyTodaySummary()} 
            onTaskClick={() => {}} // Could link to a more detailed checklist page later
          />
          
          {/* Entry Dialog (Logical layer) */}
          <WorkerEntryDialog 
            flockId={activeFlock.id}
            activeTab={activeEntryTab}
            initialExtra={entryExtra}
            onClose={() => setActiveEntryTab(null)}
            onSuccess={handleEntrySuccess}
          />
        </div>
      )}

      {/* ── Empty State ── */}
      {!loadingFlocks && !isActive && (
        <div className="flex flex-col items-center justify-center rounded-3xl bg-slate-50/50 py-20 px-6 text-center border-2 border-dashed border-slate-200">
          <div className="w-16 h-16 rounded-3xl bg-white flex items-center justify-center mb-5 shadow-sm border border-slate-100">
            <Bird className="h-8 w-8 text-slate-300" />
          </div>
          <h3 className="text-base font-extrabold text-slate-800">لا يوجد فوج نشط متاح</h3>
          <p className="mt-2 text-sm text-slate-400 font-medium max-w-[260px] leading-relaxed">
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


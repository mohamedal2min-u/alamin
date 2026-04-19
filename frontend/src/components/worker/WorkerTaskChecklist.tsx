'use client'

import { ChevronLeft, CheckCircle2, Circle } from 'lucide-react'
import { TodaySummary } from '@/types/dashboard'
import { useMemo } from 'react'
import { cn } from '@/lib/utils'

interface Props {
  summary: TodaySummary
  onTaskClick?: (type: string) => void
}

export function WorkerTaskChecklist({ summary, onTaskClick }: Props) {
  const tasks = useMemo(() => {
    return [
      { label: 'النفوق', done: summary.mortalities.entries.length > 0, count: summary.mortalities.total },
      { label: 'العلف', done: summary.feed.entries.length > 0, count: summary.feed.total },
      { label: 'الأدوية', done: summary.medicines.entries.length > 0, count: summary.medicines.total },
    ]
  }, [summary])

  const completed = tasks.filter(t => t.done).length
  const total = tasks.length
  const percent = Math.round((completed / total) * 100)

  return (
    <button 
      onClick={onTaskClick ? () => onTaskClick('summary') : undefined}
      className="w-full flex items-center justify-between p-4 bg-white rounded-2xl shadow-sm border border-slate-100 active:scale-[0.98] touch-manipulation focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 transition-all"
      dir="rtl"
    >
      <div className="flex items-center gap-3.5">
        {/* Progress Ring */}
        <div className="relative h-12 w-12 shrink-0">
          <svg className="h-full w-full -rotate-90" viewBox="0 0 36 36">
            <circle cx="18" cy="18" r="15" fill="none" className="stroke-slate-100" strokeWidth="3" />
            <circle 
              cx="18" cy="18" r="15" fill="none"
              className={cn(
                percent === 100 ? "stroke-primary-500" : "stroke-amber-400"
              )}
              strokeWidth="4"
              strokeDasharray={`${percent}, 100`}
              strokeLinecap="round"
            />
          </svg>
          <div className="absolute inset-0 flex items-center justify-center">
            <span className="text-[10px] font-black text-primary-950 tabular-nums">{percent}%</span>
          </div>
        </div>

        <div className="text-right">
          <h3 className="text-sm font-black text-primary-950 leading-tight">حالة المهام اليومية</h3>
          <div className="flex items-center gap-2 mt-1.5">
            {tasks.map((task) => (
              <div key={task.label} className="flex items-center gap-0.5">
                {task.done ? (
                  <CheckCircle2 className="h-3.5 w-3.5 text-primary-500" />
                ) : (
                  <Circle className="h-3.5 w-3.5 text-slate-300" />
                )}
                <span className={cn(
                  "text-[9px] font-bold",
                  task.done ? "text-primary-600" : "text-slate-400"
                )}>
                  {task.label}
                </span>
              </div>
            ))}
          </div>
        </div>
      </div>

      <div className="h-8 w-8 flex items-center justify-center rounded-xl bg-slate-50 text-slate-400">
        <ChevronLeft className="h-4 w-4" />
      </div>
    </button>
  )
}


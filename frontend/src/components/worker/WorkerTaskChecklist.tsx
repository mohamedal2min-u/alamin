'use client'

import { ChevronLeft } from 'lucide-react'
import { TodaySummary } from '@/types/dashboard'
import { useMemo } from 'react'

interface Props {
  summary: TodaySummary
  onTaskClick: (type: string) => void
}

export function WorkerTaskChecklist({ summary, onTaskClick }: Props) {
  const progress = useMemo(() => {
    const tasks = [
      summary.mortalities.entries.length > 0,
      summary.feed.entries.length > 0,
      summary.medicines.entries.length > 0,
    ]
    const completed = tasks.filter(Boolean).length
    const total = 3
    return {
      completed,
      total,
      percent: Math.round((completed / total) * 100)
    }
  }, [summary])

  return (
    <div className="space-y-4">
      {/* ── Stitch Progress Summary Card ── */}
      <button 
        onClick={() => onTaskClick('summary')}
        className="w-full flex items-center justify-between p-4 bg-white rounded-[2rem] shadow-sm border border-slate-50 transition-all active:scale-[0.98]"
      >
        <div className="flex items-center gap-4">
          {/* Circular Progress Ring (Smaller, Stitch size) */}
          <div className="relative h-14 w-14">
            <svg className="h-full w-full -rotate-90 transform">
              <circle
                cx="28"
                cy="28"
                r="24"
                stroke="currentColor"
                strokeWidth="4"
                fill="transparent"
                className="text-slate-50"
              />
              <circle
                cx="28"
                cy="28"
                r="24"
                stroke="currentColor"
                strokeWidth="4"
                fill="transparent"
                strokeDasharray={2 * Math.PI * 24}
                strokeDashoffset={2 * Math.PI * 24 * (1 - progress.percent / 100)}
                strokeLinecap="round"
                className="text-emerald-500 transition-all duration-500"
              />
            </svg>
            <div className="absolute inset-0 flex items-center justify-center">
              <span className="text-[11px] font-black text-slate-900">{progress.percent}%</span>
            </div>
          </div>

          <div className="text-right">
            <h3 className="text-[15px] font-black text-slate-900 leading-none mb-1">حالة المهام اليومية</h3>
            <p className="text-[11px] font-bold text-slate-400">
              تم إنجاز {progress.completed} من أصل {progress.total} مهمة
            </p>
          </div>
        </div>

        {/* Back Arrow Button (Stitch style) */}
        <div className="h-10 w-10 flex items-center justify-center rounded-2xl bg-slate-50 text-slate-400">
          <ChevronLeft className="h-5 w-5" />
        </div>
      </button>
    </div>
  )
}

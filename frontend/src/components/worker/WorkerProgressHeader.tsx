'use client'

import { cn } from '@/lib/utils'
import { Bird, Calendar } from 'lucide-react'

interface Props {
  completed: number
  total: number
  flock?: {
    name: string
    initial_count: number
    remaining_count: number
    current_age_days: number
    start_date: string
  }
}

export function WorkerProgressHeader({ completed, total, flock }: Props) {
  const percentage = Math.round((completed / total) * 100)
  const isComplete = completed >= total

  return (
    <div className="space-y-4">
      {/* ── Flock Info Bar ── */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2.5">
          <div className="h-10 w-10 rounded-2xl bg-emerald-50 flex items-center justify-center">
            <Bird className="h-5 w-5 text-emerald-600" />
          </div>
          <div>
            <h2 className="text-[15px] font-extrabold text-slate-900 leading-tight">
              {flock?.name ?? 'الفوج النشط'}
            </h2>
            <div className="flex items-center gap-3 mt-0.5">
              <span className="text-[11px] font-bold text-slate-400">
                يوم <span className="text-emerald-600 font-extrabold">{flock?.current_age_days ?? 0}</span>
              </span>
              <span className="text-slate-200">|</span>
              <span className="text-[11px] font-bold text-slate-400">
                متبقي <span className="text-slate-700 font-extrabold">{flock?.remaining_count ?? 0}</span>
              </span>
            </div>
          </div>
        </div>
        
        {/* Completion Badge */}
        <div className={cn(
          "flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-[11px] font-extrabold tabular-nums",
          isComplete 
            ? "bg-emerald-50 text-emerald-700 border border-emerald-100" 
            : "bg-slate-50 text-slate-500 border border-slate-100"
        )}>
          {completed}/{total}
          {isComplete && <span className="text-emerald-500">✓</span>}
        </div>
      </div>

      {/* ── Progress Bar ── */}
      <div className="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
        <div 
          className={cn(
            "h-full rounded-full transition-all duration-700 ease-out",
            isComplete ? "bg-emerald-500" : "bg-emerald-400"
          )}
          style={{ width: `${percentage}%` }}
        />
      </div>
    </div>
  )
}

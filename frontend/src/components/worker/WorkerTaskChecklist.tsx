'use client'

import { Fragment } from 'react'
import { 
  Skull, Wheat, Syringe, 
  CheckCircle2, ChevronLeft
} from 'lucide-react'
import { cn } from '@/lib/utils'
import type { TodaySummary } from '@/types/dashboard'

interface Props {
  summary: TodaySummary
  onTaskClick: (type: 'mortality' | 'feed' | 'medicine', extra?: any) => void
}

export function WorkerTaskChecklist({ summary, onTaskClick }: Props) {
  const getMortalityDetail = () => {
    if (summary.mortalities.entries.length === 0) return null
    return (
      <span className="text-[13px] font-extrabold text-slate-700 tabular-nums">
        {summary.mortalities.total} طير
      </span>
    )
  }

  const getFeedDetail = () => {
    if (summary.feed.entries.length === 0) return null
    const unit = summary.feed.entries[0]?.unit_label || 'كيس'
    return (
      <span className="text-[13px] font-extrabold text-slate-700 tabular-nums">
        {summary.feed.total} {unit}
      </span>
    )
  }

  const getMedicineDetail = () => {
    if (summary.medicines.entries.length === 0) return null
    return (
      <div className="flex flex-wrap items-center gap-1.5">
        {summary.medicines.entries.map((e, i) => (
          <span key={i} className="text-[12px] font-bold text-slate-600">
            {e.item_name}
            <span className="text-emerald-600 font-extrabold mr-1">{e.quantity}</span>
            {i < summary.medicines.entries.length - 1 && <span className="text-slate-300 mx-0.5">،</span>}
          </span>
        ))}
      </div>
    )
  }

  const tasks = [
    {
      id: 'mortality',
      label: 'النفوق',
      subtitle: 'سجل الطيور النافقة',
      detail: getMortalityDetail(),
      icon: Skull,
      accentColor: 'bg-rose-500',
      iconBg: 'bg-rose-50',
      iconColor: 'text-rose-600',
      isDone: summary.mortalities.entries.length > 0,
    },
    {
      id: 'feed',
      label: 'العلف',
      subtitle: 'سجل كمية العلف المستخدم',
      detail: getFeedDetail(),
      icon: Wheat,
      accentColor: 'bg-blue-500',
      iconBg: 'bg-blue-50',
      iconColor: 'text-blue-600',
      isDone: summary.feed.entries.length > 0,
    },
    {
      id: 'medicine',
      label: 'الأدوية',
      subtitle: 'سجل الأدوية والتحصينات',
      detail: getMedicineDetail(),
      icon: Syringe,
      accentColor: 'bg-emerald-500',
      iconBg: 'bg-emerald-50',
      iconColor: 'text-emerald-600',
      isDone: summary.medicines.entries.length > 0,
    }
  ]

  return (
    <div className="space-y-2.5">
      {tasks.map((task) => (
        <button
          key={task.id}
          onClick={() => onTaskClick(task.id as any)}
          className={cn(
            "flex w-full items-center gap-4 rounded-2xl p-4 text-right transition-all duration-200 active:scale-[0.98]",
            task.isDone 
              ? "bg-slate-50/80" 
              : "bg-white border border-slate-100 shadow-sm"
          )}
        >
          {/* Icon */}
          <div className={cn(
            "relative flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl transition-all",
            task.isDone ? "bg-slate-100" : task.iconBg
          )}>
            <task.icon className={cn(
              "h-5 w-5",
              task.isDone ? "text-slate-400" : task.iconColor
            )} />
            {/* Done check overlay */}
            {task.isDone && (
              <div className="absolute -bottom-1 -left-1 h-5 w-5 rounded-full bg-emerald-500 flex items-center justify-center shadow-sm">
                <CheckCircle2 className="h-3.5 w-3.5 text-white" />
              </div>
            )}
          </div>

          {/* Content */}
          <div className="flex-1 min-w-0">
            <h4 className={cn(
              "text-sm font-extrabold",
              task.isDone ? "text-slate-400" : "text-slate-900"
            )}>
              {task.label}
            </h4>
            {task.isDone && task.detail ? (
              <div className="mt-0.5">{task.detail}</div>
            ) : (
              <p className="text-[11px] font-medium text-slate-400 mt-0.5">
                {task.subtitle}
              </p>
            )}
          </div>

          {/* Action Arrow */}
          <ChevronLeft className={cn(
            "h-5 w-5 shrink-0",
            task.isDone ? "text-slate-200" : "text-slate-300"
          )} />
        </button>
      ))}
    </div>
  )
}

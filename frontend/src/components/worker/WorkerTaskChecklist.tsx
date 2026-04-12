'use client'

import { useState, Fragment } from 'react'
import { 
  Skull, Wheat, Syringe, 
  CheckCircle2, ArrowLeft
} from 'lucide-react'
import { cn } from '@/lib/utils'
import type { TodaySummary } from '@/types/dashboard'

interface Props {
  summary: TodaySummary
  onTaskClick: (type: 'mortality' | 'feed' | 'medicine', extra?: any) => void
}

export function WorkerTaskChecklist({ summary, onTaskClick }: Props) {
  // Helpers to format details with colors
  const getMortalityDetail = () => {
    if (summary.mortalities.entries.length === 0) return null
    const parts = summary.mortalities.entries.map(e => e.quantity)
    const total = summary.mortalities.total
    
    return (
      <div className="flex items-center gap-1 font-black">
        {parts.map((p, i) => (
          <Fragment key={i}>
            <span className="text-slate-600">{p}</span>
            {i < parts.length - 1 && <span className="text-slate-300 mx-0.5">+</span>}
          </Fragment>
        ))}
        {parts.length > 1 && (
          <>
            <span className="text-slate-300 mx-0.5">=</span>
            <span className="text-emerald-600">{total}</span>
          </>
        )}
      </div>
    )
  }

  const getFeedDetail = () => {
    if (summary.feed.entries.length === 0) return null
    const parts = summary.feed.entries.map(e => e.quantity)
    const total = summary.feed.total
    const unit = summary.feed.entries[0]?.unit_label || 'كيس'
    
    return (
      <div className="flex items-center gap-1 font-black">
        {parts.map((p, i) => (
          <Fragment key={i}>
            <span className="text-slate-600">{p}</span>
            {i < parts.length - 1 && <span className="text-slate-300 mx-0.5">+</span>}
          </Fragment>
        ))}
        <span className="text-slate-300 mx-0.5">=</span>
        <span className="text-emerald-600">{total}</span>
        <span className="text-slate-400 text-[10px] mr-1">{unit}</span>
      </div>
    )
  }

  const getMedicineDetail = () => {
    if (summary.medicines.entries.length === 0) return null
    return (
      <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5">
        {summary.medicines.entries.map((e, i) => (
          <div key={i} className="flex items-center gap-1">
            <span className="text-slate-500 font-bold">{e.item_name}</span>
            <span className="flex h-5 items-center justify-center rounded-md bg-emerald-50 px-1.5 text-[10px] font-black text-emerald-600 border border-emerald-100/50">
              {e.quantity}
            </span>
            {i < summary.medicines.entries.length - 1 && <span className="text-slate-200">،</span>}
          </div>
        ))}
      </div>
    )
  }

  const tasks = [
    {
      id: 'mortality',
      label: 'تسجيل النفوق اليومي',
      description: 'سجل أي طيور نافقة تم استبعادها اليوم',
      detail: getMortalityDetail(),
      icon: Skull,
      color: 'rose',
      isDone: summary.mortalities.entries.length > 0,
    },
    {
      id: 'feed',
      label: 'تسجيل استهلاك العلف',
      description: 'أدخل عدد الأكياس أو الكيلوات المستخدمة',
      detail: getFeedDetail(),
      icon: Wheat,
      color: 'blue',
      isDone: summary.feed.entries.length > 0,
    },
    {
      id: 'medicine',
      label: 'الأدوية والتحصينات',
      description: 'سجل أي إضافات للأعلاف أو للمياه',
      detail: getMedicineDetail(),
      icon: Syringe,
      color: 'emerald',
      isDone: summary.medicines.entries.length > 0,
    }
  ]

  return (
    <div className="space-y-3">
      {tasks.map((task) => (
        <ChecklistItem 
          key={task.id} 
          {...task} 
          onClick={() => onTaskClick(task.id as any)} 
        />
      ))}
    </div>
  )
}

function ChecklistItem({ label, description, detail, icon: Icon, color, isDone, onClick }: any) {
  const colorMap: any = {
    rose:    'text-rose-600 bg-rose-50 border-rose-100',
    blue:    'text-blue-600 bg-blue-50 border-blue-100',
    amber:   'text-amber-600 bg-amber-50 border-amber-100',
    orange:  'text-orange-600 bg-orange-50 border-orange-100',
    indigo:  'text-indigo-600 bg-indigo-50 border-indigo-100',
    emerald: 'text-emerald-600 bg-emerald-50 border-emerald-100',
  }

  return (
    <button
      onClick={onClick}
      className={cn(
        "flex w-full items-center gap-4 rounded-3xl border p-4 text-right transition-all duration-300 active:scale-[0.98]",
        isDone 
          ? "bg-white border-slate-200" 
          : "bg-white border-slate-200 shadow-sm hover:border-emerald-300 hover:shadow-md"
      )}
    >
      <div className={cn("flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border transition-colors", 
        isDone ? "bg-slate-50 border-slate-100 text-slate-400" : colorMap[color]
      )}>
        <Icon className="h-6 w-6" />
      </div>

      <div className="flex-1 space-y-0.5 overflow-hidden">
        <h4 className={cn("text-[13px] font-black tracking-tight", 
          isDone ? "text-slate-400" : "text-slate-900"
        )}>
          {label}
        </h4>
        
        {isDone && detail ? (
          <div className="flex items-center gap-1.5 animate-in fade-in slide-in-from-right-1 duration-500">
            {detail}
          </div>
        ) : (
          <p className="text-[10px] font-bold text-slate-400 truncate max-w-[200px]">
            {description}
          </p>
        )}
      </div>

      <div className="flex items-center gap-2">
        {isDone ? (
          <div className="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
            <CheckCircle2 className="h-5 w-5" />
          </div>
        ) : (
          <div className="flex items-center gap-1 rounded-full bg-slate-50 px-3 py-1.5 text-slate-400 group-hover:bg-emerald-50 group-hover:text-emerald-600">
            <span className="text-[10px] font-black uppercase tracking-wider text-[9px]">سجل الآن</span>
            <ArrowLeft className="h-3 w-3" />
          </div>
        )}
      </div>
    </button>
  )
}

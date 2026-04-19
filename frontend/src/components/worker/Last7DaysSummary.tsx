'use client'

import { CalendarDays } from 'lucide-react'

interface DailySummary {
  day: number
  mortality: number
  feed: number
  performance: number // Percentage 1-100
}

interface Props {
  data: DailySummary[]
}

export function Last7DaysSummary({ data }: Props) {
  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between px-1">
        <div className="flex items-center gap-2">
          <CalendarDays className="h-4 w-4 text-slate-400" />
          <h4 className="text-sm font-black text-slate-900">أداء آخر 7 أيام</h4>
        </div>
        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
          التاريخ الإحصائي
        </span>
      </div>

      <div className="space-y-2">
        {data.map((item, i) => (
          <div 
            key={i} 
            className="flex items-center gap-4 rounded-2xl bg-slate-50 p-3 decoration-neutral-100"
          >
            <div className="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-xl bg-white shadow-sm ring-1 ring-black/[0.02]">
              <span className="text-[10px] font-bold text-slate-400 leading-none">يوم</span>
              <span className="text-sm font-black text-slate-900 leading-none mt-0.5">{item.day}</span>
            </div>
            
            <div className="flex-1 space-y-1.5">
              <div className="flex justify-between">
                <div className="flex gap-3">
                  <span className="text-[11px] font-bold text-slate-500">
                    نفوق: <span className="text-red-500">{item.mortality}</span>
                  </span>
                  <span className="text-[11px] font-bold text-slate-500">
                    علف: <span className="text-amber-600">{item.feed}</span>
                  </span>
                </div>
                <span className="text-[11px] font-extrabold text-primary-600">
                  {item.performance}%
                </span>
              </div>
              <div className="h-1 w-full overflow-hidden rounded-full bg-white ring-1 ring-black/[0.02]">
                <div 
                  className="h-full bg-primary-500 rounded-full transition-all duration-500"
                  style={{ width: `${item.performance}%` }}
                />
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}


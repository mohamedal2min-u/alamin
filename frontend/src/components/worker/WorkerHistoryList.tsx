'use client'

import React, { useState } from 'react'
import { ChevronDown, Bird, Wheat, Pill, Activity } from 'lucide-react'
import { HistoryDetailDialog } from './HistoryDetailDialog'
import { cn } from '@/lib/utils'

interface HistoryDay {
  date: string
  age_days: number
  age_label: string
  stats: {
    mortality: number
    feed: number
    medicine_count: number
    completion_rate: number
  }
  timeline: any[]
}

interface Props {
  history: HistoryDay[]
  isLoading?: boolean
  isRefreshing?: boolean
}

export function WorkerHistoryList({ history, isLoading, isRefreshing }: Props) {
  const [expanded, setExpanded] = useState(false)
  const [selectedDay, setSelectedDay] = useState<HistoryDay | null>(null)

  const visibleDays = expanded ? history : history.slice(0, 1)

  if (isLoading) {
    return (
      <div className="space-y-2">
        {[1, 2].map(i => (
          <div key={i} className="h-20 w-full animate-pulse rounded-2xl bg-slate-50" />
        ))}
      </div>
    )
  }

  if (!history || history.length === 0) return null

  return (
    <div className="space-y-3" dir="rtl">
      {/* Section Header */}
      <div className="flex items-center justify-between px-1">
        <div className="flex items-center gap-2">
          <Activity className="h-3.5 w-3.5 text-emerald-500" />
          <h3 className="text-[13px] font-black text-emerald-950">سجل آخر 7 أيام</h3>
        </div>
        {isRefreshing && (
          <div className="flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-100 animate-pulse">
            <span className="w-1 h-1 rounded-full bg-emerald-500" />
            <span className="text-[8px] font-bold text-emerald-600">مزامنة</span>
          </div>
        )}
      </div>

      {/* Day Cards */}
      <div className="space-y-2">
        {visibleDays.map((day, idx) => {
          const isToday = idx === 0 && day.date === new Date().toISOString().split('T')[0]
          return (
            <div 
              key={day.date}
              onClick={() => setSelectedDay(day)}
              className={cn(
                "flex items-center justify-between border p-3.5 rounded-[1.25rem] cursor-pointer active:scale-[0.98] touch-manipulation focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all",
                isToday 
                  ? "bg-emerald-50/50 border-emerald-100" 
                  : "bg-white border-emerald-50 hover:border-emerald-100"
              )}
            >
              {/* Left: Info */}
              <div className="flex flex-col gap-1.5 min-w-0">
                <div className="flex items-center gap-2">
                  <span className={cn(
                    "text-xs font-black",
                    isToday ? "text-emerald-700" : "text-emerald-950"
                  )}>
                    {isToday ? "ملخص اليوم" : day.age_label}
                  </span>
                  {!isToday && (
                    <>
                      <div className="h-1 w-1 rounded-full bg-slate-300" />
                      <span className="text-[9px] font-bold text-slate-400 tabular-nums">{day.date}</span>
                    </>
                  )}
                  {isToday && (
                    <span className="bg-emerald-500/10 text-emerald-600 px-1.5 py-0.5 rounded text-[8px] font-black">
                      جارٍ
                    </span>
                  )}
                </div>
                <div className="flex items-center gap-3">
                  <StatMini icon={<Bird />} value={day.stats.mortality} color="red" />
                  <StatMini icon={<Wheat />} value={day.stats.feed} color="amber" />
                  <StatMini icon={<Pill />} value={day.stats.medicine_count} color="indigo" />
                </div>
              </div>

              {/* Right: Completion Ring */}
              <div className="relative h-11 w-11 shrink-0">
                <svg className="h-full w-full" viewBox="0 0 36 36">
                  <circle cx="18" cy="18" r="15" fill="none" className="stroke-slate-100" strokeWidth="3" />
                  <circle 
                    cx="18" cy="18" r="15" fill="none" 
                    className={day.stats.completion_rate === 100 ? "stroke-emerald-500" : "stroke-amber-400"}
                    strokeWidth="3" 
                    strokeDasharray={`${day.stats.completion_rate}, 100`}
                    strokeLinecap="round"
                  />
                </svg>
                <div className="absolute inset-0 flex items-center justify-center">
                  <span className="text-[9px] font-black tabular-nums">{Math.round(day.stats.completion_rate)}%</span>
                </div>
              </div>
            </div>
          )
        })}
      </div>

      {/* Show More */}
      {history.length > 1 && (
        <button 
          onClick={() => setExpanded(!expanded)}
          className="w-full py-2 flex items-center justify-center gap-2 touch-manipulation"
        >
          <div className="h-px flex-1 bg-slate-100" />
          <div className="flex items-center gap-1.5 px-3 py-1 rounded-full bg-white border border-slate-100 shadow-sm">
            <span className="text-[9px] font-black text-slate-500">
              {expanded ? 'عرض أقل' : 'عرض السجل'}
            </span>
            <ChevronDown className={cn(
              "h-3 w-3 text-slate-400",
              expanded && "rotate-180"
            )} />
          </div>
          <div className="h-px flex-1 bg-slate-100" />
        </button>
      )}

      {/* Detail Dialog */}
      {selectedDay && (
        <HistoryDetailDialog
          isOpen={!!selectedDay}
          onClose={() => setSelectedDay(null)}
          date={selectedDay.date}
          age={selectedDay.age_days}
          timeline={selectedDay.timeline}
        />
      )}
    </div>
  )
}

function StatMini({ icon, value, color }: { icon: any; value: any; color: 'red' | 'amber' | 'indigo' }) {
  const colors = {
    red: "text-red-500 bg-red-50",
    amber: "text-amber-500 bg-amber-50",
    indigo: "text-indigo-500 bg-indigo-50",
  }
  return (
    <div className="flex items-center gap-1">
      <div className={cn("p-0.5 rounded", colors[color])}>
        {React.cloneElement(icon, { className: "h-2.5 w-2.5" })}
      </div>
      <span className="text-[10px] font-black tabular-nums text-slate-700">{value}</span>
    </div>
  )
}

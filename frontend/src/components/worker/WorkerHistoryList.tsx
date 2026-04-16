'use client'

import React, { useState } from 'react'
import { ChevronDown, Bird, Wheat, Pill, Activity, Receipt } from 'lucide-react'
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
    expense_count?: number
  }
  timeline: any[]
}

interface Props {
  history: HistoryDay[]
  isLoading?: boolean
  isRefreshing?: boolean
  role?: 'worker' | 'manager'
}

export function WorkerHistoryList({ history, isLoading, isRefreshing, role = 'worker' }: Props) {
  const [expanded, setExpanded] = useState(false)
  const [selectedDay, setSelectedDay] = useState<HistoryDay | null>(null)

  const today = new Date().toISOString().split('T')[0]
  const todayDay = history.find(day => day.date === today)
  const historyData = history.filter(day => day.date !== today)
  
  const visibleDays = expanded ? historyData : historyData.slice(0, 1)

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

      {/* Today Section */}
      {todayDay && (
        <div 
          onClick={() => setSelectedDay(todayDay)}
          className="group relative flex items-center bg-gradient-to-br from-emerald-50 to-white border border-emerald-200/60 p-4 rounded-[1.5rem] shadow-sm cursor-pointer active:scale-[0.98] transition-all overflow-hidden"
        >
          {/* Subtle decoration */}
          <div className="absolute -left-4 -top-4 opacity-5">
            <Activity size={80} />
          </div>

          <div className="flex items-center justify-between w-full z-10 gap-2">
            {/* Left: Progress Ring */}
            <div className="relative h-12 w-12 shrink-0">
              <svg className="h-full w-full -rotate-90" viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="16" fill="none" className="stroke-emerald-100" strokeWidth="4" />
                <circle 
                  cx="18" cy="18" r="16" fill="none" 
                  className={todayDay.stats.completion_rate === 100 ? "stroke-emerald-500" : "stroke-amber-400"}
                  strokeWidth="4" 
                  strokeDasharray={`${todayDay.stats.completion_rate}, 100`}
                  strokeLinecap="round"
                />
              </svg>
              <div className="absolute inset-0 flex flex-col items-center justify-center">
                <span className="text-[10px] font-black tabular-nums leading-none">{Math.round(todayDay.stats.completion_rate)}%</span>
              </div>
            </div>

            {/* Center: Key Stats */}
            <div className="flex-1 flex justify-center gap-3 px-2">
              <TodayStat icon={<Bird />} value={todayDay.stats.mortality} color="red" label="نفوق" />
              <TodayStat icon={<Wheat />} value={todayDay.stats.feed} color="amber" label="علف" />
              <TodayStat icon={<Pill />} value={todayDay.stats.medicine_count} color="indigo" label="أدوية" />
              {role === 'manager' && (
                <TodayStat icon={<Receipt />} value={todayDay.stats.expense_count ?? 0} color="orange" label="مصروف" />
              )}
            </div>

            {/* Right: Title & Live Badge */}
            <div className="flex flex-col items-end gap-1.5 shrink-0">
              <h4 className="text-[13px] font-black text-emerald-950">ملخص اليوم</h4>
              <div className="flex items-center gap-1.5 bg-emerald-500 text-white px-2 py-0.5 rounded-full text-[8px] font-black shadow-sm">
                <span className="relative flex h-1.5 w-1.5">
                  <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
                  <span className="relative inline-flex rounded-full h-1.5 w-1.5 bg-white"></span>
                </span>
                <span>مباشر</span>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* History Day Cards */}
      <div className="space-y-2">
        {visibleDays.map((day) => {
          return (
            <div 
              key={day.date}
              onClick={() => setSelectedDay(day)}
              className="flex items-center justify-between border bg-white border-emerald-50 p-3.5 rounded-[1.25rem] cursor-pointer active:scale-[0.98] transition-all hover:border-emerald-100"
            >
              {/* Left: Info */}
              <div className="flex flex-col gap-1.5 min-w-0">
                <div className="flex items-center gap-2">
                  <span className="text-xs font-black text-emerald-950">
                    {day.age_label}
                  </span>
                  <div className="h-1 w-1 rounded-full bg-slate-300" />
                  <span className="text-[9px] font-bold text-slate-400 tabular-nums">{day.date}</span>
                </div>
                <div className="flex items-center gap-3">
                  <StatMini icon={<Bird />} value={day.stats.mortality} color="red" />
                  <StatMini icon={<Wheat />} value={day.stats.feed} color="amber" />
                  <StatMini icon={<Pill />} value={day.stats.medicine_count} color="indigo" />
                  {role === 'manager' && (
                    <StatMini icon={<Receipt />} value={day.stats.expense_count ?? 0} color="orange" />
                  )}
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

function TodayStat({ icon, value, color, label }: { icon: any; value: any; color: 'red' | 'amber' | 'indigo' | 'orange'; label: string }) {
  const colors = {
    red: "text-red-600 bg-red-100/50",
    amber: "text-amber-600 bg-amber-100/50",
    indigo: "text-indigo-600 bg-indigo-100/50",
    orange: "text-orange-600 bg-orange-100/50",
  }
  return (
    <div className="flex flex-col items-center gap-1">
      <div className={cn("p-1.5 rounded-lg", colors[color])}>
        {React.cloneElement(icon, { className: "h-3.5 w-3.5" })}
      </div>
      <div className="flex flex-col items-center leading-none">
        <span className="text-sm font-black tabular-nums text-slate-800">{value}</span>
        <span className="text-[8px] font-bold text-slate-400 mt-0.5">{label}</span>
      </div>
    </div>
  )
}

function StatMini({ icon, value, color }: { icon: any; value: any; color: 'red' | 'amber' | 'indigo' | 'orange' }) {
  const colors = {
    red: "text-red-500 bg-red-50",
    amber: "text-amber-500 bg-amber-50",
    indigo: "text-indigo-500 bg-indigo-50",
    orange: "text-orange-500 bg-orange-50",
  }
  return (
    <div className="flex items-center gap-1">
      <div className={cn("p-0.5 rounded", colors[color])}>
        {React.cloneElement(icon, { className: "h-3 w-3" })}
      </div>
      <span className="text-[10px] font-black tabular-nums text-slate-700">{value}</span>
    </div>
  )
}

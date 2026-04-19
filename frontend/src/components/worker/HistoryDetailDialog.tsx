'use client'

import React from 'react'
import { Dialog } from '@/components/ui/Dialog'

import { Bird, Wheat, Pill, Thermometer, Clock, User } from 'lucide-react'
import { cn } from '@/lib/utils'

interface TimelineItem {
  type: 'mortality' | 'feed' | 'medicine' | 'temp'
  title: string
  detail: string
  time: string
  worker: string
}

interface Props {
  isOpen: boolean
  onClose: () => void
  date: string
  age: number
  timeline: TimelineItem[]
}

export function HistoryDetailDialog({ isOpen, onClose, date, age, timeline }: Props) {
  const getIcon = (type: string) => {
    switch (type) {
      case 'mortality': return <Bird className="h-4 w-4 text-red-500" />
      case 'feed': return <Wheat className="h-4 w-4 text-amber-500" />
      case 'medicine': return <Pill className="h-4 w-4 text-indigo-500" />
      case 'temp': return <Thermometer className="h-4 w-4 text-primary-500" />
      default: return <Clock className="h-4 w-4 text-slate-400" />
    }
  }

  const getBg = (type: string) => {
    switch (type) {
      case 'mortality': return 'bg-red-50'
      case 'feed': return 'bg-amber-50'
      case 'medicine': return 'bg-indigo-50'
      case 'temp': return 'bg-primary-50'
      default: return 'bg-slate-50'
    }
  }

  return (
    <Dialog 
      isOpen={isOpen} 
      onClose={onClose}
      className="max-w-md rounded-[2rem] overflow-hidden border-none shadow-2xl"
    >
      <div className="-mx-6 -mt-6 mb-6 bg-gradient-to-br from-slate-900 to-slate-800 p-6 text-white text-right">
        <div className="flex items-center justify-between mb-2">
           <h3 className="text-xl font-black Arabic-font">تفاصيل سجل اليوم</h3>
           <div className="bg-white/10 backdrop-blur-md px-3 py-1 rounded-xl text-[10px] font-bold border border-white/10 uppercase tracking-widest">
              اليوم {age}
           </div>
        </div>
        <p className="text-sm opacity-60 font-medium tabular-nums">{date}</p>
      </div>

      <div className="space-y-6">
          {timeline.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-10 text-slate-400">
               <Clock className="h-10 w-10 mb-2 opacity-20" />
               <p className="text-sm font-bold">لا توجد حركات مسجلة لهذا اليوم</p>
            </div>
          ) : (
            <div className="relative space-y-8 before:absolute before:inset-y-0 before:right-4 before:w-px before:bg-slate-100 pb-2">
              {timeline.map((item, idx) => (
                <div key={idx} className="relative pr-10">
                  {/* Timeline Dot */}
                  <div className={cn(
                    "absolute right-0 top-0 h-8 w-8 rounded-xl flex items-center justify-center border-2 border-white shadow-sm z-10",
                    getBg(item.type)
                  )}>
                    {getIcon(item.type)}
                  </div>

                  <div className="space-y-1">
                    <div className="flex items-center justify-between">
                       <h4 className="text-sm font-black text-slate-800 Arabic-font">{item.title}</h4>
                       <span className="text-[10px] font-black text-slate-400 tabular-nums flex items-center gap-1">
                          <Clock className="h-3 w-3" />
                          {item.time}
                       </span>
                    </div>
                    <p className="text-xs text-slate-500 font-medium leading-relaxed">{item.detail}</p>
                    <div className="flex items-center gap-1.5 pt-1">
                       <div className="h-4 w-4 rounded-full bg-slate-100 flex items-center justify-center">
                          <User className="h-2.5 w-2.5 text-slate-400" />
                       </div>
                       <span className="text-[10px] font-bold text-slate-400">{item.worker}</span>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
      </div>
    </Dialog>
  )
}



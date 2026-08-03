'use client'

import { useEffect, useRef } from 'react'

interface FlockDaySelectorProps {
  startDate: string
  selectedDate: string
  onSelectDate: (date: string) => void
  maxDate?: string
}

export function FlockDaySelector({ startDate, selectedDate, onSelectDate, maxDate }: FlockDaySelectorProps) {
  const scrollRef = useRef<HTMLDivElement>(null)

  // Generate array of dates from startDate to maxDate (or today)
  const generateDates = () => {
    const dates: { date: string; dayIndex: number; formatted: string }[] = []
    
    const start = new Date(startDate)
    const end = maxDate ? new Date(maxDate) : new Date()
    
    // Normalize times to midnight to avoid timezone shift issues
    start.setHours(0, 0, 0, 0)
    end.setHours(0, 0, 0, 0)

    // Ensure we have at least 1 day if start date is in the future
    if (end < start) end.setTime(start.getTime())

    let current = new Date(start)
    let dayIndex = 1

    while (current <= end) {
      const dateStr = current.getFullYear() + '-' +
                      String(current.getMonth() + 1).padStart(2, '0') + '-' +
                      String(current.getDate()).padStart(2, '0')
                      
      const formatted = String(current.getDate()).padStart(2, '0') + '/' + 
                        String(current.getMonth() + 1).padStart(2, '0')

      dates.push({
        date: dateStr,
        dayIndex,
        formatted
      })

      current.setDate(current.getDate() + 1)
      dayIndex++
    }

    return dates
  }

  const dates = generateDates()

  // Scroll active item into view on mount or when selectedDate changes externally
  useEffect(() => {
    if (!scrollRef.current) return
    const activeEl = scrollRef.current.querySelector('[data-selected="true"]') as HTMLElement
    if (activeEl) {
      const container = scrollRef.current
      const scrollLeft = activeEl.offsetLeft - (container.clientWidth / 2) + (activeEl.clientWidth / 2)
      container.scrollTo({ left: scrollLeft, behavior: 'smooth' })
    }
  }, [selectedDate, dates.length])

  return (
    <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-2 rounded-2xl shadow-sm overflow-hidden">
      <div 
        ref={scrollRef}
        className="flex items-center gap-2 overflow-x-auto scrollbar-hide py-1 px-1 snap-x"
        style={{ scrollbarWidth: 'none', msOverflowStyle: 'none', WebkitOverflowScrolling: 'touch' }}
      >
        {dates.map((d) => {
          const isSelected = d.date === selectedDate
          return (
            <button
              key={d.date}
              data-selected={isSelected}
              onClick={() => onSelectDate(d.date)}
              className={`flex-shrink-0 flex flex-col items-center justify-center min-w-[72px] h-16 rounded-xl transition-all snap-center ${
                isSelected 
                  ? 'bg-primary-500 text-white shadow-md shadow-primary-500/20 ring-2 ring-primary-500/20' 
                  : 'bg-slate-50/50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
              }`}
            >
              <span className={`text-[10px] font-bold mb-1 ${isSelected ? 'text-primary-100' : 'text-slate-400'}`}>
                اليوم {d.dayIndex}
              </span>
              <span className={`text-sm font-black tracking-tight font-mono`}>
                {d.formatted}
              </span>
            </button>
          )
        })}
      </div>
      <style dangerouslySetInnerHTML={{__html: `
        .scrollbar-hide::-webkit-scrollbar {
          display: none;
        }
      `}} />
    </div>
  )
}

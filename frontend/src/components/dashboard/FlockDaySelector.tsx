'use client'

import { useEffect, useState } from 'react'

interface FlockDaySelectorProps {
  startDate: string
  selectedDate: string
  onSelectDate: (date: string) => void
  maxDate?: string
}

export function FlockDaySelector({ startDate, selectedDate, onSelectDate, maxDate }: FlockDaySelectorProps) {
  // Generate array of dates from startDate to maxDate (or today)
  const generateDates = () => {
    const dates: { date: string; dayIndex: number; formatted: string }[] = []
    
    const start = new Date(startDate)
    const end = maxDate ? new Date(maxDate) : new Date()
    
    start.setHours(0, 0, 0, 0)
    end.setHours(0, 0, 0, 0)
    if (end < start) end.setTime(start.getTime())

    let current = new Date(start)
    let dayIndex = 1

    while (current <= end) {
      const dateStr = current.getFullYear() + '-' +
                      String(current.getMonth() + 1).padStart(2, '0') + '-' +
                      String(current.getDate()).padStart(2, '0')
                      
      const formatted = String(current.getDate()).padStart(2, '0') + '/' + 
                        String(current.getMonth() + 1).padStart(2, '0')

      dates.push({ date: dateStr, dayIndex, formatted })
      current.setDate(current.getDate() + 1)
      dayIndex++
    }
    return dates
  }

  const dates = generateDates()
  const selectedIndex = dates.findIndex(d => d.date === selectedDate)
  const safeIndex = selectedIndex >= 0 ? selectedIndex : dates.length - 1

  const [dragIndex, setDragIndex] = useState(safeIndex)

  // Sync drag index when selected date changes externally
  useEffect(() => {
    setDragIndex(safeIndex)
  }, [safeIndex])

  const percentage = dates.length > 1 ? (dragIndex / (dates.length - 1)) * 100 : 100

  const handleDrag = (e: React.ChangeEvent<HTMLInputElement>) => {
    setDragIndex(parseInt(e.target.value))
  }

  const handleRelease = () => {
    if (dragIndex !== safeIndex) {
      onSelectDate(dates[dragIndex].date)
    }
  }

  return (
    <div className="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-5 rounded-2xl shadow-sm relative overflow-hidden" dir="rtl">
      <div className="flex justify-between items-center mb-6">
        <div>
           <p className="text-[11px] text-slate-400 font-bold mb-1">عمر الفوج</p>
           <div className="flex items-baseline gap-1">
             <span className="text-primary-600 font-black text-2xl">اليوم {dates[dragIndex].dayIndex}</span>
           </div>
        </div>
        <div className="text-left">
           <p className="text-[11px] text-slate-400 font-bold mb-1">تاريخ اليوم</p>
           <p className="text-slate-700 dark:text-slate-200 font-bold text-lg" dir="ltr">{dates[dragIndex].formatted}</p>
        </div>
      </div>
      
      <div className="relative w-full h-8 flex items-center">
        {/* Custom Track Background */}
        <div className="absolute top-1/2 left-0 right-0 h-2.5 -translate-y-1/2 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden pointer-events-none">
          {/* Custom Track Fill */}
          <div 
            className="absolute top-0 bottom-0 right-0 bg-primary-500 transition-all duration-75 ease-out"
            style={{ width: `${percentage}%` }}
          />
        </div>
        
        {/* Native Input Range */}
        <input 
          type="range"
          min={0}
          max={dates.length > 1 ? dates.length - 1 : 0}
          value={dragIndex}
          onChange={handleDrag}
          onMouseUp={handleRelease}
          onTouchEnd={handleRelease}
          className="w-full absolute inset-0 z-20 cursor-pointer appearance-none bg-transparent m-0"
          style={{ WebkitAppearance: 'none' }}
        />
      </div>

      <style dangerouslySetInnerHTML={{__html: `
        input[type=range]::-webkit-slider-thumb {
          -webkit-appearance: none;
          appearance: none;
          width: 28px;
          height: 28px;
          border-radius: 50%;
          background: white;
          border: 6px solid #22c55e;
          cursor: pointer;
          box-shadow: 0 2px 8px rgba(34, 197, 94, 0.4);
          position: relative;
          z-index: 30;
          transition: transform 0.1s;
        }
        input[type=range]::-webkit-slider-thumb:active {
          transform: scale(1.15);
        }
        input[type=range]::-moz-range-thumb {
          width: 28px;
          height: 28px;
          border-radius: 50%;
          background: white;
          border: 6px solid #22c55e;
          cursor: pointer;
          box-shadow: 0 2px 8px rgba(34, 197, 94, 0.4);
          transition: transform 0.1s;
        }
        input[type=range]::-moz-range-thumb:active {
          transform: scale(1.15);
        }
      `}} />
    </div>
  )
}

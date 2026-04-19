'use client'

import { CheckCircle2, ChevronLeft, Circle } from 'lucide-react'

export interface ChecklistItemProps {
  label: string
  isRequired?: boolean
  isCompleted: boolean
  displayValue?: string
  onClick: () => void
  disabled?: boolean
}

export function ChecklistItem({
  label,
  isRequired = false,
  isCompleted,
  displayValue,
  onClick,
  disabled = false,
}: ChecklistItemProps) {
  return (
    <button
      onClick={onClick}
      disabled={disabled}
      className={`w-full flex items-center justify-between p-4 bg-white rounded-xl border transition-all ${
        disabled ? 'opacity-70 cursor-not-allowed' : 'hover:border-primary-200 active:scale-[0.99] shadow-sm'
      } ${
        isCompleted ? 'border-primary-200 bg-primary-50/50' : 'border-slate-200'
      }`}
    >
      <div className="flex items-center gap-3">
        {isCompleted ? (
          <CheckCircle2 className="w-5 h-5 text-primary-500 shrink-0" />
        ) : (
          <Circle className="w-5 h-5 text-slate-300 shrink-0" />
        )}
        <div className="flex flex-col items-start gap-1">
          <div className="flex items-center gap-2">
            <span className={`font-semibold ${isCompleted ? 'text-primary-900' : 'text-slate-700'}`}>
              {label}
            </span>
            {isRequired && !isCompleted && (
              <span className="text-[10px] bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full font-medium">
                إلزامي
              </span>
            )}
          </div>
        </div>
      </div>
      
      <div className="flex items-center gap-2">
        {displayValue ? (
          <span className={`text-sm font-medium ${isCompleted ? 'text-primary-700' : 'text-slate-500'}`}>
            {displayValue}
          </span>
        ) : (
          <span className="text-sm text-slate-400">
            {isCompleted ? 'اضغط للتعديل' : 'اضغط للإدخال'}
          </span>
        )}
        {!disabled && <ChevronLeft className="w-4 h-4 text-slate-400" />}
      </div>
    </button>
  )
}


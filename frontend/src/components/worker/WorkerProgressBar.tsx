'use client'

interface WorkerProgressBarProps {
  completed: number
  total: number
}

export function WorkerProgressBar({ completed, total }: WorkerProgressBarProps) {
  const percentage = Math.round((completed / total) * 100)
  
  // Decide color based on percentage
  let colorClass = 'bg-slate-200'
  if (percentage === 100) colorClass = 'bg-primary-500'
  else if (percentage > 0) colorClass = 'bg-emerald-500'
  
  return (
    <div className="mb-6">
      <div className="flex justify-between text-sm font-medium text-slate-600 mb-2">
        <span>مستوى الإنجاز</span>
        <span>{completed} من {total} مكتملة</span>
      </div>
      <div className="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
        <div 
          className={`h-full ${colorClass} transition-all duration-500 ease-out`}
          style={{ width: `${percentage}%` }}
        />
      </div>
    </div>
  )
}


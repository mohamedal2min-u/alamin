'use client'

import { Thermometer, Wheat, Moon } from 'lucide-react'

interface WorkerOpInfoCardProps {
  targetTemp: number
  feedTargetKg: number
  dimmingActive: boolean
}

export function WorkerOpInfoCard({
  targetTemp,
  feedTargetKg,
  dimmingActive,
}: WorkerOpInfoCardProps) {
  return (
    <div className="bg-gradient-to-br from-primary-50 to-primary-100/50 p-4 rounded-xl border border-primary-100 shadow-sm mb-6">
      <div className="flex items-center justify-between mb-4 border-b border-primary-200/50 pb-3">
        <div className="flex items-center gap-2 text-primary-800">
          <Thermometer className="w-5 h-5" />
          <span className="font-semibold">{targetTemp}°C مطلوب</span>
        </div>
        <div className="flex items-center gap-2 text-primary-800">
          <Wheat className="w-5 h-5 text-emerald-600" />
          <span className="font-semibold">{feedTargetKg} كجم اليوم</span>
        </div>
      </div>
      
      <div className="flex items-center gap-2 text-primary-800">
        <Moon className={`w-5 h-5 ${dimmingActive ? 'text-indigo-600 fill-indigo-600' : 'text-slate-400'}`} />
        <span className="font-medium">التعتيم: {dimmingActive ? 'مفعّل' : 'غير مفعّل'}</span>
      </div>
    </div>
  )
}


'use client'

import { Thermometer, Wheat, Moon } from 'lucide-react'

interface Props {
  ageDays: number
  birdCount: number
}

export function WorkerGuidelinesCard({ ageDays, birdCount }: Props) {
  return (
    <div className="relative overflow-hidden bg-[#0d631b] rounded-[2rem] p-6 text-white shadow-lg">
      {/* ── Background Magic (Large Icon) ── */}
      <Thermometer className="absolute -left-4 -bottom-4 h-40 w-40 text-black/10 -rotate-12" />
      
      <div className="relative z-10 space-y-6">
        {/* Header Segment */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <span className="h-2 w-2 rounded-full bg-emerald-400" />
            <h3 className="text-[11px] font-black uppercase tracking-wider opacity-80">التعليمات التشغيلية اليومية</h3>
          </div>
          <div className="bg-white/10 px-3 py-1 rounded-full text-[10px] font-bold">
            اليوم {ageDays}
          </div>
        </div>

        {/* Temperature Highlight (Hero Section) */}
        <div className="flex flex-col items-center justify-center py-4 border-b border-white/10">
          <span className="text-[11px] font-bold opacity-60 mb-1">الحرارة المطلوبة</span>
          <div className="flex items-center gap-4">
             <Thermometer className="h-10 w-10 text-emerald-400" />
             <h2 className="text-6xl font-black tracking-tighter tabular-nums">30 - 33°</h2>
          </div>
        </div>

        {/* Bottom Metrics Grid */}
        <div className="grid grid-cols-2 gap-4">
          <div className="flex items-center gap-3">
            <div className="h-10 w-10 flex items-center justify-center rounded-2xl bg-white/10">
              <Wheat className="h-5 w-5 text-emerald-300" />
            </div>
            <div className="flex flex-col">
              <span className="text-[10px] font-bold opacity-60">العلف</span>
              <span className="text-sm font-black">18 ج / طير</span>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <div className="h-10 w-10 flex items-center justify-center rounded-2xl bg-white/10">
              <Moon className="h-5 w-5 text-emerald-300" />
            </div>
            <div className="flex flex-col">
              <span className="text-[10px] font-bold opacity-60">التعتيم</span>
              <span className="text-sm font-black">6 ساعات</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

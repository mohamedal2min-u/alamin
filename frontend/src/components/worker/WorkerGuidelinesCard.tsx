'use client'

import { Thermometer, Wheat, Moon } from 'lucide-react'

interface Props {
  ageDays: number
  birdCount: number
}

export function WorkerGuidelinesCard({ ageDays, birdCount }: Props) {
  return (
    <div className="relative overflow-hidden bg-gradient-to-br from-[#064e3b] via-[#065f46] to-[#047857] rounded-[2.5rem] p-8 text-white shadow-emerald">
      {/* ── Background Decoration ── */}
      <div className="absolute top-0 right-0 h-40 w-40 bg-emerald-400/10 rounded-full blur-[60px] -mr-20 -mt-20" />
      <div className="absolute bottom-0 left-0 h-40 w-40 bg-teal-400/10 rounded-full blur-[60px] -ml-20 -mb-20" />
      <Thermometer className="absolute -left-6 -bottom-6 h-48 w-48 text-white/5 -rotate-12" />
      
      <div className="relative z-10 space-y-8">
        {/* Header Segment */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="relative">
              <div className="h-2.5 w-2.5 rounded-full bg-emerald-400 animate-ping absolute inset-0" />
              <div className="h-2.5 w-2.5 rounded-full bg-emerald-400 relative" />
            </div>
            <h3 className="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-100/80">المساعد الذكي • اليوم {ageDays}</h3>
          </div>
          <div className="bg-white/10 backdrop-blur-md px-4 py-1.5 rounded-2xl border border-white/10 text-[11px] font-black tabular-nums">
             عمر الفوج: {ageDays} أيام
          </div>
        </div>

        {/* Temperature Highlight (Hero Section) */}
        <div className="flex flex-col items-center justify-center py-6 border-y border-white/5">
          <span className="text-[11px] font-bold text-emerald-100/50 uppercase tracking-widest mb-3">الحرارة المثالية المستهدفة</span>
          <div className="flex items-center gap-6">
             <div className="flex h-16 w-16 items-center justify-center rounded-[2rem] bg-white/10 backdrop-blur-sm border border-white/10 shadow-inner">
               <Thermometer className="h-9 w-9 text-emerald-300 drop-shadow-sm" />
             </div>
             <div className="flex flex-col items-start">
               <h2 className="text-6xl font-black tracking-tighter tabular-nums leading-none">31<span className="text-3xl font-bold opacity-40">.5</span><span className="text-3xl ml-1 text-emerald-300">°</span></h2>
               <p className="text-[10px] font-bold text-emerald-100/40 mt-1">النطاق الأمن: 30° - 33°</p>
             </div>
          </div>
        </div>

        {/* Bottom Metrics Grid */}
        <div className="grid grid-cols-2 gap-6">
          <div className="flex items-center gap-4">
            <div className="h-12 w-12 flex items-center justify-center rounded-2xl bg-white/10 border border-white/5">
              <Wheat className="h-6 w-6 text-emerald-300" />
            </div>
            <div className="flex flex-col">
              <span className="text-[10px] font-bold text-emerald-100/40 uppercase">كمية العلف</span>
              <span className="text-sm font-black tabular-nums">18 جرام / طير</span>
            </div>
          </div>

          <div className="flex items-center gap-4">
            <div className="h-12 w-12 flex items-center justify-center rounded-2xl bg-white/10 border border-white/5">
              <Moon className="h-6 w-6 text-emerald-300" />
            </div>
            <div className="flex flex-col">
              <span className="text-[10px] font-bold text-emerald-100/40 uppercase">ساعات التعتيم</span>
              <span className="text-sm font-black tabular-nums">6 ساعات</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

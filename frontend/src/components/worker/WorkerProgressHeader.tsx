'use client'

import { cn } from '@/lib/utils'
import { CheckCircle2, History, Users, Bird, Calendar } from 'lucide-react'

interface Props {
  completed: number
  total: number
  flock?: {
    name: string
    initial_count: number
    remaining_count: number
    current_age_days: number
    start_date: string
  }
}

export function WorkerProgressHeader({ completed, total, flock }: Props) {
  const percentage = Math.round((completed / total) * 100)
  const isComplete = completed >= total

  return (
    <div className="relative overflow-hidden rounded-[2rem] bg-white shadow-[0_10px_30px_rgba(0,0,0,0.04)] border border-slate-100 transition-all duration-500">
      {/* ── Background Accent (Subtle) ── */}
      <div className="absolute -top-12 -right-12 h-24 w-24 rounded-full bg-emerald-50/20 blur-2xl" />
      
      <div className="relative p-3.5">
        {/* ── Compact Metrics Grid ── */}
        <div className="mb-3 grid grid-cols-4 gap-2">
          <CompactStatPill 
            label="العدد الكلي" 
            value={flock?.initial_count ?? 0} 
            icon={<Users className="w-2.5 h-2.5" />}
            bgColor="bg-blue-50/30"
            textColor="text-blue-600"
          />
          <CompactStatPill 
            label="العمر" 
            value={flock?.current_age_days ?? 0} 
            icon={<History className="w-2.5 h-2.5" />}
            bgColor="bg-amber-50/30"
            textColor="text-amber-600"
          />
          <CompactStatPill 
            label="المتبقي" 
            value={flock?.remaining_count ?? 0} 
            icon={<Bird className="w-2.5 h-2.5" />}
            bgColor="bg-emerald-50/30"
            textColor="text-emerald-600"
          />
          <CompactStatPill 
            label="البداية" 
            value={flock?.start_date ? flock.start_date.split('-').slice(1).join('/') : '---'} 
            icon={<Calendar className="w-2.5 h-2.5" />}
            bgColor="bg-slate-50/30"
            textColor="text-slate-500"
          />
        </div>

        {/* ── Sleek Mini Progress Line ── */}
        <div className="relative mb-2.5">
          <div className="h-1.5 w-full overflow-hidden rounded-full bg-slate-100/80 shadow-inner">
            <div 
              className={cn(
                "h-full rounded-full transition-all duration-1000 ease-in-out relative",
                isComplete 
                  ? "bg-gradient-to-r from-emerald-500 to-teal-500" 
                  : "bg-gradient-to-r from-emerald-400 to-emerald-600"
              )}
              style={{ width: `${percentage}%` }}
            >
              <div className="absolute inset-0 bg-[linear-gradient(45deg,rgba(255,255,255,0.1)_25%,transparent_25%,transparent_50%,rgba(255,255,255,0.1)_50%,rgba(255,255,255,0.1)_75%,transparent_75%,transparent)] bg-[length:1rem_1rem] opacity-30 animate-[shimmer_2s_linear_infinite]" />
            </div>
          </div>
        </div>

        {/* ── Ultra-Compact Footer ── */}
        <div className="flex items-center justify-between px-1">
          {/* Active Status Badge (Mini) */}
          <div className="flex items-center gap-1.5 rounded-lg bg-slate-50/50 px-2 py-0.5 border border-slate-100/50">
            <div className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_4px_rgba(16,185,129,0.5)]" />
            <span className="text-[9px] font-black text-slate-500 tracking-tight">{flock?.name ?? 'نشط'}</span>
          </div>

          {/* Combined Completion Info */}
          <div className="flex items-center gap-2">
            <p className="text-[9px] font-bold text-slate-400">
              <span className="text-emerald-600 font-black">{completed}</span> / {total} مهام
            </p>
            <div className="flex items-center gap-1 bg-emerald-50 px-1.5 py-0.5 rounded-md border border-emerald-100/50">
               <span className="text-[9px] font-black text-emerald-700 tabular-nums">{percentage}%</span>
               {isComplete && <CheckCircle2 className="h-2.5 w-2.5 text-emerald-500" />}
            </div>
          </div>
        </div>
      </div>

      <style jsx>{`
        @keyframes shimmer {
          from { background-position: 0 0; }
          to { background-position: 2rem 0; }
        }
      `}</style>
    </div>
  )
}

function CompactStatPill({ label, value, icon, bgColor, textColor }: { 
  label: string; value: string | number; icon: React.ReactNode; bgColor: string; textColor: string 
}) {
  return (
    <div className={cn(
      "flex flex-col items-center justify-center py-1.5 px-0.5 rounded-xl transition-all",
      bgColor
    )}>
      <div className="mb-0.5 flex items-center gap-0.5 opacity-40">
        <span className="text-slate-400 capitalize">{icon}</span>
        <span className="text-[7px] font-black uppercase tracking-tighter text-slate-500">{label}</span>
      </div>
      <span className={cn(
        "text-sm font-black leading-none tabular-nums tracking-tighter",
        textColor
      )}>
        {value}
      </span>
    </div>
  )
}

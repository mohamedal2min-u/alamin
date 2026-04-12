'use client'

import { ThermometerSun, Wheat, Moon, ClipboardCheck } from 'lucide-react'
import { cn } from '@/lib/utils'
import { getFlockDailyGuidelines } from '@/lib/poultry-standards'

interface Props {
  ageDays: number
  birdCount: number
}

export function WorkerGuidelinesCard({ ageDays, birdCount }: Props) {
  // Get authoritative precision targets from Ross 308 standards engine
  const targets = getFlockDailyGuidelines(ageDays, birdCount)
  
  // Rounding for simple worker display (whole numbers preferred by user)
  const displayTemp = Math.round(targets.targetTemp)
  const displayMin = Math.round(targets.minTemp)
  const displayMax = Math.round(targets.maxTemp)
  
  const displayBags = Math.round(targets.feedBags)
  const displayKilos = targets.feedGoalKilos

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
      {/* ── Temperature Card ── */}
      <GuidelineItem 
        label="الحرارة المطلوبة" 
        value={`${displayTemp}°C`} 
        subDetail={`${displayMin}° - ${displayMax}°`} // Range logic as requested
        icon={<ThermometerSun className="h-5 w-5" />} 
        color="text-amber-600" 
        bgColor="bg-amber-50"
      />

      {/* ── Feed Card - Bags hierarchy as primary decision ── */}
      <GuidelineItem 
        label="هدف العلف اليومي" 
        value={`${displayBags} أكياس`}
        subDetail={`${displayKilos} كغم`}
        icon={<Wheat className="h-5 w-5" />} 
        color="text-blue-600" 
        bgColor="bg-blue-50"
      />

      {/* ── Dimming Card - Distribution detailed ── */}
      <GuidelineItem 
        label="ساعات التعتيم" 
        value={`${targets.dimmingHours} ساعات`} 
        subDetail={targets.dimmingDist} // Distribution logic as requested
        icon={<Moon className="h-5 w-5" />} 
        color="text-indigo-600" 
        bgColor="bg-indigo-50"
      />
    </div>
  )
}

interface ItemProps {
  label: string
  value: string
  subDetail?: string
  icon: React.ReactNode
  color: string
  bgColor: string
}

function GuidelineItem({ label, value, subDetail, icon, color, bgColor }: ItemProps) {
  return (
    <div className={cn(
      "relative flex items-center gap-4 rounded-[2rem] p-6 border border-white shadow-sm overflow-hidden min-h-[110px] transition-all duration-300 hover:shadow-md", 
      bgColor
    )}>
      {/* Icon Capsule */}
      <div className={cn("flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-black/[0.02]", color)}>
        {icon}
      </div>

      {/* Content Stack */}
      <div className="flex flex-col">
        <span className="text-[10px] font-black uppercase tracking-wider text-slate-400 opacity-80 mb-1 leading-none">
          {label}
        </span>
        <div className="flex flex-col">
          <span className={cn("text-2xl font-black tracking-tight leading-none", color)}>
            {value}
          </span>
          {subDetail && (
            <span className="text-xs font-bold text-slate-500/70 mt-1.5 tabular-nums">
              {subDetail}
            </span>
          )}
        </div>
      </div>

      {/* Background Decorative Element */}
      <ClipboardCheck className="absolute -right-3 -bottom-3 h-16 w-16 text-black/5 -rotate-12 pointer-events-none" />
    </div>
  )
}

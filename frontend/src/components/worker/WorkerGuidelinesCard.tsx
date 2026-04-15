'use client'

import React from 'react'
import { Thermometer, Wheat, Moon, ShieldCheck, type LucideIcon } from 'lucide-react'
import { getFlockDailyGuidelines, getTargetFeedPerBird } from '@/lib/poultry-standards'
import { cn } from '@/lib/utils'

interface Props {
  ageDays: number
  birdCount: number
}

export function WorkerGuidelinesCard({ ageDays, birdCount }: Props) {
  const guidelines = getFlockDailyGuidelines(ageDays, birdCount, 50)
  const gramsPerBird = Math.round(getTargetFeedPerBird(ageDays))

  const cards: { title: string; value: string; unit: string; sub: string; icon: LucideIcon; gradient: string }[] = [
    {
      title: 'الحرارة المثالية',
      value: `${Math.floor(guidelines.minTemp)}-${Math.ceil(guidelines.maxTemp)}`,
      unit: '°',
      sub: 'درجة مئوية',
      icon: Thermometer,
      gradient: 'from-emerald-500 to-emerald-700',
    },
    {
      title: 'توزيع العلف',
      value: String(guidelines.feedBags.toFixed(1)),
      unit: 'كيس',
      sub: `${gramsPerBird}ج / ${guidelines.feedGoalKilos}كجم`,
      icon: Wheat,
      gradient: 'from-amber-400 to-amber-600',
    },
    {
      title: 'فترة التعتيم',
      value: String(guidelines.dimmingHours),
      unit: 'ساعات',
      sub: guidelines.dimmingDist,
      icon: Moon,
      gradient: 'from-indigo-500 to-indigo-700',
    },
  ]

  return (
    <div className="space-y-3" dir="rtl">
      {/* Section Title */}
      <div className="flex items-center justify-between px-1">
        <div className="flex items-center gap-2">
          <div className="h-4 w-1 rounded-full bg-emerald-500" />
          <h3 className="text-[13px] font-black text-slate-800">التعليمات التشغيلية</h3>
        </div>

        <div className="flex items-center gap-2">
          <div className="flex items-center gap-1 bg-slate-50 border border-slate-200 px-2 py-0.5 rounded-lg">
            <ShieldCheck className="h-2.5 w-2.5 text-emerald-600" />
            <span className="text-[8px] font-black text-slate-500">Ross 2023</span>
          </div>
          <div className="flex items-center gap-1 bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-lg text-[10px] font-black border border-emerald-100">
            <span>اليوم</span>
            <span className="tabular-nums">{ageDays}</span>
          </div>
        </div>
      </div>

      {/* Cards Row - Horizontal Scroll */}
      <div className="flex gap-2.5 overflow-x-auto px-0.5 pb-2 no-scrollbar snap-x">
        {cards.map((card) => {
          const Icon = card.icon;
          return (
            <div
              key={card.title}
              className={cn(
                "relative min-w-[140px] flex-1 shrink-0 snap-center rounded-2xl p-4 text-white overflow-hidden bg-gradient-to-br",
                card.gradient
              )}
            >
              {/* Background icon */}
              <div className="absolute -right-2 -bottom-2 opacity-10">
                <Icon size={60} strokeWidth={1.5} />
              </div>

              <div className="relative z-10 flex flex-col gap-4">
                <div className="space-y-1">
                  <div className="flex h-8 w-8 items-center justify-center rounded-[0.5rem] bg-white/20">
                    <Icon className="h-4 w-4" />
                  </div>
                  <p className="text-[9px] font-black uppercase tracking-tight opacity-70">{card.title}</p>
              </div>
              <div className="space-y-0.5">
                <div className="flex items-baseline gap-1">
                  <h4 className="text-2xl font-black tabular-nums tracking-tighter">{card.value}</h4>
                  <span className="text-[10px] font-bold opacity-80">{card.unit}</span>
                </div>
                <p className="text-[8px] font-bold opacity-70 truncate">{card.sub}</p>
              </div>
            </div>
            </div>
          );
        })}
      </div>

      <style jsx global>{`
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
      `}</style>
    </div>
  )
}

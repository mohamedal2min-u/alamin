'use client'

import React from 'react'
import { Thermometer, Wheat, Moon, ShieldCheck, CloudSun, type LucideIcon } from 'lucide-react'
import { getFlockDailyGuidelines, getTargetFeedPerBird } from '@/lib/poultry-standards'
import { cn } from '@/lib/utils'
import { useQuery } from '@tanstack/react-query'

interface Props {
  ageDays: number
  birdCount: number
}

export function WorkerGuidelinesCard({ ageDays, birdCount }: Props) {
  const guidelines = getFlockDailyGuidelines(ageDays, birdCount, 50)
  const gramsPerBird = Math.round(getTargetFeedPerBird(ageDays))

  const { data: weather } = useQuery({
    queryKey: ['weather-kansafra'],
    queryFn: async () => {
      // إحداثيات قرية كنصفرة كما حددها المستخدم عبر خرائط جوجل.
      // نموذج ECMWF (الأوروبي) أقرب بشكل ملحوظ للطقس الفعلي بهذه المنطقة
      // من النموذج الافتراضي (best_match)، الذي كان يعطي فرقاً بـ٣ درجات تقريباً.
      const res = await fetch('https://api.open-meteo.com/v1/forecast?latitude=35.66057&longitude=36.50465&current_weather=true&models=ecmwf_ifs025')
      return res.json()
    },
    staleTime: 15 * 60_000,
  })

  const currentTemp = weather?.current_weather?.temperature

  const cards: { title: string; value: string; unit: string; sub: string; icon: LucideIcon; gradient: string; extraContent?: React.ReactNode }[] = [
    {
      title: 'الحرارة المثالية',
      value: `${Math.floor(guidelines.minTemp)}-${Math.ceil(guidelines.maxTemp)}`,
      unit: '°',
      sub: 'درجة مئوية',
      icon: Thermometer,
      gradient: 'from-primary-500 to-primary-700',
      extraContent: currentTemp !== undefined ? (
        <div className="absolute top-3 left-3 z-20 flex items-center gap-1.5 bg-white/20 px-2 py-0.5 rounded-full backdrop-blur-sm shadow-sm border border-white/20">
          <span className="relative flex h-1.5 w-1.5">
            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
            <span className="relative inline-flex rounded-full h-1.5 w-1.5 bg-rose-500"></span>
          </span>
          <div className="flex items-center gap-1">
            <CloudSun className="h-3 w-3 text-white" />
            <span className="text-[10px] font-black text-white tabular-nums" dir="ltr">{currentTemp}°</span>
          </div>
        </div>
      ) : null
    },
    {
      title: 'توزيع العلف',
      value: String(guidelines.feedBags.toFixed(1)),
      unit: 'كيس',
      sub: `${gramsPerBird}ج / ${guidelines.feedGoalKilos}كجم`,
      icon: Wheat,
      gradient: 'from-emerald-400 to-emerald-600',
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
          <div className="h-4 w-1 rounded-full bg-primary-500" />
          <h3 className="text-[13px] font-black text-slate-800">التعليمات التشغيلية</h3>
        </div>

        <div className="flex items-center gap-2">
          <div className="flex items-center gap-1 bg-slate-50 border border-slate-200 px-2.5 py-1 rounded-lg">
            <ShieldCheck className="h-3.5 w-3.5 text-primary-600" />
            <span className="text-[10px] font-black text-slate-500 uppercase">النظام المخصص</span>
          </div>
          <div className="flex items-center gap-1 bg-primary-50 text-primary-700 px-2.5 py-1 rounded-lg text-[11px] font-black border border-primary-100 shadow-sm">
            <span>اليوم</span>
            <span className="tabular-nums">{ageDays}</span>
          </div>
        </div>
      </div>

      {/* Cards Row - Grid Layout */}
      <div className="grid grid-cols-3 gap-2 px-0.5 pb-2">
        {cards.map((card) => {
          const Icon = card.icon;
          return (
            <div
              key={card.title}
              className={cn(
                "relative min-w-0 flex-1 rounded-2xl p-3 text-white overflow-hidden bg-gradient-to-br",
                card.gradient
              )}
            >
              {/* Background icon */}
              <div className="absolute -right-2 -bottom-2 opacity-10">
                <Icon size={60} strokeWidth={1.5} />
              </div>

              {card.extraContent}

              <div className="relative z-10 flex flex-col gap-2.5">
                <div className="space-y-1">
                  <div className="flex h-7 w-7 items-center justify-center rounded-[0.5rem] bg-white/20">
                    <Icon className="h-3.5 w-3.5" />
                  </div>
                  <p className="text-[11px] font-black uppercase tracking-tight text-white/90">{card.title}</p>
              </div>
              <div className="space-y-0.5">
                <div className="flex items-baseline gap-1">
                  <h4 className="text-2xl font-black tabular-nums tracking-tighter">{card.value}</h4>
                  <span className="text-[11px] font-bold text-white/90">{card.unit}</span>
                </div>
                <p className="text-[10px] font-bold text-white/80 truncate leading-tight">{card.sub}</p>
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


// frontend/src/components/dashboard/OperationalInfoCard.tsx
'use client'

import { ThermometerSun, Wheat, Moon, Bookmark } from 'lucide-react'
import { formatNumber, cn } from '@/lib/utils'
import { getFlockDailyGuidelines } from '@/lib/poultry-standards'

interface Props {
  ageDays: number | null
  birdCount: number
}

export function OperationalInfoCard({ ageDays, birdCount }: Props) {
  if (ageDays === null) return null

  // Get precision targets from the authoritative Ross 308 standards engine
  const targets = getFlockDailyGuidelines(ageDays, birdCount)

  const formatWeight = (kg: number) => {
    if (kg >= 1000) return { value: (kg / 1000).toFixed(2), unit: 'طن' }
    return { value: formatNumber(kg), unit: 'كجم' }
  }

  const weight = formatWeight(targets.feedGoalKilos)

  return (
    <div className="relative overflow-hidden rounded-3xl border border-indigo-100 bg-indigo-50/30 p-4 transition-all duration-300">
      {/* ── Header ── */}
      <div className="mb-4 flex items-center justify-between px-1 border-b border-indigo-100/50 pb-3">
        <div className="flex items-center gap-2">
          <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600">
            <Bookmark className="h-3.5 w-3.5 fill-current opacity-70" />
          </div>
          <div>
            <h3 className="text-[10px] font-black Arabic-font text-indigo-900 uppercase tracking-tight">دليل الإنتاجية (Ross 308)</h3>
            <p className="text-[8px] font-medium text-indigo-400">بيانات استرشادية من شركة Aviagen</p>
          </div>
        </div>
      </div>

      {/* ── Stats Grid (3 Columns) ── */}
      <div className="grid grid-cols-3 gap-2">
        {/* Temperature */}
        <GuidelineItem
          label="الحرارة المثالية"
          value={targets.targetTemp.toFixed(1)}
          unit="°"
          icon={<ThermometerSun className="h-4 w-4" />}
          subValue={`${targets.minTemp}° - ${targets.maxTemp}°`}
        />

        {/* Feed */}
        <GuidelineItem
          label="كمية الطعام"
          value={String(Math.round(targets.feedBags))}
          unit="كيس"
          icon={<Wheat className="h-4 w-4" />}
          subValue={`${weight.value} ${weight.unit}`}
        />

        {/* Dimming */}
        <GuidelineItem
          label="ساعات الإظلام"
          value={String(targets.dimmingHours)}
          unit="س"
          icon={<Moon className="h-4 w-4" />}
          subValue={targets.dimmingDist}
        />
      </div>
    </div>
  )
}

function GuidelineItem({
  label, value, unit, icon, subValue
}: {
  label: string; value: string; unit: string; icon: React.ReactNode; subValue?: string
}) {
  return (
    <div className="flex flex-col items-center justify-center rounded-2xl bg-white/60 p-3 text-center transition-all border border-indigo-50/50">
      <div className="mb-2 flex items-center justify-center text-indigo-500 opacity-80">
        {icon}
      </div>

      <span className="mb-1 text-[8px] font-bold text-indigo-400 Arabic-font">
        {label}
      </span>

      <div className="flex items-baseline gap-0.5">
        <span className="text-base font-black tabular-nums text-indigo-900 leading-none">
          {value}
        </span>
        <span className="text-[9px] font-bold text-indigo-600/60">{unit}</span>
      </div>

      {subValue && (
        <span className="mt-1.5 text-[8px] font-bold text-indigo-400/80 Arabic-font leading-tight">
          {subValue}
        </span>
      )}
    </div>
  )
}


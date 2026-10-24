'use client'

import { useQuery } from '@tanstack/react-query'
import { Gauge } from 'lucide-react'
import { Card } from '@/components/ui/Card'
import { useFarmStore } from '@/stores/farm.store'
import { cn } from '@/lib/utils'
import {
  kpisApi, fcrTone, mortalityTone, epefTone, marginTone, missingReason, fmt,
  TONE_TEXT, TONE_BORDER, type FlockKpis, type KpiTone, type KpiMissingCode,
} from '@/lib/api/kpis'

/** مفتاح الاستعلام تحت ['flock', id] فيتحدّث تلقائياً كلما أُبطل كاش الفوج بعد أي إدخال */
export const flockKpisKey = (flockId: number, farmId?: number) => ['flock', flockId, 'kpis', farmId] as const

export function useFlockKpis(flockId: number, enabled = true) {
  const farmId = useFarmStore((s) => s.currentFarm?.id)
  return useQuery({
    queryKey: flockKpisKey(flockId, farmId),
    queryFn: () => kpisApi.getFlockKpis(flockId),
    enabled: enabled && !!farmId,
    staleTime: 2 * 60_000,
    gcTime: 10 * 60_000,
  })
}

interface KpiDef {
  label: string
  hint: string
  value: (k: FlockKpis) => number | null
  unit: string
  digits?: number
  tone?: (v: number | null) => KpiTone
  needs: KpiMissingCode[]
}

const KPIS: KpiDef[] = [
  { label: 'معامل التحويل', hint: 'كم كغ علف لكل كغ لحم', value: (k) => k.fcr, unit: '', digits: 2, tone: fcrTone, needs: ['feed', 'feed_unit', 'sales', 'current_weight'] },
  { label: 'النفوق', hint: 'نسبة النافق من الكتاكيت', value: (k) => k.mortality_pct, unit: '%', digits: 1, tone: mortalityTone, needs: ['initial_count'] },
  { label: 'متوسط الوزن', hint: 'وزن الطير الحي عند البيع', value: (k) => k.avg_weight_kg, unit: 'كغ', digits: 2, needs: ['sales'] },
  { label: 'كفاءة الإنتاج', hint: 'EPEF: الحيوية × الوزن ÷ (العمر × التحويل)', value: (k) => k.epef, unit: '', digits: 0, tone: epefTone, needs: ['feed', 'feed_unit', 'sales', 'current_weight'] },
  { label: 'تكلفة الكيلو', hint: 'كل التكاليف ÷ الوزن الحي', value: (k) => k.cost_per_kg, unit: '$', digits: 3, needs: ['costs', 'sales', 'current_weight'] },
  { label: 'ربح الكيلو', hint: 'سعر بيع الكيلو − تكلفته', value: (k) => k.margin_per_kg, unit: '$', digits: 3, tone: marginTone, needs: ['sale_prices', 'sales', 'costs', 'current_weight'] },
]

export function FlockKpiSection({ flockId }: { flockId: number }) {
  const { data: kpis, isLoading, isError } = useFlockKpis(flockId)

  if (isError) return null

  // تنبيهات لا تُلغي المؤشرات لكنها تجعلها تقريبية
  const soft = kpis?.missing.filter((m) => ['feed_unit', 'feed_prices', 'sale_prices'].includes(m.code)) ?? []
  const estimated = !!kpis && kpis.birds_remaining > 0 && kpis.estimated_remaining_kg !== null

  return (
    <section className="space-y-2">
      <h2 className="flex items-center gap-1.5 text-sm font-bold text-slate-700 dark:text-slate-200">
        <Gauge className="h-4 w-4 text-primary-500" />
        مؤشرات الأداء
      </h2>
      <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-6">
        {KPIS.map((def) => {
          if (isLoading || !kpis) {
            return <div key={def.label} className="h-[92px] animate-pulse rounded-2xl bg-slate-100 dark:bg-slate-800" />
          }
          const v = def.value(kpis)
          const tone = def.tone ? def.tone(v) : 'none'
          const reason = v === null ? missingReason(kpis, def.needs) : null
          return (
            <Card key={def.label} className={cn('px-3 py-2.5', TONE_BORDER[tone])}>
              <p className="text-xs font-semibold text-slate-500 dark:text-slate-400">{def.label}</p>
              <p className={cn('mt-0.5 text-lg font-black tabular-nums leading-tight', TONE_TEXT[tone])} dir="ltr">
                {v === null ? '—' : def.unit === '$' ? `$${fmt(v, def.digits)}` : `${fmt(v, def.digits)}${def.unit ? ` ${def.unit}` : ''}`}
              </p>
              <p className="mt-0.5 text-[10px] leading-snug text-slate-400 dark:text-slate-500">
                {reason ?? def.hint}
              </p>
            </Card>
          )
        })}
      </div>
      {(soft.length > 0 || estimated) && (
        <p className="text-[11px] leading-relaxed text-amber-600 dark:text-amber-400">
          {estimated && 'يشمل وزناً تقديرياً للطيور الباقية (حسب آخر بيع). '}
          {soft.map((m) => m.message).join(' · ')}
        </p>
      )}
    </section>
  )
}

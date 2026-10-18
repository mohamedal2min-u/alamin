'use client'

import Link from 'next/link'
import { useQuery } from '@tanstack/react-query'
import { ArrowRight, Trophy } from 'lucide-react'
import { useFarmStore } from '@/stores/farm.store'
import { canAccessRoute, useCurrentRole } from '@/lib/roles'
import { cn, formatDate } from '@/lib/utils'
import {
  kpisApi, fcrTone, mortalityTone, epefTone, marginTone, fmt, TONE_TEXT,
  type FlockKpis, type KpiTone,
} from '@/lib/api/kpis'

interface Column {
  key: string
  label: string
  hint: string
  value: (k: FlockKpis) => number | null
  digits: number
  money?: boolean
  /** أي اتجاه أفضل: low = الأقل أفضل؛ بلا قيمة = لا تمييز */
  better?: 'low' | 'high'
  tone?: (v: number | null) => KpiTone
}

const COLUMNS: Column[] = [
  { key: 'age', label: 'العمر', hint: 'يوم', value: (k) => k.age_days || null, digits: 0 },
  { key: 'mortality', label: 'النفوق', hint: '%', value: (k) => k.mortality_pct, digits: 1, better: 'low', tone: mortalityTone },
  { key: 'weight', label: 'متوسط الوزن', hint: 'كغ', value: (k) => k.avg_weight_kg, digits: 2, better: 'high' },
  { key: 'fcr', label: 'التحويل', hint: 'كغ علف/كغ', value: (k) => k.fcr, digits: 2, better: 'low', tone: fcrTone },
  { key: 'adg', label: 'النمو اليومي', hint: 'غ/يوم', value: (k) => k.adg_g, digits: 0, better: 'high' },
  { key: 'epef', label: 'الكفاءة EPEF', hint: '', value: (k) => k.epef, digits: 0, better: 'high', tone: epefTone },
  { key: 'cost_bird', label: 'تكلفة الطير', hint: '$/كتكوت', value: (k) => k.cost_per_bird, digits: 2, money: true, better: 'low' },
  { key: 'cost_kg', label: 'تكلفة الكيلو', hint: '$/كغ', value: (k) => k.cost_per_kg, digits: 3, money: true, better: 'low' },
  { key: 'rev_kg', label: 'سعر الكيلو', hint: '$/كغ', value: (k) => k.revenue_per_kg, digits: 3, money: true, better: 'high' },
  { key: 'margin_kg', label: 'ربح الكيلو', hint: '$/كغ', value: (k) => k.margin_per_kg, digits: 3, money: true, better: 'high', tone: marginTone },
  { key: 'feed_share', label: 'حصة العلف', hint: '% من التكلفة', value: (k) => k.feed_cost_share_pct, digits: 1, better: 'low' },
]

/** أفضل قيمة في كل عمود (بين القيم المعروفة فقط، ويلزم فوجان على الأقل) */
function bestByColumn(rows: FlockKpis[]): Record<string, number | null> {
  const out: Record<string, number | null> = {}
  for (const col of COLUMNS) {
    if (!col.better) { out[col.key] = null; continue }
    const vals = rows.map(col.value).filter((v): v is number => v !== null)
    out[col.key] = vals.length < 2 ? null : col.better === 'low' ? Math.min(...vals) : Math.max(...vals)
  }
  return out
}

export default function FlockComparisonPage() {
  const farmId = useFarmStore((s) => s.currentFarm?.id)
  const role = useCurrentRole()
  const { data: rows = [], isLoading, isError } = useQuery({
    queryKey: ['reports', 'flock-comparison', farmId],
    queryFn: kpisApi.getFlockComparison,
    enabled: !!farmId,
    staleTime: 5 * 60_000,
    gcTime: 15 * 60_000,
  })

  const best = bestByColumn(rows)

  return (
    <div className="space-y-4 pb-20 sm:pb-8">
      <div className="flex items-center gap-2">
        {canAccessRoute(role, '/reports') && (
          <Link href="/reports" className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800" aria-label="رجوع">
            <ArrowRight className="h-4 w-4" />
          </Link>
        )}
        <div>
          <h1 className="text-xl font-bold text-slate-900 dark:text-slate-100">مقارنة الأفواج</h1>
          <p className="text-xs text-slate-500 dark:text-slate-400">آخر 20 فوجاً — الأفضل في كل عمود مميَّز <Trophy className="inline h-3 w-3 text-amber-500" /></p>
        </div>
      </div>

      {isLoading ? (
        <div className="h-64 animate-pulse rounded-2xl bg-slate-100 dark:bg-slate-800" />
      ) : isError ? (
        <p className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-300">تعذّر تحميل المقارنة</p>
      ) : rows.length === 0 ? (
        <p className="rounded-xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">لا توجد أفواج نشطة أو مغلقة بعد</p>
      ) : (
        <div className="overflow-x-auto rounded-2xl border border-slate-200/60 bg-white dark:border-slate-700/60 dark:bg-slate-800" style={{ boxShadow: 'var(--shadow-card)' }}>
          <table className="min-w-max w-full text-xs">
            <thead>
              <tr className="border-b border-slate-100 text-slate-500 dark:border-slate-700 dark:text-slate-400">
                <th className="sticky right-0 z-10 bg-white px-3 py-2.5 text-start font-semibold dark:bg-slate-800">الفوج</th>
                {COLUMNS.map((col) => (
                  <th key={col.key} className="px-2.5 py-2.5 text-center font-semibold">
                    <span className="block whitespace-nowrap">{col.label}</span>
                    {col.hint && <span className="block text-[10px] font-normal text-slate-400">{col.hint}</span>}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((k) => (
                <tr key={k.flock.id} className="border-b border-slate-50 last:border-0 dark:border-slate-700/50">
                  <td className="sticky right-0 z-10 bg-white px-3 py-2 dark:bg-slate-800">
                    <Link href={`/flocks/${k.flock.id}`} className="block max-w-[9rem] truncate font-bold text-slate-800 hover:text-primary-600 dark:text-slate-100">
                      {k.flock.name}
                    </Link>
                    <span className="block whitespace-nowrap text-[10px] text-slate-400">
                      {k.flock.status === 'active' ? 'نشط · ' : ''}{k.flock.start_date ? formatDate(k.flock.start_date) : ''}
                    </span>
                  </td>
                  {COLUMNS.map((col) => {
                    const v = col.value(k)
                    const isBest = v !== null && best[col.key] === v
                    const tone = col.tone ? col.tone(v) : 'none'
                    return (
                      <td key={col.key} className="px-2.5 py-2 text-center">
                        <span
                          dir="ltr"
                          className={cn(
                            'inline-block rounded-md px-1.5 py-0.5 tabular-nums font-semibold',
                            TONE_TEXT[tone],
                            v === null && 'text-slate-300 dark:text-slate-600',
                            isBest && 'bg-amber-100 ring-1 ring-amber-300 dark:bg-amber-900/30 dark:ring-amber-700',
                          )}
                        >
                          {v === null ? '—' : col.money ? `$${fmt(v, col.digits)}` : fmt(v, col.digits)}
                        </span>
                      </td>
                    )
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <p className="text-[11px] leading-relaxed text-slate-400 dark:text-slate-500">
        التحويل: كم كغ علف لكل كغ لحم (جيد ≤ 1.7) · النفوق: جيد ≤ 5% · EPEF: جيد ≥ 300 · «—» تعني أن البيانات ناقصة (لا مبيعات، أو لا علف، أو بلا أسعار). الأفواج النشطة تشمل وزناً تقديرياً للطيور الباقية.
      </p>
    </div>
  )
}

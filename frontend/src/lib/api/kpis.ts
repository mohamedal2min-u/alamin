import { apiClient } from './client'

export type KpiMissingCode =
  | 'initial_count' | 'sales' | 'sale_prices' | 'feed' | 'feed_unit' | 'feed_prices' | 'costs' | 'current_weight'

/** مؤشرات أداء الفوج — كل قيمة null تعني أن مدخلاتها ناقصة (انظر missing) */
export interface FlockKpis {
  flock: { id: number; name: string; status: string; start_date: string | null; close_date: string | null }
  initial_birds: number
  mortality: number
  mortality_pct: number | null
  livability_pct: number | null
  birds_sold: number
  birds_remaining: number
  sold_weight_kg: number
  avg_weight_kg: number | null
  current_weight_kg: number | null
  estimated_remaining_kg: number | null
  live_weight_kg: number | null
  age_days: number
  feed_kg: number | null
  feed_unknown_units: string[]
  fcr: number | null
  adg_g: number | null
  epef: number | null
  total_cost: number
  revenue: number
  cost_per_bird: number | null
  cost_per_kg: number | null
  feed_cost: number
  feed_cost_share_pct: number | null
  revenue_per_kg: number | null
  margin_per_kg: number | null
  unrecovered_cost: number
  cost_per_live_bird: number | null
  break_even_price_per_kg: number | null
  missing: { code: KpiMissingCode; message: string }[]
}

export const kpisApi = {
  getFlockKpis: async (flockId: number) => {
    const { data } = await apiClient.get<{ data: FlockKpis }>(`/flocks/${flockId}/kpis`)
    return data.data
  },

  getFlockComparison: async () => {
    const { data } = await apiClient.get<{ data: FlockKpis[] }>('/reports/flock-comparison')
    return data.data
  },
}

// ── المعايير المرجعية للفروج (تلوين جيد/مقبول/ضعيف) ─────────────────────────

export type KpiTone = 'good' | 'warn' | 'bad' | 'none'

export function fcrTone(v: number | null): KpiTone {
  if (v === null) return 'none'
  return v <= 1.7 ? 'good' : v <= 1.9 ? 'warn' : 'bad'
}

export function mortalityTone(v: number | null): KpiTone {
  if (v === null) return 'none'
  return v <= 5 ? 'good' : v <= 8 ? 'warn' : 'bad'
}

export function epefTone(v: number | null): KpiTone {
  if (v === null) return 'none'
  return v >= 300 ? 'good' : v >= 250 ? 'warn' : 'bad'
}

export function marginTone(v: number | null): KpiTone {
  if (v === null) return 'none'
  return v > 0 ? 'good' : v === 0 ? 'warn' : 'bad'
}

export const TONE_TEXT: Record<KpiTone, string> = {
  good: 'text-emerald-700 dark:text-emerald-400',
  warn: 'text-amber-600 dark:text-amber-400',
  bad: 'text-red-600 dark:text-red-400',
  none: 'text-slate-900 dark:text-slate-100',
}

export const TONE_BORDER: Record<KpiTone, string> = {
  good: 'border-emerald-200 dark:border-emerald-800/60',
  warn: 'border-amber-200 dark:border-amber-800/60',
  bad: 'border-red-200 dark:border-red-800/60',
  none: '',
}

/** سبب غياب المؤشر: أول مدخل ناقص من مدخلاته (بالترتيب المعطى) */
export function missingReason(kpis: FlockKpis, codes: KpiMissingCode[]): string | null {
  for (const code of codes) {
    const hit = kpis.missing.find((m) => m.code === code)
    if (hit) return hit.message
  }
  return null
}

export const fmt = (v: number | null, digits = 2) =>
  v === null ? '—' : new Intl.NumberFormat('en-US', { maximumFractionDigits: digits, minimumFractionDigits: 0 }).format(v)

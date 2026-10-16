'use client'

import { useState } from 'react'
import { Check, Loader2, Tag } from 'lucide-react'
import { accountingApi } from '@/lib/api/accounting'
import type { ReviewItem } from '@/lib/api/accounting'
import { cn, formatNumber } from '@/lib/utils'

// بطاقات حسب الصنف (مراحل العلف، أصناف الأدوية): إجماليات الصنف + تسعير كل النقلات دفعة واحدة

export interface ItemGroup {
  name: string
  unit: string
  rows: ReviewItem[]
  count: number
  quantity: number
  kg: number
  unpriced: number
  total: number
}

export function groupByItem(rows: ReviewItem[]): ItemGroup[] {
  const map = new Map<string, ItemGroup>()
  for (const r of rows) {
    if (r.type !== 'expense' || !r.item_name) continue
    let g = map.get(r.item_name)
    if (!g) {
      g = { name: r.item_name, unit: r.quantity_unit ?? '', rows: [], count: 0, quantity: 0, kg: 0, unpriced: 0, total: 0 }
      map.set(r.item_name, g)
    }
    g.rows.push(r)
    g.count += 1
    g.quantity += r.quantity ?? 0
    g.kg += r.computed_quantity ?? 0
    g.total += r.total_amount ?? 0
    if (r.unit_price == null || r.unit_price <= 0) g.unpriced += 1
    if (!g.unit && r.quantity_unit) g.unit = r.quantity_unit
  }
  return Array.from(map.values()).sort((a, b) => stageRank(a.name) - stageRank(b.name) || a.name.localeCompare(b.name, 'ar'))
}

// ترتيب المراحل طبيعياً (أولى، ثانية، ثالثة...) لا أبجدياً
const STAGES = ['اول', 'ثاني', 'ثالث', 'رابع', 'خامس']
function stageRank(name: string): number {
  const n = name.replace(/[أإآ]/g, 'ا').replace(/ى/g, 'ي')
  const i = STAGES.findIndex((w) => n.includes(w))
  return i === -1 ? STAGES.length : i
}

const round4 = (n: number) => Math.round(n * 10_000) / 10_000
const kgPerUnit = (r: ReviewItem) => (r.quantity && r.computed_quantity ? r.computed_quantity / r.quantity : null)

interface Props {
  isFeed: boolean
  groups: ItemGroup[]
  selected: string | null
  onSelect: (name: string | null) => void
  onDone: () => void
}

export function ItemGroups({ isFeed, groups, selected, onSelect, onDone }: Props) {
  const [pricing, setPricing] = useState<string | null>(null)

  if (groups.length === 0) return null
  const pricingGroup = groups.find((g) => g.name === pricing)
  return (
    <section className="space-y-2">
      <h2 className="text-sm font-extrabold text-slate-700 dark:text-slate-200">{isFeed ? 'مراحل العلف' : 'أصناف الأدوية'}</h2>
      <ul className="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-800">
        {groups.map((g) => (
          <li key={g.name}>
            <GroupRow
              g={g}
              isFeed={isFeed}
              active={selected === g.name}
              onSelect={() => onSelect(selected === g.name ? null : g.name)}
              onPrice={() => setPricing(pricing === g.name ? null : g.name)}
            />
            {pricing === g.name && pricingGroup && (
              <div className="border-t border-slate-100 p-3 dark:border-slate-700">
                <BulkPrice key={pricing} g={pricingGroup} isFeed={isFeed} onClose={() => setPricing(null)} onDone={onDone} />
              </div>
            )}
          </li>
        ))}
      </ul>
    </section>
  )
}

function GroupRow({ g, isFeed, active, onSelect, onPrice }: {
  g: ItemGroup; isFeed: boolean; active: boolean; onSelect: () => void; onPrice: () => void
}) {
  const priced = g.count - g.unpriced > 0
  const qty = isFeed && g.kg > 0 ? `${formatNumber(g.kg / 1000)} طن` : `${formatNumber(g.quantity)} ${g.unit}`.trim()
  return (
    <div className={cn('flex items-center gap-3 px-3.5 py-3', active && 'bg-primary-50 dark:bg-primary-900/20')}>
      <button type="button" onClick={onSelect} className="min-w-0 flex-1 text-right">
        <div className="truncate text-[14px] font-extrabold text-slate-800 dark:text-slate-100">{g.name}</div>
        <div className="mt-0.5 text-[11px] font-semibold text-slate-400">
          {g.count} نقلة · {qty}
          {g.unpriced > 0 && <span className="text-amber-600"> · {g.unpriced} بلا سعر</span>}
        </div>
      </button>
      <div className={cn('shrink-0 text-left text-[14px] font-extrabold tabular-nums', priced ? 'text-slate-800 dark:text-slate-100' : 'text-amber-600')}>
        {priced ? formatNumber(g.total) : 'بلا سعر'}
      </div>
      <button type="button" onClick={onPrice} className="flex shrink-0 items-center gap-1 rounded-xl bg-primary-600 px-3 py-2 text-[12px] font-bold text-white active:scale-95">
        <Tag className="h-3.5 w-3.5" /> سعر موحّد
      </button>
    </div>
  )
}

function BulkPrice({ g, isFeed, onClose, onDone }: { g: ItemGroup; isFeed: boolean; onClose: () => void; onDone: () => void }) {
  const [value, setValue] = useState('')
  const [onlyUnpriced, setOnlyUnpriced] = useState(true)
  const [running, setRunning] = useState(false)
  const [progress, setProgress] = useState(0)
  const [result, setResult] = useState<{ ok: number; failed: number } | null>(null)

  const open = (r: ReviewItem) => r.flock_status !== 'closed' && r.flock_status !== 'cancelled'
  const candidates = g.rows.filter((r) => open(r) && (!onlyUnpriced || r.unit_price == null || r.unit_price <= 0))
  // العلف: السعر للطن، فنحوّله لسعر الوحدة (كيس مثلاً) حسب وزن الوحدة في كل نقلة
  const convertible = candidates.filter((r) => !isFeed || kgPerUnit(r) !== null)
  const skippedNoWeight = candidates.length - convertible.length
  const price = parseFloat(value)
  const valid = Number.isFinite(price) && price > 0

  const unitPriceFor = (r: ReviewItem) => (isFeed ? round4((price * (kgPerUnit(r) as number)) / 1000) : round4(price))

  const run = async () => {
    if (!valid || convertible.length === 0) return
    setRunning(true); setProgress(0)
    let ok = 0, failed = 0
    for (const r of convertible) {
      try {
        await accountingApi.updateReviewItem(r.type, r.record_id, { unit_price: unitPriceFor(r) })
        ok += 1
      } catch {
        failed += 1
      }
      setProgress(ok + failed)
    }
    setRunning(false)
    setResult({ ok, failed })
    if (ok > 0) onDone()
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-extrabold text-slate-800 dark:text-slate-100">{isFeed ? 'سعر الطن' : `سعر الـ${g.unit || 'وحدة'}`} لكل نقلات {g.name}</h3>
        <button type="button" onClick={onClose} className="text-xs font-semibold text-slate-400">إغلاق</button>
      </div>

      <input
        type="number" inputMode="decimal" min="0" step="any" dir="ltr"
        value={value} onChange={(e) => setValue(e.target.value)} placeholder={isFeed ? 'سعر الطن' : `سعر الـ${g.unit || 'وحدة'}`}
        disabled={running}
        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-center text-lg font-bold tabular-nums outline-none focus:border-primary-600 dark:border-slate-600 dark:bg-slate-900"
      />

      <label className="flex items-center gap-2 text-[12px] font-semibold text-slate-600 dark:text-slate-300">
        <input type="checkbox" checked={onlyUnpriced} onChange={(e) => setOnlyUnpriced(e.target.checked)} disabled={running} className="h-4 w-4 accent-primary-600" />
        تطبيق على النقلات التي بلا سعر فقط (لا يغيّر الأسعار الموضوعة)
      </label>

      <p className="text-[12px] text-slate-500">
        سيُسعَّر <b className="tabular-nums">{convertible.length}</b> من {g.count} نقلة
        {skippedNoWeight > 0 && <span className="text-amber-600"> · {skippedNoWeight} بلا وزن وحدة فلا يمكن تحويلها (سعّرها نقلة نقلة)</span>}
        {g.rows.some((r) => !open(r)) && <span className="text-slate-400"> · نقلات الأفواج المغلقة لا تتغير</span>}
      </p>
      {isFeed && valid && convertible[0] && (
        <p className="text-[11px] text-slate-400">مثال: نقلة {formatNumber(convertible[0].quantity ?? 0)} {convertible[0].quantity_unit ?? ''} ← سعر الوحدة {formatNumber(unitPriceFor(convertible[0]))}</p>
      )}

      {result && (
        <p className={cn('text-[12px] font-bold', result.failed > 0 ? 'text-rose-600' : 'text-emerald-600')}>
          تم تسعير {result.ok} نقلة{result.failed > 0 ? ` · تعذّر ${result.failed}` : ''}
        </p>
      )}

      <button
        type="button"
        onClick={() => void run()}
        disabled={running || !valid || convertible.length === 0}
        className="flex w-full items-center justify-center gap-2 rounded-xl bg-primary-600 py-2.5 text-sm font-bold text-white disabled:opacity-40"
      >
        {running ? <><Loader2 className="h-4 w-4 animate-spin" /> {progress}/{convertible.length}</> : <><Check className="h-4 w-4" /> تطبيق السعر</>}
      </button>
    </div>
  )
}

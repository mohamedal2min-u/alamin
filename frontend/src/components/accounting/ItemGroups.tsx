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
  return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name, 'ar'))
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
  return (
    <div className="space-y-2.5">
      <div className="grid grid-cols-2 gap-2.5">
        {groups.map((g) => (
          <GroupCard
            key={g.name}
            g={g}
            isFeed={isFeed}
            active={selected === g.name}
            onSelect={() => onSelect(selected === g.name ? null : g.name)}
            onPrice={() => setPricing(pricing === g.name ? null : g.name)}
            pricingOpen={pricing === g.name}
          />
        ))}
      </div>
      {pricing && groups.find((g) => g.name === pricing) && (
        <BulkPrice
          key={pricing}
          g={groups.find((g) => g.name === pricing)!}
          isFeed={isFeed}
          onClose={() => setPricing(null)}
          onDone={onDone}
        />
      )}
    </div>
  )
}

function GroupCard({ g, isFeed, active, onSelect, onPrice, pricingOpen }: {
  g: ItemGroup; isFeed: boolean; active: boolean; onSelect: () => void; onPrice: () => void; pricingOpen: boolean
}) {
  const priced = g.count - g.unpriced
  const kgText = isFeed && g.kg > 0 ? (g.kg >= 1000 ? `${formatNumber(g.kg / 1000)} طن` : `${formatNumber(g.kg)} كغ`) : null
  return (
    <div className={cn('rounded-2xl border bg-white p-3 dark:bg-slate-800', active ? 'border-primary-600 ring-1 ring-primary-600' : 'border-slate-200 dark:border-slate-700')}>
      <button type="button" onClick={onSelect} className="block w-full text-right">
        <div className="flex items-start justify-between gap-2">
          <span className="text-[13px] font-extrabold leading-snug text-slate-800 dark:text-slate-100">{g.name}</span>
          <span className="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300">{g.count} نقلة</span>
        </div>
        <dl className="mt-2 space-y-1 text-[12px]">
          <div className="flex justify-between gap-2">
            <dt className="text-slate-400">الكمية</dt>
            <dd className="font-extrabold tabular-nums text-slate-800 dark:text-slate-100">
              {formatNumber(g.quantity)} {g.unit}
              {kgText && <span className="ms-1 text-[10px] font-semibold text-slate-400">({kgText})</span>}
            </dd>
          </div>
          <div className="flex justify-between gap-2">
            <dt className="text-slate-400">السعر</dt>
            <dd className={cn('font-extrabold tabular-nums', priced > 0 ? 'text-slate-800 dark:text-slate-100' : 'text-amber-600')}>
              {priced > 0 ? formatNumber(g.total) : 'لم يُحدَّد'}
            </dd>
          </div>
        </dl>
        {g.unpriced > 0 && priced > 0 && <div className="mt-1 text-[11px] font-semibold text-amber-600">{g.unpriced} بدون سعر</div>}
      </button>
      <button
        type="button"
        onClick={onPrice}
        className={cn(
          'mt-2.5 flex w-full items-center justify-center gap-1.5 rounded-xl py-2 text-[12px] font-bold transition-colors',
          pricingOpen ? 'bg-slate-800 text-white' : 'bg-primary-600 text-white'
        )}
      >
        <Tag className="h-3.5 w-3.5" /> {isFeed ? 'سعر الطن لكل النقلات' : 'سعر الوحدة لكل النقلات'}
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
    <div className="space-y-3 rounded-2xl border border-primary-200 bg-primary-50/40 p-3.5 dark:border-slate-600 dark:bg-slate-800">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-extrabold text-slate-800 dark:text-slate-100">{g.name}: {isFeed ? 'سعر الطن' : 'سعر الوحدة'} لكل النقلات</h3>
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

'use client'

import { useRef, useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { CheckCircle2, ChevronLeft, ChevronRight, Droplets, Layers, Loader2, Lock, Pill, ShoppingCart, Wheat } from 'lucide-react'
import { accountingApi } from '@/lib/api/accounting'
import type { CategoryBreakdown, ReviewItem } from '@/lib/api/accounting'
import { cn, formatNumber } from '@/lib/utils'
import { PriceWithHistory } from './PriceWithHistory'
import { ItemGroups, groupByItem } from './ItemGroups'

// ── لوحة المحاسب: ما المطلوب ← اختر الفئة ← سعّر السجلات (أو الصنف كاملاً) ─────

const CATEGORIES: { code: string | undefined; label: string; icon: React.ElementType }[] = [
  { code: undefined,   label: 'الكل',    icon: Layers },
  { code: 'feed',      label: 'علف',     icon: Wheat },
  { code: 'medicine',  label: 'دواء',    icon: Pill },
  { code: 'water',     label: 'ماء',     icon: Droplets },
  { code: 'sales',     label: 'مبيعات',  icon: ShoppingCart },
]

const CATEGORY_TONE: Record<string, string> = {
  feed:     'bg-amber-50 text-amber-700',
  medicine: 'bg-teal-50 text-teal-700',
  water:    'bg-sky-50 text-sky-700',
  sales:    'bg-emerald-50 text-emerald-700',
}

export function AccountantPrices({ flockId }: { flockId: string }) {
  const qc = useQueryClient()

  const [onlyPending, setOnlyPending] = useState(false)
  const [category, setCategory] = useState<string | undefined>(undefined)
  const [itemFilter, setItemFilter] = useState<string | null>(null)
  const [editingId, setEditingId] = useState<string | null>(null)
  const [mode, setMode] = useState<'price' | 'payment'>('price')
  const [value, setValue] = useState('')
  const [error, setError] = useState<string | null>(null)

  const flock_id = flockId || undefined

  // ملخص كل الفئات (العدد والكمية والسعر الإجمالي)
  const overview = useQuery({
    queryKey: ['accounting', 'review-queue', 'overview', flock_id],
    queryFn: () => accountingApi.getReviewQueue({ scope: 'all', flock_id, per_page: 1 }),
    staleTime: 15_000,
  })

  // كل سجلات الفئة المختارة (لتجميعها حسب الصنف وعرضها)
  const rowsQuery = useQuery({
    queryKey: ['accounting', 'review-queue', 'rows', category, flock_id],
    queryFn: async () => {
      const out: ReviewItem[] = []
      for (let page = 1; page <= 20; page++) {
        const r = await accountingApi.getReviewQueue({ scope: 'all', category, flock_id, per_page: 100, page })
        out.push(...r.data)
        if (r.data.length === 0 || out.length >= r.meta.total) break
      }
      return out
    },
    staleTime: 15_000,
  })

  const breakdown = overview.data?.summary.category_breakdown ?? []
  const allRows = rowsQuery.data ?? []
  const isUnpriced = (i: ReviewItem) => i.unit_price == null || i.unit_price <= 0
  const pendingTotal = breakdown.reduce((n, c) => n + (c.unpriced_count ?? 0), 0)

  // العلف (مراحل) والدواء (أصناف): قائمة الأصناف مع سعر موحّد لكل نقلاتها
  const groupsEnabled = category === 'feed' || category === 'medicine'
  const groups = groupsEnabled ? groupByItem(allRows) : []

  // القائمة: غير المسعّر أولاً
  const items = allRows
    .filter((i) => !itemFilter || i.item_name === itemFilter)
    .filter((i) => !onlyPending || isUnpriced(i))
    .sort((a, b) => Number(isUnpriced(b)) - Number(isUnpriced(a)))

  const refresh = () => qc.invalidateQueries({ queryKey: ['accounting', 'review-queue'] })

  const save = useMutation({
    mutationFn: ({ item, price }: { item: ReviewItem; price: number }) =>
      mode === 'payment'
        // الدفعة: إجمالي المدفوع/المستلم حتى الآن
        ? accountingApi.updateReviewItem(item.type, item.record_id, { paid_amount: price })
        // سعر البيع يُدخَل للطن ويُخزَّن للكيلو
        : accountingApi.updateReviewItem(item.type, item.record_id, {
            unit_price: item.type === 'sale' ? price / 1000 : price,
          }),
    onSuccess: () => {
      refresh()
      setEditingId(null)
      setValue('')
      setError(null)
    },
    onError: (e: unknown) => {
      setError(
        (e as { response?: { data?: { message?: string } } })?.response?.data?.message ??
          'تعذر حفظ السعر، حاول مرة أخرى'
      )
    },
  })

  const startPayment = (item: ReviewItem) => {
    setMode('payment')
    setEditingId(item.id)
    setError(null)
    setValue(String(item.paid_amount ?? 0))
  }

  const startEdit = (item: ReviewItem) => {
    setMode('price')
    setEditingId(item.id)
    setError(null)
    const shown = item.unit_price != null ? (item.type === 'sale' ? item.unit_price * 1000 : item.unit_price) : null
    setValue(shown != null ? String(shown) : '')
  }

  const submit = (item: ReviewItem) => {
    const price = parseFloat(value)
    if (!Number.isFinite(price) || price < 0) {
      setError(mode === 'payment' ? 'أدخل مبلغاً صحيحاً' : 'أدخل سعراً صحيحاً')
      return
    }
    save.mutate({ item, price })
  }

  const pickCategory = (code: string | undefined) => { setCategory(code); setItemFilter(null) }

  return (
    <div className="space-y-4" dir="rtl">
      {/* ١) ما المطلوب منك الآن */}
      <TodoBanner loading={overview.isLoading} pending={pendingTotal} />

      {/* ٢) الفئات: البطاقة نفسها هي زر الاختيار، وفيها العدد والكمية والسعر */}
      <CategoryCards breakdown={breakdown} selected={category} onSelect={pickCategory} />

      {/* ٣) العلف والدواء: الأصناف مع سعر موحّد */}
      {groupsEnabled && (
        <ItemGroups
          isFeed={category === 'feed'}
          groups={groups}
          selected={itemFilter}
          onSelect={setItemFilter}
          onDone={refresh}
        />
      )}

      {/* ٤) السجلات */}
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-extrabold text-slate-700 dark:text-slate-200">
          {itemFilter ? `نقلات ${itemFilter}` : 'السجلات'} <span className="text-slate-400">({items.length})</span>
        </h2>
        <button
          type="button"
          onClick={() => setOnlyPending((v) => !v)}
          className={cn(
            'rounded-full border px-3 py-1.5 text-xs font-bold transition-colors',
            onlyPending ? 'border-amber-500 bg-amber-500 text-white' : 'border-slate-200 bg-white text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300'
          )}
        >
          بانتظار السعر فقط
        </button>
      </div>

      {rowsQuery.isLoading ? (
        <div className="flex justify-center py-16"><Loader2 className="h-6 w-6 animate-spin text-slate-400" /></div>
      ) : items.length === 0 ? (
        <div className="flex flex-col items-center gap-2 py-16 text-slate-400">
          <CheckCircle2 className="h-12 w-12 text-primary-400" />
          <p className="text-sm font-semibold">{onlyPending ? 'كل الأسعار محدّدة' : 'لا توجد سجلات'}</p>
        </div>
      ) : (
        <ul className="space-y-3">
          {items.map((item) => (
            <PriceCard
              key={item.id}
              item={item}
              showCategory={!category}
              locked={item.flock_status === 'closed' || item.flock_status === 'cancelled'}
              isEditing={editingId === item.id}
              mode={mode}
              value={value}
              error={editingId === item.id ? error : null}
              isSaving={save.isPending && editingId === item.id}
              onValue={setValue}
              onEdit={() => startEdit(item)}
              onPay={() => startPayment(item)}
              onCancel={() => { setEditingId(null); setError(null) }}
              onSave={() => submit(item)}
            />
          ))}
        </ul>
      )}
    </div>
  )
}

function TodoBanner({ loading, pending }: { loading: boolean; pending: number }) {
  if (loading) return <div className="h-12 animate-pulse rounded-2xl bg-slate-100 dark:bg-slate-800" />
  return (
    <div className={cn('flex items-center gap-2 rounded-2xl px-4 py-3 text-sm font-extrabold text-white', pending > 0 ? 'bg-amber-500' : 'bg-emerald-600')}>
      {pending > 0 ? <>المطلوب منك: تسعير {pending} سجل</> : <><CheckCircle2 className="h-5 w-5" /> كل الأسعار محدّدة</>}
    </div>
  )
}

const CARD_TONE: Record<string, { icon: string; ring: string; tint: string }> = {
  all:      { icon: 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900', ring: 'ring-slate-700 dark:ring-slate-300', tint: 'bg-slate-50 dark:bg-slate-700/40' },
  feed:     { icon: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300', ring: 'ring-amber-500', tint: 'bg-amber-50/70 dark:bg-amber-900/20' },
  medicine: { icon: 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300', ring: 'ring-teal-500', tint: 'bg-teal-50/70 dark:bg-teal-900/20' },
  water:    { icon: 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300', ring: 'ring-sky-500', tint: 'bg-sky-50/70 dark:bg-sky-900/20' },
  sales:    { icon: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300', ring: 'ring-emerald-500', tint: 'bg-emerald-50/70 dark:bg-emerald-900/20' },
}

function CategoryCards({ breakdown, selected, onSelect }: { breakdown: CategoryBreakdown[]; selected: string | undefined; onSelect: (code: string | undefined) => void }) {
  const costs = breakdown.filter((c) => c.code !== 'sales')
  const all = {
    count: breakdown.reduce((n, c) => n + c.count, 0),
    unpriced: breakdown.reduce((n, c) => n + (c.unpriced_count ?? 0), 0),
    amount: costs.reduce((n, c) => n + (c.total_amount ?? 0), 0),
  }
  const cards = [
    { key: 'all', code: undefined as string | undefined, name: 'الكل', c: null as CategoryBreakdown | null },
    ...CATEGORIES.filter((x) => x.code).map((x) => ({ key: x.code as string, code: x.code, name: x.label, c: breakdown.find((b) => b.code === x.code) ?? null })),
  ]
  const stripRef = useRef<HTMLDivElement>(null)
  const [edges, setEdges] = useState({ start: true, end: cards.length <= 3 })

  // شريط أفقي: ثلاث بطاقات ظاهرة، وسهمان للتنقل (والسحب باللمس)
  const scroll = (dir: 1 | -1) => {
    const el = stripRef.current
    if (!el) return
    // الصفحة RTL: «التالي» يعني التمرير نحو اليسار
    el.scrollBy({ left: -dir * (el.clientWidth / 3), behavior: 'smooth' })
  }
  const updateEdges = () => {
    const el = stripRef.current
    if (!el) return
    const pos = Math.abs(el.scrollLeft)
    setEdges({ start: pos < 4, end: pos + el.clientWidth >= el.scrollWidth - 4 })
  }

  return (
    <div className="relative">
      <div
        ref={stripRef}
        onScroll={updateEdges}
        className="no-scrollbar flex snap-x snap-mandatory gap-2 overflow-x-auto scroll-smooth py-1"
      >
        {cards.map(({ key, code, name, c }) => {
          const Icon = CATEGORIES.find((x) => x.code === code)?.icon ?? Layers
          const tone = CARD_TONE[key] ?? CARD_TONE.all
          const active = selected === code
          const isAll = key === 'all'
          const count = isAll ? all.count : c?.count ?? 0
          const unpriced = isAll ? all.unpriced : c?.unpriced_count ?? 0
          const priced = count - unpriced > 0
          const amount = isAll ? all.amount : c?.total_amount ?? 0
          const kg = c?.computed_quantity ?? 0
          const qty = !isAll && c && (c.quantity ?? 0) > 0
            ? (kg > 0 ? `${formatNumber(kg / 1000)} طن` : `${formatNumber(c.quantity ?? 0)} ${c.quantity_unit ?? ''}`.trim())
            : null
          const empty = count === 0

          return (
            <button
              key={key}
              type="button"
              onClick={() => onSelect(code)}
              className={cn(
                'flex w-[calc((100%-1rem)/3)] shrink-0 snap-start flex-col items-center rounded-2xl border px-2 py-3 text-center transition-all active:scale-[0.98]',
                active ? cn('border-transparent ring-2', tone.ring, tone.tint) : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800',
                empty && !active && 'opacity-60'
              )}
            >
              <span className={cn('flex h-9 w-9 items-center justify-center rounded-xl', tone.icon)}><Icon className="h-5 w-5" /></span>
              <span className="mt-1.5 text-[13px] font-extrabold text-slate-800 dark:text-slate-100">{name}</span>

              <span className="mt-1.5 flex min-h-[2.5rem] flex-col items-center justify-center">
                {empty ? (
                  <span className="text-xs font-semibold text-slate-400">لا سجلات</span>
                ) : priced ? (
                  <>
                    <span className="text-base font-black tabular-nums leading-tight text-slate-800 sm:text-lg dark:text-slate-100">{formatNumber(amount)}</span>
                    <span className="text-[10px] font-semibold text-slate-400">{key === 'sales' ? 'مبيعات' : isAll ? 'إجمالي التكاليف' : 'تكلفة'}</span>
                  </>
                ) : (
                  <span className="text-xs font-bold text-amber-600">بانتظار السعر</span>
                )}
              </span>

              {!empty && (
                <span className="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                  {count} سجل{qty ? ` · ${qty}` : ''}
                </span>
              )}
              {unpriced > 0 && (
                <span className="mt-1.5 whitespace-nowrap rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">{unpriced} بلا سعر</span>
              )}
            </button>
          )
        })}
      </div>

      {!edges.start && (
        <button type="button" aria-label="السابق" onClick={() => scroll(-1)}
          className="absolute right-0 top-1/2 flex h-7 w-7 -translate-y-1/2 translate-x-1/3 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-md dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">
          <ChevronRight className="h-4 w-4" />
        </button>
      )}
      {!edges.end && (
        <button type="button" aria-label="التالي" onClick={() => scroll(1)}
          className="absolute left-0 top-1/2 flex h-7 w-7 -translate-x-1/3 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-md dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">
          <ChevronLeft className="h-4 w-4" />
        </button>
      )}
    </div>
  )
}

interface CardProps {
  item: ReviewItem
  showCategory: boolean
  locked: boolean
  isEditing: boolean
  mode: 'price' | 'payment'
  value: string
  error: string | null
  isSaving: boolean
  onValue: (v: string) => void
  onEdit: () => void
  onPay: () => void
  onCancel: () => void
  onSave: () => void
}

function PriceCard({ item, showCategory, locked, isEditing, mode, value, error, isSaving, onValue, onEdit, onPay, onCancel, onSave }: CardProps) {
  const unpriced = item.unit_price == null
  const unitLabel = mode === 'payment' ? 'إجمالي المدفوع حتى الآن' : item.type === 'sale' ? 'سعر الطن' : 'سعر الوحدة'
  const remaining = Number(item.remaining_amount ?? 0)

  return (
    <li className={cn('rounded-2xl border bg-white p-4 shadow-sm', unpriced ? 'border-amber-200' : 'border-slate-200')}>
      {/* العنوان + التصنيف */}
      <div className="flex items-start justify-between gap-3">
        <h3 className="text-[15px] font-bold leading-snug text-slate-800">{item.description}</h3>
        {showCategory && item.category_name && (
          <span className={cn('shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold', CATEGORY_TONE[item.category_code ?? ''] ?? 'bg-slate-100 text-slate-600')}>
            {item.category_name}
          </span>
        )}
      </div>

      {/* معلومات ثانوية */}
      <p className="mt-1 text-xs text-slate-400">
        {item.flock_name ?? '—'} • {item.entry_date ?? '—'}
        {item.quantity != null && <> • الكمية {formatNumber(item.quantity)} {item.quantity_unit ?? ''}</>}
      </p>

      {isEditing ? (
        <div className="mt-3 space-y-2">
          <label className="text-xs font-semibold text-slate-500">{unitLabel}</label>
          <div className="flex gap-2">
            <input
              autoFocus
              type="number"
              inputMode="decimal"
              min={0}
              value={value}
              onChange={(e) => onValue(e.target.value)}
              className="h-12 min-w-0 flex-1 rounded-xl border border-slate-300 px-3 text-lg font-bold text-slate-800 outline-none focus:border-primary-500"
              placeholder="0"
            />
            <button
              onClick={onSave}
              disabled={isSaving}
              className="h-12 rounded-xl bg-primary-600 px-5 text-sm font-bold text-white active:scale-95 disabled:opacity-60"
            >
              {isSaving ? '...' : 'حفظ'}
            </button>
            <button onClick={onCancel} className="h-12 rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-500">
              إلغاء
            </button>
          </div>
          {mode === 'payment' && (
            <p className="text-[11px] text-slate-400">الإجمالي {formatNumber(item.total_amount)} — يُسجَّل كل تعديل على الدفعات باسمك ولا يُحذف.</p>
          )}
          {mode === 'price' && item.unit_price != null && (
            <p className="text-[11px] text-slate-400">السعر القديم سيبقى ظاهراً مشطوباً ويُسجَّل التعديل باسمك.</p>
          )}
          {error && <p className="text-xs font-semibold text-red-600">{error}</p>}
        </div>
      ) : (
        <div className="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3">
          <div className="text-sm text-slate-500">
            <PriceWithHistory item={item} />
            {!unpriced && (
              <div className="mt-0.5 text-xs text-slate-400">
                الإجمالي: {formatNumber(item.total_amount)}
                {Number(item.paid_amount ?? 0) > 0 && <> • مدفوع {formatNumber(Number(item.paid_amount))}</>}
                {remaining > 0 && <> • <span className="font-bold text-red-600">متبقي {formatNumber(remaining)}</span></>}
              </div>
            )}
          </div>
          {locked ? (
            <span className="inline-flex shrink-0 items-center gap-1 rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300">
              <Lock className="h-3.5 w-3.5" /> فوج مغلق
            </span>
          ) : (
            <div className="flex shrink-0 gap-2">
              {!unpriced && remaining > 0 && (
                <button onClick={onPay} className="rounded-xl bg-emerald-50 px-4 py-2.5 text-sm font-bold text-emerald-700 active:scale-95 dark:bg-emerald-900/30 dark:text-emerald-300">
                  تسجيل دفعة
                </button>
              )}
              <button
                onClick={onEdit}
                className={cn(
                  'rounded-xl px-5 py-2.5 text-sm font-bold active:scale-95',
                  unpriced ? 'bg-primary-600 text-white' : 'border border-slate-200 bg-white text-slate-700'
                )}
              >
                {unpriced ? 'تحديد السعر' : 'تعديل'}
              </button>
            </div>
          )}
        </div>
      )}
    </li>
  )
}

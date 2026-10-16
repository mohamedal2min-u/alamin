'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { CheckCircle2, Droplets, Layers, Loader2, Lock, Pill, ShoppingCart, Wheat } from 'lucide-react'
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
      <TodoBanner loading={overview.isLoading} pending={pendingTotal} total={breakdown.reduce((n, c) => n + (c.total_amount ?? 0), 0)} />

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

function TodoBanner({ loading, pending, total }: { loading: boolean; pending: number; total: number }) {
  if (loading) return <div className="h-16 animate-pulse rounded-2xl bg-slate-100 dark:bg-slate-800" />
  return (
    <div className={cn('flex items-center justify-between rounded-2xl px-4 py-3.5', pending > 0 ? 'bg-amber-500 text-white' : 'bg-emerald-600 text-white')}>
      <div className="flex items-center gap-2 text-sm font-extrabold">
        {pending > 0 ? <>{pending} سجل بانتظار السعر</> : <><CheckCircle2 className="h-5 w-5" /> كل الأسعار محدّدة</>}
      </div>
      <div className="text-left">
        <div className="text-[10px] font-semibold opacity-80">الإجمالي</div>
        <div className="text-lg font-extrabold tabular-nums">{formatNumber(total)}</div>
      </div>
    </div>
  )
}

function CategoryCards({ breakdown, selected, onSelect }: { breakdown: CategoryBreakdown[]; selected: string | undefined; onSelect: (code: string | undefined) => void }) {
  const totalCount = breakdown.reduce((n, c) => n + c.count, 0)
  const cards = [
    { code: undefined as string | undefined, name: 'الكل', count: totalCount, c: null as CategoryBreakdown | null },
    ...CATEGORIES.filter((x) => x.code).map((x) => {
      const c = breakdown.find((b) => b.code === x.code) ?? null
      return { code: x.code, name: x.label, count: c?.count ?? 0, c }
    }),
  ]
  return (
    <div className="-mx-1 flex snap-x gap-2 overflow-x-auto px-1 pb-1">
      {cards.map(({ code, name, count, c }) => {
        const Icon = CATEGORIES.find((x) => x.code === code)?.icon ?? Layers
        const active = selected === code
        const unpriced = c?.unpriced_count ?? 0
        const kg = c?.computed_quantity ?? 0
        const qty = c && (c.quantity ?? 0) > 0
          ? (kg > 0 ? `${formatNumber(kg / 1000)} طن` : `${formatNumber(c.quantity ?? 0)} ${c.quantity_unit ?? ''}`.trim())
          : null
        const priced = c ? count - unpriced > 0 : false
        return (
          <button
            key={name}
            type="button"
            onClick={() => onSelect(code)}
            className={cn(
              'relative w-[7.5rem] shrink-0 snap-start rounded-2xl border p-3 text-right transition-colors',
              active ? 'border-primary-600 bg-primary-600 text-white' : 'border-slate-200 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200'
            )}
          >
            {unpriced > 0 && (
              <span className="absolute -top-1.5 left-2 rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-bold text-white">{unpriced}</span>
            )}
            <div className="flex items-center gap-1.5 text-[13px] font-extrabold">
              <Icon className="h-4 w-4 shrink-0" /> {name}
            </div>
            <div className={cn('mt-1.5 text-[11px] font-semibold', active ? 'text-white/80' : 'text-slate-400')}>
              {count} سجل{qty ? ` · ${qty}` : ''}
            </div>
            {c && (
              <div className={cn('mt-0.5 truncate text-[13px] font-extrabold tabular-nums', !priced && !active && 'text-amber-600')}>
                {priced ? formatNumber(c.total_amount ?? 0) : 'بلا سعر'}
              </div>
            )}
          </button>
        )
      })}
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

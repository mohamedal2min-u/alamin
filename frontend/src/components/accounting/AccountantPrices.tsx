'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { CheckCircle2, Coins, Droplets, Egg, FlaskConical, Layers, Loader2, Lock, Package, Pill, ShoppingCart, SprayCan, Wheat } from 'lucide-react'
import { accountingApi, PAYMENT_METHOD_LABELS } from '@/lib/api/accounting'
import type { CategoryBreakdown, PaymentMethod, ReviewItem } from '@/lib/api/accounting'
import { cn, formatNumber, getTodayLocalISO } from '@/lib/utils'
import { PriceWithHistory } from './PriceWithHistory'
import { ItemGroups, groupByItem } from './ItemGroups'
import { WaterTotal } from './WaterTotal'

// ── لوحة المحاسب: ما المطلوب ← اختر الفئة ← سعّر السجلات (أو الصنف كاملاً) ─────

const CATEGORIES: { code: string | undefined; label: string; icon: React.ElementType }[] = [
  { code: undefined,   label: 'الكل',    icon: Layers },
  { code: 'feed',      label: 'علف',     icon: Wheat },
  { code: 'medicine',  label: 'دواء',    icon: Pill },
  { code: 'water',     label: 'ماء',     icon: Droplets },
  { code: 'sales',     label: 'مبيعات',  icon: ShoppingCart },
]

// أيقونات الفئات الأخرى (الفئة غير المعروفة تأخذ أيقونة عامة)
const EXTRA_ICONS: Record<string, React.ElementType> = {
  uncategorized: Egg,
  farm_wash:     SprayCan,
  disinfectant:  FlaskConical,
  small:         Coins,
}

// «مصروفات ناعمة»: مصاريف متفرقة قيمتها أقل من 100$ (أو بلا سعر بعد)، خارج العلف والدواء والماء والمبيعات
const SMALL = 'small'
const SMALL_LIMIT = 100
const isSmallExpense = (i: ReviewItem) =>
  i.type === 'expense' && !['feed', 'medicine'].includes(i.category_code ?? '') && Number(i.total_amount ?? 0) < SMALL_LIMIT

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
  // الدفعة: تاريخها وطريقتها
  const [paidAt, setPaidAt] = useState(getTodayLocalISO())
  const [method, setMethod] = useState<PaymentMethod>('cash')

  const flock_id = flockId || undefined

  // ملخص كل الفئات (العدد والكمية والسعر الإجمالي)
  const overview = useQuery({
    queryKey: ['accounting', 'review-queue', 'overview', flock_id],
    queryFn: () => accountingApi.getReviewQueue({ scope: 'all', flock_id, per_page: 1 }),
    staleTime: 15_000,
  })

  // كل سجلات فئة (لتجميعها حسب الصنف وعرضها)
  const fetchRows = async (cat: string | undefined) => {
    const out: ReviewItem[] = []
    for (let page = 1; page <= 20; page++) {
      const r = await accountingApi.getReviewQueue({ scope: 'all', category: cat, flock_id, per_page: 100, page })
      out.push(...r.data)
      if (r.data.length === 0 || out.length >= r.meta.total) break
    }
    return out
  }
  // المصروفات الناعمة تُحسب من كل السجلات، فنفس استعلام «الكل»
  const apiCategory = category === SMALL ? undefined : category
  const rowsQuery = useQuery({
    queryKey: ['accounting', 'review-queue', 'rows', apiCategory, flock_id],
    queryFn: () => fetchRows(apiCategory),
    staleTime: 15_000,
  })
  const everyRow = useQuery({
    queryKey: ['accounting', 'review-queue', 'rows', undefined, flock_id],
    queryFn: () => fetchRows(undefined),
    staleTime: 15_000,
  })

  const breakdown = overview.data?.summary.category_breakdown ?? []
  const isUnpriced = (i: ReviewItem) => i.unit_price == null || i.unit_price <= 0
  const smallRows = (everyRow.data ?? []).filter(isSmallExpense)
  const allRows = category === SMALL ? smallRows : rowsQuery.data ?? []
  const small = {
    count: smallRows.length,
    unpriced: smallRows.filter(isUnpriced).length,
    amount: smallRows.reduce((n, i) => n + Number(i.total_amount ?? 0), 0),
  }
  const pendingTotal = breakdown.reduce((n, c) => n + (c.unpriced_count ?? 0), 0)

  // العلف (مراحل) والدواء (أصناف): قائمة الأصناف مع سعر موحّد لكل نقلاتها
  const groupsEnabled = category === 'feed' || category === 'medicine'
  const groups = groupsEnabled ? groupByItem(allRows) : []

  // القائمة: غير المسعّر أولاً
  const items = allRows
    .filter((i) => !itemFilter || i.item_name === itemFilter)
    .filter((i) => !onlyPending || isUnpriced(i))
    .sort((a, b) => Number(isUnpriced(b)) - Number(isUnpriced(a)))

  const refresh = () => {
    qc.invalidateQueries({ queryKey: ['accounting', 'review-queue'] })
    // أرصدة الموردين والتجار تتغير مع كل دفعة
    qc.invalidateQueries({ queryKey: ['contacts'] })
  }

  const save = useMutation({
    mutationFn: ({ item, price }: { item: ReviewItem; price: number }) =>
      mode === 'payment'
        // دفعة جديدة مؤرَّخة تُضاف إلى المدفوع (لا تعديل للإجمالي التراكمي)
        ? accountingApi.recordPayment({
            payable_type: item.type,
            payable_id: item.record_id,
            amount: price,
            paid_at: paidAt,
            method,
          })
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
    setValue(String(Number(item.remaining_amount ?? 0)))
    setPaidAt(getTodayLocalISO())
    setMethod('cash')
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
    if (!Number.isFinite(price) || price < 0 || (mode === 'payment' && price <= 0)) {
      setError(mode === 'payment' ? 'أدخل مبلغاً صحيحاً' : 'أدخل سعراً صحيحاً')
      return
    }
    if (mode === 'payment' && price - Number(item.remaining_amount ?? 0) > 0.005) {
      setError(`الدفعة أكبر من المتبقي (${formatNumber(Number(item.remaining_amount ?? 0))})`)
      return
    }
    save.mutate({ item, price })
  }

  const pickCategory = (code: string | undefined) => { setCategory(code); setItemFilter(null) }

  return (
    <div className="space-y-4" dir="rtl">
      {/* ١) ما المطلوب منك الآن */}
      <TodoBanner loading={overview.isLoading} pending={pendingTotal} total={breakdown.reduce((n, c) => n + c.count, 0)} />

      {/* ٢) الفئات: البطاقة نفسها هي زر الاختيار، وفيها العدد والكمية والسعر */}
      <CategoryCards breakdown={breakdown} small={small} selected={category} onSelect={pickCategory} />

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

      {/* الماء: مبلغ إجمالي واحد يُقسَّم على الصهاريج */}
      {category === 'water' && !rowsQuery.isLoading && (
        <WaterTotal rows={allRows} flockSelected={!!flockId} onDone={refresh} />
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
              paidAt={paidAt}
              onPaidAt={setPaidAt}
              method={method}
              onMethod={setMethod}
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
  if (loading) return <div className="h-11 animate-pulse rounded-2xl bg-slate-100 dark:bg-slate-800" />
  // إنجاز المحاسبة كلها: نسبة السجلات المسعّرة من كل السجلات
  const done = total > 0 ? Math.floor(((total - pending) / total) * 100) : 100
  return (
    <div className="space-y-2">
      <div className={cn('flex h-11 items-center gap-2 rounded-2xl px-4 text-sm font-extrabold text-white', pending > 0 ? 'bg-amber-500' : 'bg-emerald-600')}>
        {pending > 0 ? <>المطلوب منك: تسعير {pending} سجل</> : <><CheckCircle2 className="h-5 w-5" /> كل الأسعار محدّدة</>}
      </div>
      <div className="flex items-center gap-2">
        <span className="h-2.5 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
          <span
            className={cn('block h-full rounded-full transition-all', done === 100 ? 'bg-emerald-500' : 'bg-red-500')}
            style={{ width: `${done}%` }}
          />
        </span>
        <span className="text-xs font-extrabold tabular-nums text-slate-600 dark:text-slate-300">المحاسبة {done}%</span>
      </div>
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

function CategoryCards({ breakdown, small, selected, onSelect }: {
  breakdown: CategoryBreakdown[]
  small: { count: number; unpriced: number; amount: number }
  selected: string | undefined
  onSelect: (code: string | undefined) => void
}) {
  const costs = breakdown.filter((c) => c.code !== 'sales')
  const all = {
    count: breakdown.reduce((n, c) => n + c.count, 0),
    unpriced: breakdown.reduce((n, c) => n + (c.unpriced_count ?? 0), 0),
    amount: costs.reduce((n, c) => n + (c.total_amount ?? 0), 0),
  }
  // بطاقة لكل صنف: الأصناف الأساسية أولاً، ثم أي فئة أخرى فيها سجلات، ثم المصروفات الناعمة
  const known = CATEGORIES.filter((x) => x.code).map((x) => x.code as string)
  const cards = [
    { key: 'all', code: undefined as string | undefined, name: 'الكل', c: null as CategoryBreakdown | null },
    ...CATEGORIES.filter((x) => x.code).map((x) => ({ key: x.code as string, code: x.code, name: x.label, c: breakdown.find((b) => b.code === x.code) ?? null })),
    ...breakdown.filter((b) => !known.includes(b.code)).map((b) => ({ key: b.code, code: b.code as string | undefined, name: b.name, c: b })),
    { key: SMALL, code: SMALL as string | undefined, name: 'مصروفات ناعمة', c: null as CategoryBreakdown | null },
  ]

  return (
    <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
      {cards.map(({ key, code, name, c }) => {
        const Icon = CATEGORIES.find((x) => x.code === code)?.icon ?? EXTRA_ICONS[key] ?? Package
        const tone = CARD_TONE[key] ?? CARD_TONE.all
        const active = selected === code
        const isAll = key === 'all'
        const isSmall = key === SMALL
        const count = isAll ? all.count : isSmall ? small.count : c?.count ?? 0
        const unpriced = isAll ? all.unpriced : isSmall ? small.unpriced : c?.unpriced_count ?? 0
        const priced = count - unpriced > 0
        const amount = isAll ? all.amount : isSmall ? small.amount : c?.total_amount ?? 0
        const kg = c?.computed_quantity ?? 0
        const qty = !isAll && c && (c.quantity ?? 0) > 0
          ? (kg > 0 ? `${formatNumber(kg / 1000)} طن` : `${formatNumber(c.quantity ?? 0)} ${c.quantity_unit ?? ''}`.trim())
          : null
        const empty = count === 0
        const done = count > 0 ? Math.floor(((count - unpriced) / count) * 100) : 0

        return (
          <button
            key={key}
            type="button"
            onClick={() => onSelect(code)}
            className={cn(
              'flex min-w-0 flex-col items-center rounded-2xl border px-2 py-3 text-center transition-all active:scale-[0.98]',
              active ? cn('border-transparent ring-2', tone.ring, tone.tint) : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800',
              empty && !active && 'opacity-60'
            )}
          >
            <span className={cn('flex h-9 w-9 items-center justify-center rounded-xl', tone.icon)}><Icon className="h-5 w-5" /></span>
            <span className="mt-1.5 text-[13px] font-extrabold leading-tight text-slate-800 dark:text-slate-100">{name}</span>
            {isSmall && <span className="text-[10px] font-semibold text-slate-400">أقل من {SMALL_LIMIT}$</span>}

            <span className="mt-1.5 flex min-h-[2.5rem] flex-col items-center justify-center">
              {empty ? (
                <span className="text-xs font-semibold text-slate-400">لا سجلات</span>
              ) : priced ? (
                <>
                  <span className="text-sm font-black tabular-nums leading-tight text-slate-800 sm:text-lg dark:text-slate-100">{formatNumber(amount)}</span>
                  <span className="text-[10px] font-semibold text-slate-400">{key === 'sales' ? 'مبيعات' : isAll ? 'إجمالي التكاليف' : 'تكلفة'}</span>
                </>
              ) : (
                <span className="text-xs font-bold text-amber-600">بانتظار السعر</span>
              )}
            </span>

            {!empty && (
              <span className="mt-1 flex flex-col text-[11px] font-semibold leading-snug text-slate-500 dark:text-slate-400">
                <span className="whitespace-nowrap">{count} سجل</span>
                {qty && <span className="whitespace-nowrap">{qty}</span>}
              </span>
            )}
            {unpriced > 0 && (
              <span className="mt-1.5 whitespace-nowrap rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">{unpriced} بلا سعر</span>
            )}

            {/* شريط الإنجاز: نسبة السجلات المسعّرة، يمتلئ بالأحمر حتى 100% */}
            {!empty && (
              <span className="mt-2 w-full">
                <span className="block h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                  <span
                    className={cn('block h-full rounded-full transition-all', done === 100 ? 'bg-emerald-500' : 'bg-red-500')}
                    style={{ width: `${done}%` }}
                  />
                </span>
                <span className="mt-0.5 block text-[10px] font-bold tabular-nums text-slate-500 dark:text-slate-400">{done}%</span>
              </span>
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
  paidAt: string
  onPaidAt: (v: string) => void
  method: PaymentMethod
  onMethod: (v: PaymentMethod) => void
  onEdit: () => void
  onPay: () => void
  onCancel: () => void
  onSave: () => void
}

function PriceCard({ item, showCategory, locked, isEditing, mode, value, error, isSaving, onValue, paidAt, onPaidAt, method, onMethod, onEdit, onPay, onCancel, onSave }: CardProps) {
  const unpriced = item.unit_price == null
  const unitLabel = mode === 'payment' ? (item.type === 'sale' ? 'المبلغ المقبوض الآن' : 'مبلغ الدفعة') : item.type === 'sale' ? 'سعر الطن' : 'سعر الوحدة'
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
        {item.contact_name && <> • {item.contact_name}</>}
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
            <>
              <div className="flex gap-2">
                <input
                  type="date"
                  value={paidAt}
                  max={getTodayLocalISO()}
                  onChange={(e) => onPaidAt(e.target.value)}
                  aria-label="تاريخ الدفعة"
                  className="h-11 min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 outline-none focus:border-primary-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                />
                <div className="flex shrink-0 overflow-hidden rounded-xl border border-slate-300 dark:border-slate-600">
                  {(Object.keys(PAYMENT_METHOD_LABELS) as PaymentMethod[]).map((m) => (
                    <button
                      key={m}
                      type="button"
                      onClick={() => onMethod(m)}
                      className={cn(
                        'h-11 px-3 text-xs font-bold',
                        method === m ? 'bg-primary-600 text-white' : 'bg-white text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                      )}
                    >
                      {PAYMENT_METHOD_LABELS[m]}
                    </button>
                  ))}
                </div>
              </div>
              <p className="text-[11px] text-slate-400">
                الإجمالي {formatNumber(item.total_amount)} • مدفوع {formatNumber(Number(item.paid_amount ?? 0))} • المتبقي {formatNumber(Number(item.remaining_amount ?? 0))} — تُسجَّل الدفعة باسمك ولا تُحذف.
              </p>
            </>
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

      {(item.payments?.length ?? 0) > 0 && <PaymentHistory item={item} />}
    </li>
  )
}

/** سجل الدفعات المؤرَّخ تحت البطاقة (الأقدم أولاً) */
function PaymentHistory({ item }: { item: ReviewItem }) {
  const payments = item.payments ?? []
  return (
    <details className="mt-3 rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-800/60">
      <summary className="cursor-pointer text-xs font-bold text-slate-600 dark:text-slate-300">
        {item.type === 'sale' ? 'سجل المقبوضات' : 'سجل الدفعات'} ({payments.length})
      </summary>
      <ul className="mt-2 space-y-1.5">
        {payments.map((p) => (
          <li key={p.id} className="flex items-center justify-between gap-2 text-xs">
            <span className="text-slate-500 dark:text-slate-400">
              <span className="tabular-nums">{p.paid_at ?? '—'}</span>
              {' • '}{p.is_opening ? 'رصيد افتتاحي' : PAYMENT_METHOD_LABELS[p.method] ?? p.method}
              {p.created_by_name && <> • {p.created_by_name}</>}
              {p.note && !p.is_opening && <> • {p.note}</>}
            </span>
            <span className={cn('shrink-0 font-bold tabular-nums', p.amount < 0 ? 'text-red-600' : 'text-emerald-700 dark:text-emerald-400')}>
              {formatNumber(p.amount)}
            </span>
          </li>
        ))}
      </ul>
    </details>
  )
}

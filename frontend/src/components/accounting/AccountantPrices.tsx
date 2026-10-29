'use client'

import { useState } from 'react'
import { createPortal } from 'react-dom'
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

// اسم الفئة كما يقرؤه المستخدم («شراء كتاكيت» في القاعدة ← «صوص»)
const displayCategory = (name: string | null | undefined) => (name === 'شراء كتاكيت' ? 'صوص' : name)

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
        // المبيعات والعلف تُسعَّر بالطن وتُخزَّن للكيلو/للكيس
        : accountingApi.updateReviewItem(item.type, item.record_id, {
            unit_price: price * priceBasis(item).factor,
          }),
    onSuccess: (_, { item }) => {
      refresh()
      setError(null)
      // تسجيل سريع: بعد حفظ السعر تفتح النافذة على السجل التالي بلا سعر
      const at = items.findIndex((i) => i.id === item.id)
      const next = [...items.slice(at + 1), ...items.slice(0, Math.max(at, 0))]
        .find((i) => i.id !== item.id && isUnpriced(i) && i.flock_status !== 'closed' && i.flock_status !== 'cancelled')
      if (mode === 'price' && next) {
        startEdit(next)
      } else {
        setEditingId(null)
        setValue('')
      }
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
    const shown = item.unit_price != null ? Math.round((item.unit_price / priceBasis(item).factor) * 1000) / 1000 : null
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

      {/* ٢) الفئات: البطاقة نفسها هي زر الاختيار، وفيها العدد والكمية والسعر */}
      <CategoryList loading={overview.isLoading} breakdown={breakdown} small={small} selected={category} onSelect={pickCategory} />

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
        <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-slate-50 text-[11px] font-extrabold text-slate-500 dark:bg-slate-700/50 dark:text-slate-300">
              <th className="px-2 py-2.5 text-right">التاريخ</th>
              <th className="px-2 py-2.5 text-right">البيان</th>
              <th className="px-2 py-2.5 text-right">الكمية</th>
              <th className="px-2 py-2.5 text-right">السعر</th>
              <th className="px-2 py-2.5 text-left">الإجمالي</th>
            </tr>
          </thead>
          <tbody>
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
          </tbody>
          <TableTotals items={items} />
        </table>
        </div>
      )}
      {items.length > 0 && <p className="text-xs text-slate-400">اضغط على أي سطر لتحديد السعر أو تعديله</p>}
    </div>
  )
}

// سطر المجموع تحت الجدول: الكمية تُجمع فقط إن كانت كل السطور بنفس الوحدة
function TableTotals({ items }: { items: ReviewItem[] }) {
  const bases = items.map(priceBasis)
  const unit = (b: ReturnType<typeof priceBasis>) => b.qtyText.replace(/^[\d.,\s]+/, '')
  const sameUnit = bases.every((b) => unit(b) === unit(bases[0]))
  const qty = bases.reduce((n, b) => n + b.qty, 0)
  const total = items.reduce((n, i) => n + Number(i.total_amount ?? 0), 0)
  const remaining = items.reduce((n, i) => n + (i.unit_price != null ? Number(i.remaining_amount ?? 0) : 0), 0)
  return (
    <tfoot>
      <tr className="border-t border-slate-200 bg-slate-100 font-black text-slate-900 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100">
        <td className="px-2 py-3" colSpan={2}>المجموع</td>
        <td className="whitespace-nowrap px-2 py-3 tabular-nums">{sameUnit && qty > 0 ? `${formatNumber(qty)} ${unit(bases[0])}` : ''}</td>
        <td />
        <td className="px-2 py-3 text-left tabular-nums">
          {formatNumber(total)}
          {remaining > 0 && <div className="text-[11px] font-bold text-red-600">متبقي {formatNumber(remaining)}</div>}
        </td>
      </tr>
    </tfoot>
  )
}

const CARD_TONE: Record<string, { icon: string; ring: string; tint: string }> = {
  all:      { icon: 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900', ring: 'ring-slate-700 dark:ring-slate-300', tint: 'bg-slate-50 dark:bg-slate-700/40' },
  feed:     { icon: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300', ring: 'ring-amber-500', tint: 'bg-amber-50/70 dark:bg-amber-900/20' },
  medicine: { icon: 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300', ring: 'ring-teal-500', tint: 'bg-teal-50/70 dark:bg-teal-900/20' },
  water:    { icon: 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300', ring: 'ring-sky-500', tint: 'bg-sky-50/70 dark:bg-sky-900/20' },
  sales:    { icon: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300', ring: 'ring-emerald-500', tint: 'bg-emerald-50/70 dark:bg-emerald-900/20' },
  small:    { icon: 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300', ring: 'ring-violet-500', tint: 'bg-violet-50/70 dark:bg-violet-900/20' },
}

function ProgressBar({ done, className }: { done: number; className?: string }) {
  return (
    <span className={cn('block w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700', className)}>
      <span
        className={cn('block h-full rounded-full transition-all', done === 100 ? 'bg-emerald-500' : 'bg-red-500')}
        style={{ width: `${done}%` }}
      />
    </span>
  )
}

// قائمة الفئات: ملخص المحاسبة كلها فوق، ثم سطر لكل صنف بانتظار السعر، والمكتمل ملخّص صغير تحت
function CategoryList({ loading, breakdown, small, selected, onSelect }: {
  loading: boolean
  breakdown: CategoryBreakdown[]
  small: { count: number; unpriced: number; amount: number }
  selected: string | undefined
  onSelect: (code: string | undefined) => void
}) {
  if (loading) return <div className="h-32 animate-pulse rounded-2xl bg-slate-100 dark:bg-slate-800" />

  const costs = breakdown.filter((c) => c.code !== 'sales')
  const total = breakdown.reduce((n, c) => n + c.count, 0)
  const pending = breakdown.reduce((n, c) => n + (c.unpriced_count ?? 0), 0)
  const amount = costs.reduce((n, c) => n + (c.total_amount ?? 0), 0)
  const allDone = total > 0 ? Math.floor(((total - pending) / total) * 100) : 100

  // الأصناف الأساسية أولاً، ثم أي فئة أخرى فيها سجلات، ثم المصروفات الناعمة
  const known = CATEGORIES.filter((x) => x.code).map((x) => x.code as string)
  const rows = [
    ...CATEGORIES.filter((x) => x.code).map((x) => ({ key: x.code as string, name: x.label, c: breakdown.find((b) => b.code === x.code) })),
    ...breakdown.filter((b) => !known.includes(b.code)).map((b) => ({ key: b.code, name: displayCategory(b.name) ?? b.name, c: b as CategoryBreakdown | undefined })),
  ]
    .filter((r) => r.c && r.c.count > 0)
    .map(({ key, name, c }) => {
      const kg = c!.computed_quantity ?? 0
      const sub = (c!.quantity ?? 0) > 0
        ? (kg > 0 ? `${formatNumber(kg / 1000)} طن` : `${formatNumber(c!.quantity ?? 0)} ${c!.quantity_unit ?? ''}`.trim())
        : `${c!.count} سجل`
      return { key, name, count: c!.count, unpriced: c!.unpriced_count ?? 0, amount: c!.total_amount ?? 0, sub }
    })
  if (small.count > 0) {
    rows.push({ key: SMALL, name: 'مصروفات ناعمة', count: small.count, unpriced: small.unpriced, amount: small.amount, sub: `أقل من ${SMALL_LIMIT}$` })
  }
  const todo = rows.filter((r) => r.unpriced > 0)
  const done = rows.filter((r) => r.unpriced === 0)
  const iconOf = (key: string) => CATEGORIES.find((x) => x.code === key)?.icon ?? EXTRA_ICONS[key] ?? Package

  return (
    <div className="space-y-3">
      {/* ملخص المحاسبة كلها (والضغط عليه يعرض كل السجلات) */}
      <button
        type="button"
        onClick={() => onSelect(undefined)}
        className={cn(
          'block w-full rounded-2xl border bg-white p-4 text-right transition-all dark:bg-slate-800',
          selected === undefined ? 'border-transparent ring-2 ring-slate-700 dark:ring-slate-300' : 'border-slate-200 dark:border-slate-700'
        )}
      >
        <span className="block text-sm font-bold text-slate-500 dark:text-slate-400">إجمالي التكاليف</span>
        <span className="block text-3xl font-black tabular-nums text-slate-900 dark:text-slate-100">{formatNumber(amount)} $</span>
        <span className="mt-2 flex items-center justify-between text-sm font-extrabold">
          <span className="text-slate-700 dark:text-slate-200">المحاسبة {allDone}%</span>
          {pending > 0
            ? <span className="text-amber-700 dark:text-amber-400">باقي {pending} سجل</span>
            : <span className="flex items-center gap-1 text-emerald-600"><CheckCircle2 className="h-4 w-4" /> كل الأسعار محدّدة</span>}
        </span>
        <ProgressBar done={allDone} className="mt-2 h-3" />
      </button>

      {/* بانتظار السعر: سطر لكل صنف */}
      {todo.map((r) => {
        const Icon = iconOf(r.key)
        const tone = CARD_TONE[r.key] ?? CARD_TONE.all
        const pct = Math.floor(((r.count - r.unpriced) / r.count) * 100)
        return (
          <button
            key={r.key}
            type="button"
            onClick={() => onSelect(r.key)}
            className={cn(
              'flex w-full items-center gap-3 rounded-2xl border bg-white p-3.5 text-right transition-all active:scale-[0.99] dark:bg-slate-800',
              selected === r.key ? cn('border-transparent ring-2', tone.ring, tone.tint) : 'border-slate-200 dark:border-slate-700'
            )}
          >
            <span className={cn('flex h-11 w-11 shrink-0 items-center justify-center rounded-xl', tone.icon)}><Icon className="h-6 w-6" /></span>
            <span className="min-w-0 flex-1">
              <span className="flex items-center justify-between gap-2">
                <span className="truncate text-base font-extrabold text-slate-900 dark:text-slate-100">{r.name}</span>
                <span className="shrink-0 text-lg font-black tabular-nums text-slate-900 dark:text-slate-100">{formatNumber(r.amount)} $</span>
              </span>
              <span className="mt-0.5 flex items-center justify-between gap-2">
                <span className="truncate text-xs font-semibold text-slate-500 dark:text-slate-400">{r.sub}</span>
                <span className="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">{r.unpriced} بلا سعر</span>
              </span>
              <ProgressBar done={pct} className="mt-2 h-2" />
            </span>
          </button>
        )
      })}

      {/* المكتمل: ملخص صغير */}
      {done.length > 0 && (
        <div>
          <h3 className="mb-2 flex items-center gap-1.5 text-sm font-extrabold text-slate-700 dark:text-slate-200">
            <CheckCircle2 className="h-4 w-4 text-emerald-600" /> خلصت
          </h3>
          <div className="flex flex-wrap gap-2">
            {done.map((r) => (
              <button
                key={r.key}
                type="button"
                onClick={() => onSelect(r.key)}
                className={cn(
                  'rounded-xl bg-emerald-50 px-3 py-2 text-right text-xs font-bold text-emerald-800 transition-all dark:bg-emerald-900/20 dark:text-emerald-300',
                  selected === r.key && 'ring-2 ring-emerald-500'
                )}
              >
                {r.name}
                <span className="block text-base font-black tabular-nums text-slate-900 dark:text-slate-100">{formatNumber(r.amount)} $</span>
              </button>
            ))}
          </div>
        </div>
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
  paidAt: string
  onPaidAt: (v: string) => void
  method: PaymentMethod
  onMethod: (v: PaymentMethod) => void
  onEdit: () => void
  onPay: () => void
  onCancel: () => void
  onSave: () => void
}

// في الجدول: نحذف بادئة «دين شراء:» التلقائية ليقصر السطر
const shortDescription = (d: string | null) => (d ?? '').replace(/^دين شراء(?: تلقائي)?:\s*/, '')

// أساس السعر الذي يكتبه المحاسب: الطن للمبيعات والعلف، والوحدة الواحدة لغيرها.
// factor يحوّل السعر المكتوب إلى السعر المخزَّن (للكيلو في البيع، وللكيس في العلف).
function priceBasis(i: ReviewItem): { label: string; qty: number; qtyText: string; factor: number } {
  const q = Number(i.quantity ?? 0)
  if (i.type === 'sale') {
    return { label: 'سعر الطن الواحد', qty: q / 1000, qtyText: `${formatNumber(q / 1000)} طن`, factor: 1 / 1000 }
  }
  const kg = Number(i.computed_quantity ?? 0)
  if (i.item_type === 'feed' && kg > 0 && q > 0) {
    return { label: 'سعر الطن الواحد', qty: kg / 1000, qtyText: `${formatNumber(kg / 1000)} طن`, factor: kg / 1000 / q }
  }
  if (i.category_name === 'شراء كتاكيت') {
    return { label: 'سعر الصوص الواحد', qty: q, qtyText: `${formatNumber(q)} صوص`, factor: 1 }
  }
  const unit = i.quantity_unit || 'وحدة'
  return { label: `سعر الوحدة الواحدة (${unit})`, qty: q, qtyText: `${formatNumber(q)} ${unit}`, factor: 1 }
}

function PriceCard({ item, showCategory, locked, isEditing, mode, value, error, isSaving, onValue, paidAt, onPaidAt, method, onMethod, onEdit, onPay, onCancel, onSave }: CardProps) {
  const unpriced = item.unit_price == null
  const basis = priceBasis(item)
  const unitLabel = mode === 'payment' ? (item.type === 'sale' ? 'المبلغ المقبوض الآن' : 'مبلغ الدفعة') : basis.label
  const entered = parseFloat(value)
  const remaining = Number(item.remaining_amount ?? 0)

  const shownPrice = item.unit_price != null ? item.unit_price / basis.factor : null
  const canOpen = !locked

  return (
    <tr
      onClick={canOpen ? onEdit : undefined}
      className={cn(
        'border-t border-slate-100 align-top dark:border-slate-700',
        canOpen && 'cursor-pointer hover:bg-slate-50 active:bg-slate-100 dark:hover:bg-slate-700/40',
        unpriced && 'bg-amber-50/60 dark:bg-amber-900/10'
      )}
    >
      <td className="whitespace-nowrap px-2 py-3 text-xs text-slate-400">{item.entry_date?.slice(5).split('-').reverse().join('-') ?? '—'}</td>
      <td className="px-2 py-3">
        <span className="block font-bold leading-snug text-slate-800 dark:text-slate-100">{shortDescription(item.description)}</span>
        {(showCategory && item.category_name) || item.contact_name ? (
          <span className="mt-0.5 block text-[11px] text-slate-400">
            {showCategory && item.category_name && (
              <span className={cn('ml-1 rounded-full px-1.5 py-0.5 font-bold', CATEGORY_TONE[item.category_code ?? ''] ?? 'bg-slate-100 text-slate-600')}>
                {displayCategory(item.category_name)}
              </span>
            )}
            {item.contact_name}
          </span>
        ) : null}
      </td>
      <td className="whitespace-nowrap px-2 py-3 font-bold tabular-nums text-slate-700 dark:text-slate-200">{basis.qty > 0 ? basis.qtyText : '—'}</td>
      <td className="whitespace-nowrap px-2 py-3 font-bold tabular-nums text-slate-800 dark:text-slate-100">
        {shownPrice != null ? formatNumber(shownPrice) : <span className="rounded-lg bg-primary-600 px-2 py-1 text-[11px] font-extrabold text-white">حدّد</span>}
      </td>
      <td className="whitespace-nowrap px-2 py-3 text-left">
        <span className="font-black tabular-nums text-slate-900 dark:text-slate-100">{unpriced ? '—' : formatNumber(item.total_amount)}</span>
        {!unpriced && (remaining > 0 ? (
          locked ? (
            <span className="block text-[11px] font-bold text-red-600">متبقي {formatNumber(remaining)}</span>
          ) : (
            <button
              type="button"
              onClick={(e) => { e.stopPropagation(); onPay() }}
              className="mt-0.5 block rounded-md bg-red-50 px-1.5 py-0.5 text-[11px] font-bold text-red-600 dark:bg-red-900/30"
            >
              متبقي {formatNumber(remaining)} • دفعة
            </button>
          )
        ) : (
          <span className="block text-[11px] font-bold text-emerald-600">مدفوع ✓</span>
        ))}
        {locked && <Lock className="mt-0.5 inline h-3 w-3 text-slate-400" />}
      </td>
      {isEditing && createPortal(
        // نافذة منبثقة: السعر بخانة كبيرة، والمجموع يُحسب فوراً، و«حفظ» ينقل للسجل التالي
        <div dir="rtl" className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4" onClick={(e) => { e.stopPropagation(); onCancel() }}>
        <div
          className="w-full max-w-md space-y-3 rounded-t-3xl bg-white p-5 shadow-2xl dark:bg-slate-800 sm:rounded-3xl"
          onClick={(e) => e.stopPropagation()}
        >
          <div>
            <h3 className="text-lg font-extrabold text-slate-900 dark:text-slate-100">{item.description}</h3>
            <p className="mt-0.5 text-xs text-slate-400">
              {displayCategory(item.category_name) ?? ''}{item.entry_date ? ` • ${item.entry_date}` : ''}
              {basis.qty > 0 && <> • {basis.qtyText}</>}
            </p>
          </div>
          <label className="block text-sm font-bold text-slate-600 dark:text-slate-300">{unitLabel}</label>
          <form
            onSubmit={(e) => { e.preventDefault(); onSave() }}
            className="flex gap-2"
          >
            <input
              autoFocus
              type="number"
              inputMode="decimal"
              step="any"
              min={0}
              value={value}
              onChange={(e) => onValue(e.target.value)}
              className="h-14 min-w-0 flex-1 rounded-2xl border-2 border-slate-200 px-4 text-2xl font-black tabular-nums text-slate-900 outline-none focus:border-primary-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
              placeholder="0"
            />
            <button
              type="submit"
              disabled={isSaving}
              className="h-14 rounded-2xl bg-primary-600 px-6 text-base font-extrabold text-white active:scale-95 disabled:opacity-60"
            >
              {isSaving ? '...' : 'حفظ'}
            </button>
          </form>
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
          {mode === 'price' && basis.qty > 0 && (
            <p className="rounded-xl bg-primary-50 px-3 py-2.5 text-base font-bold text-slate-700 dark:bg-primary-900/20 dark:text-slate-200">
              {basis.qtyText} × {Number.isFinite(entered) ? formatNumber(entered) : 'السعر'} ={' '}
              <span className="font-bold text-primary-700">{Number.isFinite(entered) ? formatNumber(basis.qty * entered) : '؟'} $</span>
            </p>
          )}
          {mode === 'price' && item.unit_price != null && (
            <p className="text-[11px] text-slate-400">السعر القديم سيبقى ظاهراً مشطوباً ويُسجَّل التعديل باسمك.</p>
          )}
          {error && <p className="text-xs font-semibold text-red-600">{error}</p>}
          <button type="button" onClick={onCancel} className="h-11 w-full rounded-2xl border border-slate-200 text-sm font-bold text-slate-500 dark:border-slate-600 dark:text-slate-300">
            إلغاء
          </button>
          {(item.payments?.length ?? 0) > 0 && <PaymentHistory item={item} />}
          {(item.price_history ?? []).some((h) => h.old_price != null) && (
            <div className="text-xs text-slate-500"><PriceWithHistory item={item} /></div>
          )}
        </div>
        </div>,
        document.body
      )}
    </tr>
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

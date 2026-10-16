'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { CheckCircle2, Droplets, Layers, Loader2, Lock, Pill, ShoppingCart, Wheat } from 'lucide-react'
import { accountingApi } from '@/lib/api/accounting'
import type { CategoryBreakdown, ReviewItem } from '@/lib/api/accounting'
import { cn, formatNumber } from '@/lib/utils'
import { PriceWithHistory } from './PriceWithHistory'

// ── لوحة المحاسب: قائمة أسعار بسيطة. تحديد السعر وتعديله فقط ──────────────────

type Tab = 'pending' | 'all'

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

  const [tab, setTab] = useState<Tab>('all')
  const [category, setCategory] = useState<string | undefined>(undefined)
  const [editingId, setEditingId] = useState<string | null>(null)
  const [mode, setMode] = useState<'price' | 'payment'>('price')
  const [value, setValue] = useState('')
  const [error, setError] = useState<string | null>(null)

  const flock_id = flockId || undefined

  const pending = useQuery({
    queryKey: ['accounting', 'review-queue', 'pending', category, flock_id],
    queryFn: () => accountingApi.getReviewQueue({ reason: 'missing_price', category, flock_id, per_page: 50 }),
    staleTime: 15_000,
  })

  const all = useQuery({
    queryKey: ['accounting', 'review-queue', 'all', category, flock_id],
    queryFn: () => accountingApi.getReviewQueue({ scope: 'all', category, flock_id, per_page: 50 }),
    staleTime: 15_000,
    enabled: tab === 'all',
  })

  // ملخص شامل لكل الفئات (الكميات والأسعار الإجمالية) — لا يتأثر بتبويب القائمة
  const overview = useQuery({
    queryKey: ['accounting', 'review-queue', 'overview', flock_id],
    queryFn: () => accountingApi.getReviewQueue({ scope: 'all', flock_id, per_page: 1 }),
    staleTime: 15_000,
  })

  const current = tab === 'pending' ? pending : all
  const items = current.data?.data ?? []
  const pendingCount = pending.data?.meta.total ?? 0

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
      qc.invalidateQueries({ queryKey: ['accounting', 'review-queue'] })
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

  return (
    <div className="space-y-4" dir="rtl">
      {/* ── ملخص الفئة المختارة: الكمية والسعر الإجمالي وغيرها ── */}
      <CategorySummary breakdown={overview.data?.summary.category_breakdown ?? []} category={category} loading={overview.isLoading} onSelect={setCategory} />

      {/* ── تبويبان فقط ── */}
      <div className="grid grid-cols-2 gap-1 rounded-2xl bg-slate-100 p-1">
        <TabButton active={tab === 'pending'} onClick={() => setTab('pending')}>
          بانتظار السعر
          {pendingCount > 0 && (
            <span className="ms-1.5 rounded-full bg-rose-500 px-1.5 py-0.5 text-[11px] font-bold text-white">{pendingCount}</span>
          )}
        </TabButton>
        <TabButton active={tab === 'all'} onClick={() => setTab('all')}>كل الأسعار</TabButton>
      </div>

      {/* ── تصنيف: شريط أفقي صغير ── */}
      <div className="flex gap-1.5">
        {CATEGORIES.map((c) => (
          <button
            key={c.label}
            onClick={() => setCategory(c.code)}
            className={cn(
              'flex min-w-0 flex-1 items-center justify-center gap-1 rounded-full border px-1.5 py-2 text-[13px] font-semibold transition-colors',
              category === c.code
                ? 'border-primary-600 bg-primary-600 text-white'
                : 'border-slate-200 bg-white text-slate-600'
            )}
          >
            <c.icon className="h-3.5 w-3.5 shrink-0" />
            {c.label}
          </button>
        ))}
      </div>

      {/* ── القائمة ── */}
      {current.isLoading ? (
        <div className="flex justify-center py-16"><Loader2 className="h-6 w-6 animate-spin text-slate-400" /></div>
      ) : items.length === 0 ? (
        <div className="flex flex-col items-center gap-2 py-16 text-slate-400">
          <CheckCircle2 className="h-12 w-12 text-primary-400" />
          <p className="text-sm font-semibold">{tab === 'pending' ? 'كل الأسعار محدّدة' : 'لا توجد سجلات'}</p>
        </div>
      ) : (
        <ul className="space-y-3">
          {items.map((item) => (
            <PriceCard
              key={item.id}
              item={item}
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

function TabButton({ active, onClick, children }: { active: boolean; onClick: () => void; children: React.ReactNode }) {
  return (
    <button
      onClick={onClick}
      className={cn(
        'flex items-center justify-center rounded-xl py-2.5 text-sm font-bold transition-all',
        active ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500'
      )}
    >
      {children}
    </button>
  )
}

interface CardProps {
  item: ReviewItem
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

function PriceCard({ item, locked, isEditing, mode, value, error, isSaving, onValue, onEdit, onPay, onCancel, onSave }: CardProps) {
  const unpriced = item.unit_price == null
  const unitLabel = mode === 'payment' ? 'إجمالي المدفوع حتى الآن' : item.type === 'sale' ? 'سعر الطن' : 'سعر الوحدة'
  const remaining = Number(item.remaining_amount ?? 0)

  return (
    <li className={cn('rounded-2xl border bg-white p-4 shadow-sm', unpriced ? 'border-amber-200' : 'border-slate-200')}>
      {/* العنوان + التصنيف */}
      <div className="flex items-start justify-between gap-3">
        <h3 className="text-[15px] font-bold leading-snug text-slate-800">{item.description}</h3>
        {item.category_name && (
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

// ── ملخص شامل للفئة المختارة (أو لكل الفئات) ─────────────────────────────────
function CategorySummary({ breakdown, category, loading, onSelect }: { breakdown: CategoryBreakdown[]; category: string | undefined; loading: boolean; onSelect: (code: string | undefined) => void }) {
  if (loading) return <div className="h-28 animate-pulse rounded-2xl bg-slate-100 dark:bg-slate-800" />
  if (breakdown.length === 0) return null

  // «الكل»: بطاقة لكل نوع (علف، دواء، ماء، مبيعات...) بعدد السجلات والكمية الإجمالية والسعر إن وُجد
  if (!category) {
    const grandTotal = breakdown.reduce((n, c) => n + (c.total_amount ?? 0), 0)
    const grandUnpriced = breakdown.reduce((n, c) => n + (c.unpriced_count ?? 0), 0)
    return (
      <div className="space-y-2.5">
        <div className="grid grid-cols-2 gap-2.5">
          {breakdown.map((c) => <CategoryCard key={c.code} c={c} onClick={() => onSelect(c.code)} />)}
        </div>
        <div className="flex items-center justify-between rounded-2xl bg-slate-800 px-4 py-3 text-white dark:bg-slate-700">
          <span className="text-xs font-semibold text-slate-300">
            الإجمالي{grandUnpriced > 0 ? ` · ${grandUnpriced} سجل بدون سعر` : ''}
          </span>
          <span className="text-lg font-extrabold tabular-nums">{formatNumber(grandTotal)}</span>
        </div>
      </div>
    )
  }

  const rows = category ? breakdown.filter((c) => c.code === category) : breakdown
  if (rows.length === 0) {
    return <div className="rounded-2xl border border-dashed border-slate-200 p-4 text-center text-sm text-slate-400">لا توجد سجلات في هذه الفئة</div>
  }

  const count        = rows.reduce((n, c) => n + c.count, 0)
  const totalAmount  = rows.reduce((n, c) => n + (c.total_amount ?? 0), 0)
  const unpriced     = rows.reduce((n, c) => n + (c.unpriced_count ?? 0), 0)
  const quantity     = rows.reduce((n, c) => n + (c.quantity ?? 0), 0)
  const units        = Array.from(new Set(rows.map((c) => c.quantity_unit).filter(Boolean)))
  const single       = rows.length === 1 ? rows[0] : null
  const isSales      = category === 'sales'
  // الكمية لا تُجمع عبر وحدات مختلفة (كغ + عبوة + م³) ولا معنى لمتوسط سعر مختلط
  const quantityText = single || units.length <= 1
    ? `${formatNumber(quantity)} ${units[0] ?? ''}`.trim()
    : '—'
  const feedKg       = single?.computed_quantity

  const avg = single?.avg_unit_price
  const avgText = avg == null ? '—' : formatNumber(isSales ? avg * 1000 : avg)

  return (
    <div className="grid grid-cols-2 gap-2.5">
      <Stat label="عدد السجلات" value={String(count)} sub={unpriced > 0 ? `${unpriced} بدون سعر` : 'كلها مسعّرة'} tone={unpriced > 0 ? 'amber' : 'emerald'} />
      <Stat
        label="الكمية الإجمالية"
        value={quantityText}
        sub={feedKg != null && feedKg > 0 ? (feedKg >= 1000 ? `${formatNumber(feedKg / 1000)} طن` : `${formatNumber(feedKg)} كغ`) : undefined}
      />
      <Stat label="السعر الإجمالي" value={formatNumber(totalAmount)} tone="slate" strong />
      <Stat label={isSales ? 'متوسط سعر الطن' : 'متوسط سعر الوحدة'} value={single ? avgText : '—'} sub={single ? undefined : 'اختر فئة'} />
    </div>
  )
}

function CategoryCard({ c, onClick }: { c: CategoryBreakdown; onClick: () => void }) {
  const Icon = CATEGORIES.find((x) => x.code === c.code)?.icon ?? Layers
  const tone = CATEGORY_TONE[c.code] ?? 'bg-slate-100 text-slate-600'
  const unpriced = c.unpriced_count ?? 0
  const priced = (c.priced_count ?? c.count - unpriced) > 0
  const quantity = (c.quantity ?? 0) > 0 ? `${formatNumber(c.quantity ?? 0)} ${c.quantity_unit ?? ''}`.trim() : '—'
  const kg = c.computed_quantity ?? 0
  const kgText = kg > 0 ? (kg >= 1000 ? `${formatNumber(kg / 1000)} طن` : `${formatNumber(kg)} كغ`) : null
  return (
    <button
      type="button"
      onClick={onClick}
      className="rounded-2xl border border-slate-200 bg-white p-3.5 text-right transition-colors active:bg-slate-50 dark:border-slate-700 dark:bg-slate-800"
    >
      <div className="flex items-center justify-between">
        <span className={cn('flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold', tone)}>
          <Icon className="h-3.5 w-3.5" /> {c.name}
        </span>
        <span className="text-[11px] font-semibold text-slate-400">{c.count} سجل</span>
      </div>
      <dl className="mt-3 space-y-1.5 text-[12px]">
        <div className="flex items-baseline justify-between gap-2">
          <dt className="text-slate-400">الكمية</dt>
          <dd className="truncate font-extrabold tabular-nums text-slate-800 dark:text-slate-100">
            {quantity}{kgText ? <span className="ms-1 text-[10px] font-semibold text-slate-400">({kgText})</span> : null}
          </dd>
        </div>
        <div className="flex items-baseline justify-between gap-2">
          <dt className="text-slate-400">السعر</dt>
          <dd className={cn('font-extrabold tabular-nums', priced ? 'text-slate-800 dark:text-slate-100' : 'text-amber-600')}>
            {priced ? formatNumber(c.total_amount ?? 0) : 'لم يُحدَّد'}
          </dd>
        </div>
      </dl>
      {unpriced > 0 && priced && <div className="mt-1.5 text-[11px] font-semibold text-amber-600">{unpriced} بدون سعر</div>}
    </button>
  )
}

function Stat({ label, value, sub, tone, strong }: { label: string; value: string; sub?: string; tone?: 'amber' | 'emerald' | 'slate'; strong?: boolean }) {
  const subTone = tone === 'amber' ? 'text-amber-600' : tone === 'emerald' ? 'text-emerald-600' : 'text-slate-400'
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-3.5 dark:border-slate-700 dark:bg-slate-800">
      <div className="text-[11px] font-semibold text-slate-400">{label}</div>
      <div className={cn('mt-1 truncate text-lg font-extrabold tabular-nums text-slate-800 dark:text-slate-100', strong && 'text-xl')}>{value}</div>
      {sub && <div className={cn('mt-0.5 text-[11px] font-semibold', subTone)}>{sub}</div>}
    </div>
  )
}

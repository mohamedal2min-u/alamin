'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { accountingApi, REASON_LABELS } from '@/lib/api/accounting'
import type { ReviewItem, ReviewReason, ReviewQueueFilters } from '@/lib/api/accounting'
import { Card, CardContent } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { formatNumber, formatCurrency } from '@/lib/utils'
import { PaymentOnly } from '@/components/common/PriceOnly'
import { PriceWithHistory } from './PriceWithHistory'
import { AlertTriangle, CheckCircle2, Loader2, RefreshCw, Wheat, Droplets, Pill, ShoppingCart, Package, Layers } from 'lucide-react'

// ── Badge color map — single source of truth ──────────────────────────────────
const REASON_COLORS: Record<ReviewReason, string> = {
  unpaid:                        'bg-red-100 text-red-700 border-red-200',
  partial_payment:               'bg-emerald-100 text-emerald-700 border-emerald-200',
  missing_price:                 'bg-emerald-100 text-emerald-700 border-emerald-200',
  missing_payment_status:        'bg-gray-100 text-gray-700 border-gray-200',
  inconsistent_financial_state:  'bg-purple-100 text-purple-700 border-purple-200',
  blocking_flock_closure:        'bg-red-200 text-red-800 border-red-300',
}

interface Props {
  initialFlockId?: string
  initialFilter?: string
}

export function ReviewQueueTab({ initialFlockId, initialFilter }: Props) {
  const qc = useQueryClient()

  const [filters, setFilters] = useState<ReviewQueueFilters>({
    type: 'all',
    flock_id: initialFlockId,
    filter: initialFilter,
    page: 1,
    per_page: 20,
  })

  const [editingId, setEditingId] = useState<string | null>(null)
  const [editValues, setEditValues] = useState<{ paid_amount?: string; unit_price?: string }>({})
  const [saveError, setSaveError] = useState<string | null>(null)

  const { data, isLoading, isFetching } = useQuery({
    queryKey: ['accounting', 'review-queue', filters],
    queryFn: () => accountingApi.getReviewQueue(filters),
    staleTime: 15_000,
  })

  const { mutate: updateItem, isPending: isUpdating } = useMutation({
    mutationFn: ({ type, id, payload }: {
      type: 'expense' | 'sale' | 'water_log'
      id: number
      payload: { paid_amount?: number; unit_price?: number }
    }) => accountingApi.updateReviewItem(type, id, payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['accounting', 'review-queue'] })
      setEditingId(null)
      setEditValues({})
      setSaveError(null)
    },
    onError: (error: unknown) => {
      const message =
        (error as { response?: { data?: { message?: string } } })?.response?.data?.message
        ?? 'تعذر حفظ التعديل، حاول مرة أخرى'
      setSaveError(message)
    },
  })

  const summary = data?.summary

  const handleSave = (item: ReviewItem) => {
    const payload: { paid_amount?: number; unit_price?: number } = {}
    if (editValues.paid_amount !== undefined && editValues.paid_amount !== '') {
      payload.paid_amount = parseFloat(editValues.paid_amount)
    }
    // تحديد الأسعار من صلاحية المحاسب فقط؛ مدير المزرعة يسجّل الدفعات
    updateItem({ type: item.type, id: item.record_id, payload })
  }

  return (
    <div className="space-y-5" dir="rtl">
      {/* ── Summary Cards (تعمل كفلاتر) ─────────────────────────────────── */}
      {summary && (
        <div className="grid grid-cols-3 gap-2.5">
          {([
            ['unpaid',                       'غير مدفوع',       summary.unpaid_count,                       'red'],
            ['partial_payment',              'دفع جزئي',        summary.partial_payment_count,              'emerald'],
            ['missing_price',                'ناقص السعر',      summary.missing_price_count,                'emerald'],
            ['missing_payment_status',       'ناقص حالة الدفع', summary.missing_payment_status_count,       'gray'],
            ['inconsistent_financial_state', 'تناقض مالي',      summary.inconsistent_financial_state_count, 'purple'],
            ['blocking_flock_closure',       'مانع إغلاق',      summary.blocking_flock_closure_count,       'rose'],
          ] as [ReviewReason, string, number, string][]).map(([reason, label, count, color]) => (
            <SummaryCard
              key={reason}
              label={label}
              count={count}
              color={color}
              active={filters.reason === reason}
              onClick={() => setFilters((f) => ({ ...f, reason: f.reason === reason ? undefined : reason, filter: undefined, page: 1 }))}
            />
          ))}
        </div>
      )}

      {/* ── Type + Reason Filters ─────────────────────────────────────────── */}
      <div className="flex flex-wrap gap-2">
        {(['all', 'expense', 'sale'] as const).map((t) => (
          <button
            key={t}
            onClick={() => setFilters((f) => ({ ...f, type: t, page: 1 }))}
            className={`rounded-full px-3 py-1 text-sm font-medium border transition-colors ${
              filters.type === t
                ? 'bg-primary-600 text-white border-primary-600'
                : 'bg-white text-slate-600 border-slate-200 hover:border-primary-400'
            }`}
          >
            {t === 'all' ? 'الكل' : t === 'expense' ? 'المصروفات' : 'المبيعات'}
          </button>
        ))}

        <button
          onClick={() => setFilters((f) => ({ ...f, scope: f.scope === 'all' ? undefined : 'all', page: 1 }))}
          className={`rounded-full px-3 py-1 text-sm font-medium border transition-colors ${
            filters.scope === 'all'
              ? 'bg-primary-600 text-white border-primary-600'
              : 'bg-white text-slate-600 border-slate-200 hover:border-primary-400'
          }`}
        >
          كل الأسعار
        </button>

        {(filters.reason || filters.filter || filters.category) && (
          <button
            onClick={() => setFilters((f) => ({ ...f, reason: undefined, filter: undefined, category: undefined, page: 1 }))}
            className="text-xs text-slate-400 underline self-center"
          >
            مسح الفلتر
          </button>
        )}
      </div>

      {/* ── Category Filter Cards ────────────────────────────────────────── */}
      {summary && summary.category_breakdown && summary.category_breakdown.length > 0 && (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          {summary.category_breakdown.map((cat) => (
            <CategoryCard
              key={cat.code}
              code={cat.code}
              name={cat.name}
              count={cat.count}
              remainingAmount={cat.remaining_amount}
              isActive={filters.category === cat.code}
              onClick={() =>
                setFilters((f) => ({
                  ...f,
                  category: f.category === cat.code ? undefined : cat.code,
                  page: 1,
                }))
              }
            />
          ))}
        </div>
      )}

      {/* ── List ──────────────────────────────────────────────────────────── */}
      {isLoading ? (
        <div className="flex justify-center py-12">
          <Loader2 className="h-6 w-6 animate-spin text-slate-400" />
        </div>
      ) : !data?.data.length ? (
        <div className="flex flex-col items-center justify-center py-16 text-slate-400">
          <CheckCircle2 className="mb-3 h-10 w-10 text-primary-400" />
          <p className="text-sm font-medium">{filters.scope === 'all' ? 'لا توجد سجلات' : 'لا توجد سجلات تحتاج مراجعة'}</p>
        </div>
      ) : (
        <div className="space-y-2">
          {isFetching && !isLoading && (
            <div className="text-xs text-slate-400 flex items-center gap-1 pb-1">
              <RefreshCw className="h-3 w-3 animate-spin" /> جاري التحديث...
            </div>
          )}

          {data.data.map((item) => (
            <ReviewRow
              key={item.id}
              item={item}
              isEditing={editingId === item.id}
              isUpdating={isUpdating && editingId === item.id}
              editValues={editValues}
              error={editingId === item.id ? saveError : null}
              onEdit={() => {
                setEditingId(item.id)
                setSaveError(null)
                setEditValues({ paid_amount: String(item.paid_amount ?? 0) })
              }}
              onCancel={() => { setEditingId(null); setEditValues({}); setSaveError(null) }}
              onSave={() => { setSaveError(null); handleSave(item) }}
              onEditChange={setEditValues}
            />
          ))}

          {/* Pagination */}
          {data.meta.total > data.meta.per_page && (
            <div className="flex items-center justify-center gap-3 pt-3">
              <button
                disabled={filters.page === 1}
                onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) - 1 }))}
                className="rounded px-3 py-1 text-sm border disabled:opacity-40"
              >
                السابق
              </button>
              <span className="text-xs text-slate-500">
                صفحة {data.meta.current_page} من {Math.ceil(data.meta.total / data.meta.per_page)}
              </span>
              <button
                disabled={(filters.page ?? 1) * data.meta.per_page >= data.meta.total}
                onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}
                className="rounded px-3 py-1 text-sm border disabled:opacity-40"
              >
                التالي
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  )
}

// ── Summary Card ──────────────────────────────────────────────────────────────
function SummaryCard({ label, count, color, active, onClick }: { label: string; count: number; color: string; active: boolean; onClick: () => void }) {
  if (count === 0) return null

  const colorMap: Record<string, string> = {
    red:    'bg-red-50 border-red-200 text-red-700 dark:bg-red-900/20 dark:border-red-900/40',
    emerald:'bg-emerald-50 border-emerald-200 text-emerald-700 dark:bg-emerald-900/20 dark:border-emerald-900/40',
    gray:   'bg-gray-50 border-gray-200 text-gray-700 dark:bg-slate-800 dark:border-slate-700',
    purple: 'bg-purple-50 border-purple-200 text-purple-700 dark:bg-purple-900/20 dark:border-purple-900/40',
    rose:   'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-900/20 dark:border-rose-900/40',
  }

  return (
    <button
      type="button"
      onClick={onClick}
      className={`rounded-xl border p-3 text-right transition-all active:scale-[0.97] ${colorMap[color] ?? 'bg-slate-50 border-slate-200 text-slate-700'} ${active ? 'ring-2 ring-slate-800 ring-offset-1 dark:ring-slate-200' : ''}`}
    >
      <div className="text-2xl font-black tabular-nums leading-none">{count}</div>
      <div className="mt-1 text-[11px] font-bold opacity-80">{label}</div>
    </button>
  )
}

// ── Review Row ────────────────────────────────────────────────────────────────
interface ReviewRowProps {
  item: ReviewItem
  isEditing: boolean
  isUpdating: boolean
  editValues: { paid_amount?: string; unit_price?: string }
  error?: string | null
  onEdit: () => void
  onCancel: () => void
  onSave: () => void
  onEditChange: (v: { paid_amount?: string; unit_price?: string }) => void
}

function ReviewRow({ item, isEditing, isUpdating, editValues, error, onEdit, onCancel, onSave, onEditChange }: ReviewRowProps) {
  const hasBlocking = item.review_reasons.includes('blocking_flock_closure')
  // حالتا «غير مدفوع/دفع جزئي» ظاهرتان من المبالغ نفسها — لا نكرّرهما كشارات
  const badges = item.review_reasons.filter((r) => r !== 'unpaid' && r !== 'partial_payment')
  const typeLabel = item.type === 'expense' ? 'مصروف' : item.type === 'sale' ? 'بيع' : 'استهلاك'
  const paid = Number(item.paid_amount ?? 0)
  const total = Number(item.total_amount ?? 0)
  const remaining = Number(item.remaining_amount ?? 0)
  const pct = total > 0 ? Math.min(100, Math.round((paid / total) * 100)) : 0

  return (
    <Card className={`transition-colors ${hasBlocking ? 'border-red-300 bg-red-50/30' : ''}`}>
      <CardContent className="space-y-2.5 px-4 py-3.5">
        {/* العنوان + الشارات */}
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0">
            <p className="truncate text-sm font-bold text-slate-800 dark:text-slate-100">{item.description}</p>
            <p className="mt-0.5 text-[11px] text-slate-400">{typeLabel} · {item.flock_name ?? '—'} · {item.entry_date ?? '—'}</p>
          </div>
          {badges.length > 0 && (
            <div className="flex shrink-0 flex-wrap justify-end gap-1">
              {badges.map((r) => (
                <span key={r} className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold ${REASON_COLORS[r]}`}>
                  {r === 'blocking_flock_closure' && <AlertTriangle className="me-1 h-3 w-3" />}
                  {REASON_LABELS[r]}
                </span>
              ))}
            </div>
          )}
        </div>

        {isEditing ? (
          <div className="flex flex-wrap items-end gap-2">
            <div className="flex flex-col gap-1">
              <label className="text-xs text-slate-500">المدفوع</label>
              <Input
                type="number"
                min={0}
                value={editValues.paid_amount ?? ''}
                onChange={(e) => onEditChange({ ...editValues, paid_amount: e.target.value })}
                className="h-9 w-32 text-sm"
              />
            </div>
            <div className="flex gap-2">
              <Button size="sm" onClick={onSave} loading={isUpdating} disabled={isUpdating}>حفظ</Button>
              <Button size="sm" variant="outline" onClick={onCancel}>إلغاء</Button>
            </div>
            {error && <span className="w-full text-xs text-red-600">{error}</span>}
          </div>
        ) : (
          <>
            {/* الكمية × السعر في سطر واحد */}
            {(item.quantity != null || item.unit_price != null) && (
              <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                {item.quantity != null && (
                  <span>
                    <strong className="text-slate-700 dark:text-slate-200">{formatNumber(item.quantity)} {item.quantity_unit || ''}</strong>
                    {item.item_type === 'feed' && item.computed_quantity != null && (
                      <span className="mr-1.5">({item.computed_quantity >= 1000 ? `${formatNumber(item.computed_quantity / 1000)} طن` : `${formatNumber(item.computed_quantity)} كغ`})</span>
                    )}
                  </span>
                )}
                <PriceWithHistory item={item} />
              </div>
            )}

            {/* المبالغ: الإجمالي دائماً، المدفوع/المتبقي عند الحاجة فقط */}
            <div className="flex items-end justify-between gap-3">
              <div className="flex items-baseline gap-4">
                <div>
                  <p className="text-[10px] font-bold text-slate-400">الإجمالي</p>
                  <p className="text-base font-black tabular-nums text-slate-900 dark:text-slate-100">{formatCurrency(total)}</p>
                </div>
                {paid > 0 && remaining > 0 && (
                  <div>
                    <p className="text-[10px] font-bold text-slate-400">{item.type === 'sale' ? 'المقبوض' : 'المدفوع'}</p>
                    <p className={`text-sm font-bold tabular-nums ${item.type === 'sale' ? 'text-emerald-700' : 'text-rose-700'}`}>{formatCurrency(paid)}</p>
                  </div>
                )}
                {remaining > 0 && (
                  <div>
                    <p className="text-[10px] font-bold text-slate-400">المتبقي</p>
                    <p className="text-sm font-bold tabular-nums text-orange-600">{formatCurrency(remaining)}</p>
                  </div>
                )}
              </div>
              {remaining > 0 && (
                <PaymentOnly>
                  <button
                    onClick={onEdit}
                    className="shrink-0 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-600 transition-colors hover:border-primary-400 hover:text-primary-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                  >
                    تسجيل دفعة
                  </button>
                </PaymentOnly>
              )}
            </div>

            {paid > 0 && remaining > 0 && (
              <div className="h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                <div className="h-full rounded-full bg-primary-500" style={{ width: `${pct}%` }} />
              </div>
            )}
          </>
        )}
      </CardContent>
    </Card>
  )
}

// ── Category Card ─────────────────────────────────────────────────────────────
const CATEGORY_ICONS: Record<string, React.ElementType> = {
  feed:     Wheat,
  water:    Droplets,
  medicine: Pill,
  sales:    ShoppingCart,
}

const CATEGORY_COLORS: Record<string, { bg: string; border: string; text: string; icon: string; activeBg: string }> = {
  feed:     { bg: 'bg-amber-50',   border: 'border-amber-200',   text: 'text-amber-800',   icon: 'text-amber-500',   activeBg: 'bg-amber-100' },
  medicine: { bg: 'bg-teal-50',    border: 'border-teal-200',    text: 'text-teal-800',    icon: 'text-teal-500',    activeBg: 'bg-teal-100' },
  water:    { bg: 'bg-sky-50',     border: 'border-sky-200',     text: 'text-sky-800',     icon: 'text-sky-500',     activeBg: 'bg-sky-100' },
  sales:    { bg: 'bg-emerald-50', border: 'border-emerald-200', text: 'text-emerald-800', icon: 'text-emerald-500', activeBg: 'bg-emerald-100' },
}

const DEFAULT_CAT_COLOR = { bg: 'bg-slate-50', border: 'border-slate-200', text: 'text-slate-700', icon: 'text-slate-400', activeBg: 'bg-slate-100' }

function CategoryCard({
  code,
  name,
  count,
  remainingAmount,
  isActive,
  onClick,
}: {
  code: string
  name: string
  count: number
  remainingAmount: number
  isActive: boolean
  onClick: () => void
}) {
  const Icon = CATEGORY_ICONS[code] ?? Layers
  const colors = CATEGORY_COLORS[code] ?? DEFAULT_CAT_COLOR

  return (
    <button
      onClick={onClick}
      className={`rounded-xl border-2 p-3 text-right transition-all duration-200 ${
        isActive
          ? `${colors.activeBg} ${colors.border} ring-2 ring-offset-1 ring-primary-300 scale-[1.02]`
          : `${colors.bg} ${colors.border} hover:scale-[1.01] hover:shadow-sm`
      }`}
    >
      <div className="flex items-center justify-between mb-2">
        <Icon className={`h-5 w-5 ${colors.icon}`} />
        <span className={`text-[10px] font-medium rounded-full px-2 py-0.5 ${colors.bg} ${colors.text}`}>
          {count} سجل
        </span>
      </div>
      <div className={`text-sm font-bold ${colors.text}`}>{name}</div>
      {remainingAmount > 0 && (
        <div className="text-[11px] text-orange-600 mt-1 font-medium">
          متبقي: {formatCurrency(remainingAmount)}
        </div>
      )}
    </button>
  )
}


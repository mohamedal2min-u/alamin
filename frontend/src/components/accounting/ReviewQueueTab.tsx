'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { accountingApi, REASON_LABELS } from '@/lib/api/accounting'
import type { ReviewItem, ReviewReason, ReviewQueueFilters } from '@/lib/api/accounting'
import { Card, CardContent } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { formatNumber } from '@/lib/utils'
import { AlertTriangle, CheckCircle2, Loader2, RefreshCw } from 'lucide-react'

// ── Badge color map — single source of truth ──────────────────────────────────
const REASON_COLORS: Record<ReviewReason, string> = {
  unpaid:                        'bg-red-100 text-red-700 border-red-200',
  partial_payment:               'bg-amber-100 text-amber-700 border-amber-200',
  missing_price:                 'bg-orange-100 text-orange-700 border-orange-200',
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

  const { data, isLoading, isFetching } = useQuery({
    queryKey: ['accounting', 'review-queue', filters],
    queryFn: () => accountingApi.getReviewQueue(filters),
    staleTime: 15_000,
  })

  const { mutate: updateItem, isPending: isUpdating } = useMutation({
    mutationFn: ({ type, id, payload }: {
      type: 'expense' | 'sale'
      id: number
      payload: { paid_amount?: number; unit_price?: number }
    }) => accountingApi.updateReviewItem(type, id, payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['accounting', 'review-queue'] })
      setEditingId(null)
      setEditValues({})
    },
  })

  const summary = data?.summary

  const handleSave = (item: ReviewItem) => {
    const payload: { paid_amount?: number; unit_price?: number } = {}
    if (editValues.paid_amount !== undefined && editValues.paid_amount !== '') {
      payload.paid_amount = parseFloat(editValues.paid_amount)
    }
    if (editValues.unit_price !== undefined && editValues.unit_price !== '' && item.type === 'expense') {
      payload.unit_price = parseFloat(editValues.unit_price)
    }
    updateItem({ type: item.type, id: item.record_id, payload })
  }

  return (
    <div className="space-y-5" dir="rtl">
      {/* ── Summary Cards ─────────────────────────────────────────────────── */}
      {summary && (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
          <SummaryCard label="غير مدفوع"        count={summary.unpaid_count}                       color="red" />
          <SummaryCard label="دفع جزئي"         count={summary.partial_payment_count}               color="amber" />
          <SummaryCard label="ناقص السعر"       count={summary.missing_price_count}                 color="orange" />
          <SummaryCard label="ناقص حالة الدفع"  count={summary.missing_payment_status_count}        color="gray" />
          <SummaryCard label="تناقض مالي"       count={summary.inconsistent_financial_state_count}  color="purple" />
          <SummaryCard label="مانع إغلاق"       count={summary.blocking_flock_closure_count}        color="rose" />
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

        {(['unpaid', 'partial_payment', 'blocking_flock_closure'] as ReviewReason[]).map((r) => (
          <button
            key={r}
            onClick={() =>
              setFilters((f) => ({
                ...f,
                reason: f.reason === r ? undefined : r,
                filter: undefined,
                page: 1,
              }))
            }
            className={`rounded-full px-3 py-1 text-sm font-medium border transition-colors ${
              filters.reason === r
                ? 'bg-slate-800 text-white border-slate-800'
                : 'bg-white text-slate-600 border-slate-200 hover:border-slate-400'
            }`}
          >
            {REASON_LABELS[r]}
          </button>
        ))}

        {(filters.reason || filters.filter) && (
          <button
            onClick={() => setFilters((f) => ({ ...f, reason: undefined, filter: undefined, page: 1 }))}
            className="text-xs text-slate-400 underline self-center"
          >
            مسح الفلتر
          </button>
        )}
      </div>

      {/* ── List ──────────────────────────────────────────────────────────── */}
      {isLoading ? (
        <div className="flex justify-center py-12">
          <Loader2 className="h-6 w-6 animate-spin text-slate-400" />
        </div>
      ) : !data?.data.length ? (
        <div className="flex flex-col items-center justify-center py-16 text-slate-400">
          <CheckCircle2 className="mb-3 h-10 w-10 text-primary-400" />
          <p className="text-sm font-medium">لا توجد سجلات تحتاج مراجعة</p>
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
              onEdit={() => {
                setEditingId(item.id)
                setEditValues({
                  paid_amount: String(item.paid_amount ?? 0),
                  unit_price: item.unit_price != null ? String(item.unit_price) : '',
                })
              }}
              onCancel={() => { setEditingId(null); setEditValues({}) }}
              onSave={() => handleSave(item)}
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
function SummaryCard({ label, count, color }: { label: string; count: number; color: string }) {
  if (count === 0) return null

  const colorMap: Record<string, string> = {
    red:    'bg-red-50 border-red-200 text-red-700',
    amber:  'bg-amber-50 border-amber-200 text-amber-700',
    orange: 'bg-orange-50 border-orange-200 text-orange-700',
    gray:   'bg-gray-50 border-gray-200 text-gray-700',
    purple: 'bg-purple-50 border-purple-200 text-purple-700',
    rose:   'bg-rose-50 border-rose-200 text-rose-800',
  }

  return (
    <div className={`rounded-xl border p-3 ${colorMap[color] ?? 'bg-slate-50 border-slate-200 text-slate-700'}`}>
      <div className="text-2xl font-bold">{count}</div>
      <div className="text-xs mt-0.5 opacity-80">{label}</div>
    </div>
  )
}

// ── Review Row ────────────────────────────────────────────────────────────────
interface ReviewRowProps {
  item: ReviewItem
  isEditing: boolean
  isUpdating: boolean
  editValues: { paid_amount?: string; unit_price?: string }
  onEdit: () => void
  onCancel: () => void
  onSave: () => void
  onEditChange: (v: { paid_amount?: string; unit_price?: string }) => void
}

function ReviewRow({ item, isEditing, isUpdating, editValues, onEdit, onCancel, onSave, onEditChange }: ReviewRowProps) {
  const hasBlocking = item.review_reasons.includes('blocking_flock_closure')

  return (
    <Card className={`transition-colors ${hasBlocking ? 'border-red-300 bg-red-50/30' : ''}`}>
      <CardContent className="py-3 px-4">
        <div className="flex items-start justify-between gap-3">
          <div className="flex-1 min-w-0">
            {/* Title + meta */}
            <div className="flex items-center gap-2 flex-wrap">
              <span className="font-medium text-slate-800 text-sm">{item.description}</span>
              <span className="text-xs text-slate-400">
                {item.type === 'expense' ? 'مصروف' : 'بيع'} • {item.flock_name ?? '—'} • {item.entry_date ?? '—'}
              </span>
            </div>

            {/* Reason badges */}
            <div className="mt-1.5 flex flex-wrap gap-1">
              {item.review_reasons.map((r) => (
                <span
                  key={r}
                  className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium ${REASON_COLORS[r]}`}
                >
                  {r === 'blocking_flock_closure' && (
                    <AlertTriangle className="me-1 h-3 w-3" />
                  )}
                  {REASON_LABELS[r]}
                </span>
              ))}
            </div>

            {/* Edit form or amounts display */}
            {isEditing ? (
              <div className="mt-3 flex flex-wrap items-end gap-2">
                <div className="flex flex-col gap-1">
                  <label className="text-xs text-slate-500">المدفوع</label>
                  <Input
                    type="number"
                    min={0}
                    value={editValues.paid_amount ?? ''}
                    onChange={(e) => onEditChange({ ...editValues, paid_amount: e.target.value })}
                    className="h-8 w-28 text-sm"
                  />
                </div>
                {item.type === 'expense' && (
                  <div className="flex flex-col gap-1">
                    <label className="text-xs text-slate-500">سعر الوحدة</label>
                    <Input
                      type="number"
                      min={0}
                      value={editValues.unit_price ?? ''}
                      onChange={(e) => onEditChange({ ...editValues, unit_price: e.target.value })}
                      className="h-8 w-28 text-sm"
                    />
                  </div>
                )}
                <div className="flex gap-2">
                  <Button size="sm" onClick={onSave} loading={isUpdating} disabled={isUpdating}>
                    حفظ
                  </Button>
                  <Button size="sm" variant="outline" onClick={onCancel}>إلغاء</Button>
                </div>
              </div>
            ) : (
              <div className="mt-2 flex items-center gap-4 text-xs text-slate-500">
                <span>الإجمالي: <strong className="text-slate-700">{formatNumber(item.total_amount)}</strong></span>
                <span>المدفوع: <strong className="text-slate-700">{formatNumber(item.paid_amount)}</strong></span>
                <span>المتبقي: <strong className="text-red-600">{formatNumber(item.remaining_amount)}</strong></span>
              </div>
            )}
          </div>

          {/* Edit trigger */}
          {!isEditing && (
            <button
              onClick={onEdit}
              className="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 hover:border-primary-400 hover:text-primary-600 transition-colors"
            >
              تسديد / تعديل
            </button>
          )}
        </div>
      </CardContent>
    </Card>
  )
}


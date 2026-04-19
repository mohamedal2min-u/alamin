'use client'

import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Plus, Package, CheckCircle2, AlertTriangle, AlertCircle } from 'lucide-react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { feedLogsApi } from '@/lib/api/feedLogs'
import { inventoryApi } from '@/lib/api/inventory'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { formatDate, formatNumber } from '@/lib/utils'
import type { FeedLog } from '@/types/feedLog'
import type { InventoryItem } from '@/types/dashboard'
import type { FlockStatus } from '@/types/flock'

// ── Schema ────────────────────────────────────────────────────────────────────
const schema = z.object({
  item_id:    z.number({ invalid_type_error: 'يجب اختيار صنف العلف' }).int().positive('يجب اختيار صنف العلف'),
  quantity:   z.number({ invalid_type_error: 'يجب إدخال رقم' }).min(0.001, 'الكمية يجب أن تكون أكبر من صفر'),
  entry_date: z.string().min(1, 'تاريخ الإدخال مطلوب').regex(/^\d{4}-\d{2}-\d{2}$/, 'صيغة التاريخ غير صحيحة'),
  notes:      z.string().max(5000).optional().or(z.literal('')),
})
type FormData = z.infer<typeof schema>

// ── Props ─────────────────────────────────────────────────────────────────────
interface Props {
  flockId: number
  flockStatus: FlockStatus
}

// ── Component ─────────────────────────────────────────────────────────────────
export function FeedTab({ flockId, flockStatus }: Props) {
  const queryClient = useQueryClient()
  const [showForm, setShowForm]       = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)
  const [bags, setBags]               = useState(0)
  const [extraKg, setExtraKg]         = useState(0)

  const canAdd = flockStatus === 'active'

  const { data: logs = [], isLoading, isError } = useQuery<FeedLog[]>({
    queryKey: ['feed-logs', flockId],
    queryFn: () => feedLogsApi.list(flockId).then(res => res.data),
    staleTime: 30_000,
    gcTime: 10 * 60 * 1000,
  })

  const { data: items = [] } = useQuery<InventoryItem[]>({
    queryKey: ['inventory-items', 'feed'],
    queryFn: () => inventoryApi.items('feed').then(res => res.data),
    staleTime: 5 * 60 * 1000,
    gcTime: 30 * 60 * 1000,
  })

  // ── Form ─────────────────────────────────────────────────────────────────
  const {
    register,
    handleSubmit,
    reset,
    setValue,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      item_id:    undefined as unknown as number,
      quantity:   0,
      entry_date: new Date().toISOString().split('T')[0],
    },
  })

  const selectedItemId = watch('item_id')
  const selectedItem = items.find(i => i.id === selectedItemId)
  const isUnitBased = selectedItem && selectedItem.unit_value > 1

  const handleCancel = () => {
    reset({ item_id: undefined as unknown as number, entry_date: new Date().toISOString().split('T')[0] })
    setServerError(null)
    setBags(0)
    setExtraKg(0)
    setShowForm(false)
  }

  const onSubmit = async (data: FormData) => {
    setServerError(null)
    const item = items.find((i) => i.id === data.item_id)
    try {
      await feedLogsApi.create(flockId, {
        item_id:    data.item_id,
        quantity:   data.quantity,
        entry_date: data.entry_date,
        unit_label: item?.input_unit,
        notes:      data.notes || undefined,
      })
      handleCancel()
      queryClient.invalidateQueries({ queryKey: ['feed-logs', flockId] })
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const first = axiosErr?.response?.data?.errors
        ? Object.values(axiosErr.response.data.errors)[0]?.[0]
        : null
      setServerError(first ?? axiosErr?.response?.data?.message ?? 'حدث خطأ غير متوقع')
    }
  }

  // ── Render ────────────────────────────────────────────────────────────────
  if (isLoading) return (
    <div className="flex items-center justify-center py-20 text-slate-400">
      <span className="text-sm">جارٍ التحميل...</span>
    </div>
  )

  if (isError) return (
    <div className="m-4 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">
      <AlertCircle className="h-5 w-5 shrink-0" />
      <p className="text-sm">تعذّر تحميل سجلات العلف</p>
    </div>
  )

  return (
    <div className="p-4 space-y-4">

      {/* Add button */}
      {canAdd && !showForm && (
        <div className="flex justify-end">
          <Button size="sm" onClick={() => setShowForm(true)}>
            <Plus className="me-1.5 h-4 w-4" />
            تسجيل علف
          </Button>
        </div>
      )}

      {/* Inline form */}
      {canAdd && showForm && (
        <form
          onSubmit={handleSubmit(onSubmit)}
          className="rounded-xl border border-primary-200 bg-primary-50/40 p-4 space-y-3"
          noValidate
        >
          <p className="text-sm font-semibold text-slate-700">تسجيل علف جديد</p>

          {/* No feed items warning */}
          {items.length === 0 && (
            <div className="flex items-start gap-2.5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
              <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
              <span>
                لا توجد أصناف علف مسجّلة في المخزون.{' '}
                <a href="/inventory" className="font-semibold underline hover:no-underline">
                  انتقل لصفحة المخزون
                </a>{' '}
                لإضافة أصناف.
              </span>
            </div>
          )}

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {/* Item select */}
            <div className="space-y-1">
              <label htmlFor="feed_item_id" className="text-sm font-medium text-slate-700">
                صنف العلف <span className="text-red-500">*</span>
              </label>
              <select
                {...register('item_id', {
                  valueAsNumber: true,
                  onChange: () => { setBags(0); setExtraKg(0) }
                })}
                id="feed_item_id"
                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
              >
                <option value="">اختر صنف العلف</option>
                {items.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.name} ({item.input_unit})
                  </option>
                ))}
              </select>
              {errors.item_id && (
                <p className="text-xs text-red-500">{errors.item_id.message}</p>
              )}
            </div>

            {/* Quantity Inputs */}
            {isUnitBased ? (
              <div className="grid grid-cols-2 gap-2">
                <div className="space-y-1">
                  <label className="text-sm font-medium text-slate-700">
                    عدد الأكياس <span className="text-xs text-slate-400">({selectedItem?.input_unit})</span>
                  </label>
                  <Input
                    type="number"
                    value={bags || ''}
                    onChange={(e) => {
                      const val = parseInt(e.target.value) || 0
                      setBags(val)
                      const total = val + (extraKg / (selectedItem?.unit_value || 1))
                      setValue('quantity', total, { shouldValidate: true })
                    }}
                    placeholder="0"
                    className="h-10"
                  />
                </div>
                <div className="space-y-1">
                  <label className="text-sm font-medium text-slate-700">
                    وزن إضافي <span className="text-xs text-slate-400">(كيلو)</span>
                  </label>
                  <Input
                    type="number"
                    step="0.01"
                    value={extraKg || ''}
                    onChange={(e) => {
                      const val = parseFloat(e.target.value) || 0
                      setExtraKg(val)
                      const total = bags + (val / (selectedItem?.unit_value || 1))
                      setValue('quantity', total, { shouldValidate: true })
                    }}
                    placeholder="0.00"
                    className="h-10"
                  />
                </div>
              </div>
            ) : (
              <Input
                {...register('quantity', { valueAsNumber: true })}
                id="feed_quantity"
                label={`الكمية (${selectedItem?.input_unit || 'بالكيلو'})`}
                type="number"
                step="0.01"
                min={0.001}
                placeholder="مثال: 50"
                error={errors.quantity?.message}
                required
              />
            )}
          </div>

          {/* Total Preview (Only if bags used) */}
          {isUnitBased && (bags > 0 || extraKg > 0) && (
            <div className="flex items-center gap-2 rounded-lg bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-700 border border-primary-100">
              <CheckCircle2 className="h-3.5 w-3.5" />
              <span>
                الإجمالي المستهلك: {formatNumber(bags * (selectedItem?.unit_value || 1) + extraKg)} كجم
              </span>
            </div>
          )}

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {/* Entry date */}
            <Input
              {...register('entry_date')}
              id="feed_entry_date"
              label="تاريخ الإدخال"
              type="date"
              max={new Date().toISOString().split('T')[0]}
              error={errors.entry_date?.message}
              required
            />

            {/* Notes */}
            <div className="space-y-1">
              <label htmlFor="feed_notes" className="text-sm font-medium text-slate-700">
                ملاحظات <span className="text-xs text-slate-400">(اختياري)</span>
              </label>
              <input
                {...register('notes')}
                id="feed_notes"
                type="text"
                placeholder="مثال: تغذية صباحية"
                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
              />
            </div>
          </div>

          {serverError && (
            <p className="text-sm text-red-600">{serverError}</p>
          )}

          <div className="flex gap-2 justify-end">
            <Button type="button" variant="outline" size="sm" onClick={handleCancel}>
              إلغاء
            </Button>
            <Button type="submit" size="sm" loading={isSubmitting} disabled={items.length === 0}>
              تسجيل
            </Button>
          </div>
        </form>
      )}

      {/* Empty state */}
      {logs.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-16 text-center text-slate-400">
          <Package className="mb-3 h-10 w-10 opacity-30" />
          <p className="text-base font-medium text-slate-600">لا توجد سجلات علف</p>
          {canAdd && !showForm && (
            <p className="mt-1 text-sm">اضغط «تسجيل علف» لإضافة أول سجل</p>
          )}
        </div>
      ) : (
        <div className="space-y-2">
          {logs.map((log) => (
            <FeedLogRow key={log.id} log={log} />
          ))}
        </div>
      )}

    </div>
  )
}

// ── FeedLogRow ────────────────────────────────────────────────────────────────
function FeedLogRow({ log }: { log: FeedLog }) {
  const displayUnit = log.unit_label ?? log.item_input_unit ?? ''

  return (
    <div className="flex items-start justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3" style={{ boxShadow: 'var(--shadow-card)' }}>
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-x-3 gap-y-0.5">
          <span className="text-sm font-semibold text-slate-900">
            {log.item_name ?? '—'}
          </span>
          <span className="text-sm tabular-nums text-slate-700">
            {formatNumber(log.quantity)} {displayUnit}
          </span>
          <span className="text-xs text-slate-400">{formatDate(log.entry_date)}</span>
        </div>
        {log.notes && (
          <p className="mt-0.5 text-xs text-slate-500 truncate">{log.notes}</p>
        )}
      </div>
      <div className="shrink-0 pt-0.5">
        {log.inventory_linked ? (
          <span title="تم خصم المخزون">
            <CheckCircle2 className="h-4 w-4 text-primary-500" />
          </span>
        ) : (
          <span className="text-xs text-slate-300">—</span>
        )}
      </div>
    </div>
  )
}


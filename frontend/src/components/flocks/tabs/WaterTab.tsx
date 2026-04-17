'use client'

import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Plus, Droplets, AlertCircle } from 'lucide-react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { waterLogsApi } from '@/lib/api/waterLogs'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { formatDate, formatNumber } from '@/lib/utils'
import type { WaterLog } from '@/types/waterLog'
import type { FlockStatus } from '@/types/flock'

// ── Schema ────────────────────────────────────────────────────────────────────
const schema = z.object({
  quantity:   z.preprocess(
    (v) => (v === '' || v === undefined ? undefined : Number(v)),
    z.number({ invalid_type_error: 'يجب إدخال رقم' }).min(0.001, 'الكمية يجب أن تكون أكبر من صفر').optional()
  ),
  unit_label: z.string().max(50).optional().or(z.literal('')),
  total_amount: z.preprocess(
    (v) => (v === '' || v === undefined ? undefined : Number(v)),
    z.number().min(0, 'عذراً لا يمكن ان يكون سالباً').optional()
  ),
  paid_amount: z.preprocess(
    (v) => (v === '' || v === undefined ? undefined : Number(v)),
    z.number().min(0, 'عذراً لا يمكن ان يكون سالباً').optional()
  ),
  payment_status: z.enum(['paid', 'partial', 'unpaid']).default('paid'),
  entry_date: z.string().min(1, 'تاريخ الإدخال مطلوب').regex(/^\d{4}-\d{2}-\d{2}$/, 'صيغة التاريخ غير صحيحة'),
  notes:      z.string().max(5000).optional().or(z.literal('')),
}).refine(
  (data) => {
    if (data.total_amount && data.paid_amount) {
      return data.paid_amount <= data.total_amount
    }
    return true
  },
  { message: 'المبلغ المدفوع لا يمكن أن يتجاوز الإجمالي', path: ['paid_amount'] }
)
type FormData = z.infer<typeof schema>


// ── Props ─────────────────────────────────────────────────────────────────────
interface Props {
  flockId: number
  flockStatus: FlockStatus
}

// ── Component ─────────────────────────────────────────────────────────────────
export function WaterTab({ flockId, flockStatus }: Props) {
  const queryClient = useQueryClient()
  const [showForm, setShowForm]       = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)

  const canAdd = flockStatus === 'active'

  const { data: logs = [], isLoading, isError } = useQuery<WaterLog[]>({
    queryKey: ['water-logs', flockId],
    queryFn: () => waterLogsApi.list(flockId).then(res => res.data),
    staleTime: 30_000,
    gcTime: 10 * 60 * 1000,
  })

  // ── Form ─────────────────────────────────────────────────────────────────
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      entry_date: new Date().toISOString().split('T')[0],
      unit_label: 'صهريج',
      payment_status: 'paid',
    },
  })

  const handleCancel = () => {
    reset({ entry_date: new Date().toISOString().split('T')[0], unit_label: 'صهريج', payment_status: 'paid' })
    setServerError(null)
    setShowForm(false)
  }

  const onSubmit = async (data: FormData) => {
    setServerError(null)
    try {
      await waterLogsApi.create(flockId, {
        quantity:       data.quantity,
        entry_date:     data.entry_date,
        unit_label:     data.unit_label || undefined,
        total_amount:   data.total_amount,
        paid_amount:    data.paid_amount,
        payment_status: data.payment_status,
        notes:          data.notes || undefined,
      })
      handleCancel()
      queryClient.invalidateQueries({ queryKey: ['water-logs', flockId] })
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
      <p className="text-sm">تعذّر تحميل سجلات المياه</p>
    </div>
  )

  return (
    <div className="p-4 space-y-4">

      {/* Add button */}
      {canAdd && !showForm && (
        <div className="flex justify-end">
          <Button size="sm" onClick={() => setShowForm(true)}>
            <Plus className="me-1.5 h-4 w-4" />
            تسجيل مياه
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
          <p className="text-sm font-semibold text-slate-700">تسجيل استهلاك مياه</p>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {/* Quantity */}
            <Input
              {...register('quantity')}
              id="water_quantity"
              label="عدد الصهاريج"
              type="number"
              step="0.5"
              min={0.5}
              placeholder="مثال: 1"
              error={errors.quantity?.message}
            />

            {/* Unit label */}
            <Input
              {...register('unit_label')}
              id="water_unit_label"
              label="الوحدة"
              type="text"
              readOnly
              className="bg-slate-50 cursor-not-allowed"
              placeholder="مثال: صهريج"
              error={errors.unit_label?.message}
            />
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <Input
              {...register('total_amount')}
              id="water_total"
              label="السعر الإجمالي ($)"
              type="number"
              step="0.01"
              min={0}
              placeholder="0.00"
              error={errors.total_amount?.message}
            />
            <Input
              {...register('paid_amount')}
              id="water_paid"
              label="المبلغ المدفوع ($)"
              type="number"
              step="0.01"
              min={0}
              placeholder="0.00"
              error={errors.paid_amount?.message}
            />
            <div className="space-y-1">
              <label className="text-sm font-medium text-slate-700">حالة الدفع</label>
              <select
                {...register('payment_status')}
                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
              >
                <option value="paid">مدفوع بالكامل</option>
                <option value="partial">مدفوع جزئياً</option>
                <option value="unpaid">غير مدفوع (دين)</option>
              </select>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {/* Entry date */}
            <Input
              {...register('entry_date')}
              id="water_entry_date"
              label="تاريخ الإدخال"
              type="date"
              max={new Date().toISOString().split('T')[0]}
              error={errors.entry_date?.message}
              required
            />

            {/* Notes */}
            <div className="space-y-1">
              <label htmlFor="water_notes" className="text-sm font-medium text-slate-700">
                ملاحظات <span className="text-xs text-slate-400">(اختياري)</span>
              </label>
              <input
                {...register('notes')}
                id="water_notes"
                type="text"
                placeholder="مثال: استهلاك طبيعي"
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
            <Button type="submit" size="sm" loading={isSubmitting}>
              تسجيل
            </Button>
          </div>
        </form>
      )}

      {/* Empty state */}
      {logs.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-16 text-center text-slate-400">
          <Droplets className="mb-3 h-10 w-10 opacity-30" />
          <p className="text-base font-medium text-slate-600">لا توجد سجلات مياه</p>
          {canAdd && !showForm && (
            <p className="mt-1 text-sm">اضغط «تسجيل مياه» لإضافة أول سجل</p>
          )}
        </div>
      ) : (
        <div className="space-y-2">
          {logs.map((log) => (
            <WaterLogRow key={log.id} log={log} />
          ))}
        </div>
      )}

    </div>
  )
}

// ── WaterLogRow ───────────────────────────────────────────────────────────────
function WaterLogRow({ log }: { log: WaterLog }) {
  const displayUnit = log.unit_label ?? 'صهريج'

  const PAYMENT_STATUS_LABEL: Record<string, { label: string; color: string }> = {
    paid:    { label: 'مدفوع',     color: 'bg-green-100 text-green-700' },
    partial: { label: 'جزئي',      color: 'bg-amber-100 text-amber-700' },
    unpaid:  { label: 'دين',       color: 'bg-red-100 text-red-700' },
  }

  const statusObj = log.payment_status ? PAYMENT_STATUS_LABEL[log.payment_status] : null

  return (
    <div className="flex items-start justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3" style={{ boxShadow: 'var(--shadow-card)' }}>
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-x-3 gap-y-0.5 mb-1.5">
          <span className="text-sm font-semibold text-slate-900">
            {log.quantity !== null
              ? `${formatNumber(log.quantity)} ${displayUnit}`
              : 'بدون كمية'}
          </span>
          <span className="text-xs text-slate-400">{formatDate(log.entry_date)}</span>
        </div>
        
        {log.total_amount !== null && (
          <div className="flex items-center gap-2 mb-1.5">
            <span className="text-sm font-bold text-slate-700">{formatNumber(log.total_amount)} USD</span>
            {statusObj && (
              <span className={`px-2 py-0.5 rounded-full text-[10px] font-semibold ${statusObj.color}`}>
                {statusObj.label}
              </span>
            )}
          </div>
        )}

        {log.notes && (
          <p className="mt-0.5 text-xs text-slate-500 truncate">{log.notes}</p>
        )}
      </div>
      <Droplets className="h-4 w-4 shrink-0 text-sky-400 mt-0.5" />
    </div>
  )
}


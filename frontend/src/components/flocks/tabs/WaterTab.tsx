'use client'

import { useCallback, useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Plus, Droplets } from 'lucide-react'
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
export function WaterTab({ flockId, flockStatus }: Props) {
  const [logs, setLogs]               = useState<WaterLog[]>([])
  const [loading, setLoading]         = useState(true)
  const [fetchError, setFetchError]   = useState<string | null>(null)
  const [showForm, setShowForm]       = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)

  const canAdd = flockStatus === 'active'

  // ── Fetch logs ───────────────────────────────────────────────────────────
  const fetchLogs = useCallback(() => {
    setLoading(true)
    waterLogsApi
      .list(flockId)
      .then((res) => setLogs(res.data))
      .catch(() => setFetchError('تعذّر تحميل سجلات المياه'))
      .finally(() => setLoading(false))
  }, [flockId])

  useEffect(() => {
    fetchLogs()
  }, [fetchLogs])

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
      unit_label: 'لتر',
    },
  })

  const handleCancel = () => {
    reset({ entry_date: new Date().toISOString().split('T')[0], unit_label: 'لتر' })
    setServerError(null)
    setShowForm(false)
  }

  const onSubmit = async (data: FormData) => {
    setServerError(null)
    try {
      const res = await waterLogsApi.create(flockId, {
        quantity:   data.quantity,
        entry_date: data.entry_date,
        unit_label: data.unit_label || undefined,
        notes:      data.notes || undefined,
      })
      setLogs((prev) => [res.data, ...prev])
      handleCancel()
    } catch (err: unknown) {
      const axiosErr = err as {
        response?: { data?: { message?: string; errors?: Record<string, string[]> } }
      }
      const first = axiosErr?.response?.data?.errors
        ? Object.values(axiosErr.response.data.errors)[0]?.[0]
        : null
      setServerError(first ?? axiosErr?.response?.data?.message ?? 'حدث خطأ غير متوقع')
    }
  }

  // ── Render ────────────────────────────────────────────────────────────────
  if (loading) {
    return (
      <div className="flex items-center justify-center py-20 text-slate-400">
        <span className="text-sm">جارٍ التحميل...</span>
      </div>
    )
  }

  if (fetchError) {
    return (
      <div className="m-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
        {fetchError}
      </div>
    )
  }

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
              label="الكمية"
              type="number"
              step="0.01"
              min={0.001}
              placeholder="مثال: 250"
              error={errors.quantity?.message}
            />

            {/* Unit label */}
            <Input
              {...register('unit_label')}
              id="water_unit_label"
              label="الوحدة"
              type="text"
              placeholder="مثال: لتر"
              error={errors.unit_label?.message}
            />
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
  const displayUnit = log.unit_label ?? 'لتر'

  return (
    <div className="flex items-start justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3" style={{ boxShadow: 'var(--shadow-card)' }}>
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-x-3 gap-y-0.5">
          <span className="text-sm font-semibold text-slate-900">
            {log.quantity !== null
              ? `${formatNumber(log.quantity)} ${displayUnit}`
              : 'بدون كمية'}
          </span>
          <span className="text-xs text-slate-400">{formatDate(log.entry_date)}</span>
        </div>
        {log.notes && (
          <p className="mt-0.5 text-xs text-slate-500 truncate">{log.notes}</p>
        )}
      </div>
      <Droplets className="h-4 w-4 shrink-0 text-sky-400 mt-0.5" />
    </div>
  )
}

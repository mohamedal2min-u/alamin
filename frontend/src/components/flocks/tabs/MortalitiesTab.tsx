// frontend/src/components/flocks/tabs/MortalitiesTab.tsx
'use client'

import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { AlertCircle, Plus } from 'lucide-react'
import { mortalitiesApi } from '@/lib/api/mortalities'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { formatDate } from '@/lib/utils'
import type { FlockStatus } from '@/types/flock'
import type { Mortality } from '@/types/mortality'

// ── Schema ────────────────────────────────────────────────────────────────────
const schema = z.object({
  entry_date: z
    .string()
    .min(1, 'تاريخ الإدخال مطلوب')
    .regex(/^\d{4}-\d{2}-\d{2}$/, 'صيغة التاريخ غير صحيحة'),
  quantity: z
    .number({ invalid_type_error: 'يجب إدخال رقم صحيح' })
    .int()
    .positive('الكمية يجب أن تكون أكبر من صفر'),
  reason: z.string().max(190).optional().or(z.literal('')),
  notes: z.string().optional().or(z.literal('')),
})
type FormData = z.infer<typeof schema>

// ── Props ─────────────────────────────────────────────────────────────────────
interface MortalitiesTabProps {
  flockId: number
  flockStatus: FlockStatus
}

// ── Component ─────────────────────────────────────────────────────────────────
export function MortalitiesTab({ flockId, flockStatus }: MortalitiesTabProps) {
  const [mortalities, setMortalities] = useState<Mortality[]>([])
  const [loading, setLoading]         = useState(true)
  const [fetchError, setFetchError]   = useState<string | null>(null)
  const [serverError, setServerError] = useState<string | null>(null)
  const [showForm, setShowForm]       = useState(false)

  const canAdd = flockStatus === 'active'

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: { entry_date: new Date().toISOString().split('T')[0] },
  })

  // ── Fetch ─────────────────────────────────────────────────────────────────
  const fetchMortalities = () => {
    setLoading(true)
    mortalitiesApi
      .list(flockId)
      .then((res) => setMortalities(res.data))
      .catch(() => setFetchError('تعذّر تحميل سجلات النفوق'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    fetchMortalities()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [flockId])

  // ── Submit ────────────────────────────────────────────────────────────────
  const onSubmit = async (data: FormData) => {
    setServerError(null)
    try {
      await mortalitiesApi.create(flockId, {
        entry_date: data.entry_date,
        quantity:   data.quantity,
        reason:     data.reason || undefined,
        notes:      data.notes  || undefined,
      })
      reset({ entry_date: new Date().toISOString().split('T')[0] })
      setShowForm(false)
      fetchMortalities()
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
      <div className="m-4 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">
        <AlertCircle className="h-5 w-5 shrink-0" />
        <p className="text-sm">{fetchError}</p>
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
            تسجيل نفوق
          </Button>
        </div>
      )}

      {/* Inline form */}
      {canAdd && showForm && (
        <form
          onSubmit={handleSubmit(onSubmit)}
          className="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3"
          noValidate
        >
          <h3 className="text-sm font-semibold text-slate-700">تسجيل نفوق جديد</h3>

          <div className="grid grid-cols-2 gap-3">
            <Input
              {...register('entry_date')}
              id="entry_date"
              label="التاريخ"
              type="date"
              error={errors.entry_date?.message}
              required
            />
            <Input
              {...register('quantity', { valueAsNumber: true })}
              id="quantity"
              label="العدد"
              type="number"
              min={1}
              placeholder="مثال: 5"
              error={errors.quantity?.message}
              required
            />
          </div>

          <Input
            {...register('reason')}
            id="reason"
            label="السبب"
            placeholder="مثال: مرض تنفسي"
            error={errors.reason?.message}
          />

          <div className="flex flex-col gap-1">
            <label htmlFor="notes" className="text-sm font-medium text-slate-700">
              ملاحظات <span className="text-xs text-slate-400">(اختياري)</span>
            </label>
            <textarea
              {...register('notes')}
              id="notes"
              rows={2}
              className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
            />
          </div>

          {serverError && (
            <p className="text-sm text-red-600">{serverError}</p>
          )}

          <div className="flex gap-2 justify-end">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => { setShowForm(false); setServerError(null) }}
            >
              إلغاء
            </Button>
            <Button type="submit" size="sm" loading={isSubmitting}>
              حفظ
            </Button>
          </div>
        </form>
      )}

      {/* List */}
      {mortalities.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-16 text-center text-slate-400">
          <p className="text-base font-medium text-slate-600">لا توجد سجلات نفوق</p>
          {canAdd && (
            <p className="mt-1 text-sm">اضغط «تسجيل نفوق» لإضافة أول سجل</p>
          )}
        </div>
      ) : (
        <div className="overflow-x-auto rounded-xl border border-slate-200">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-4 py-3 text-start font-medium text-slate-600">التاريخ</th>
                <th className="px-4 py-3 text-start font-medium text-slate-600">العدد</th>
                <th className="px-4 py-3 text-start font-medium text-slate-600">السبب</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 bg-white">
              {mortalities.map((m) => (
                <tr key={m.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3 text-slate-700">{formatDate(m.entry_date)}</td>
                  <td className="px-4 py-3 font-medium text-red-700">{m.quantity}</td>
                  <td className="px-4 py-3 text-slate-500">{m.reason ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

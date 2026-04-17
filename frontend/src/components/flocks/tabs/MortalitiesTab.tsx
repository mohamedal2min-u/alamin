// frontend/src/components/flocks/tabs/MortalitiesTab.tsx
'use client'

import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { AlertCircle, Plus } from 'lucide-react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { mortalitiesApi } from '@/lib/api/mortalities'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { formatDate } from '@/lib/utils'
import { useState } from 'react'
import type { FlockStatus } from '@/types/flock'
import type { Mortality } from '@/types/mortality'

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

interface MortalitiesTabProps {
  flockId: number
  flockStatus: FlockStatus
}

export function MortalitiesTab({ flockId, flockStatus }: MortalitiesTabProps) {
  const queryClient = useQueryClient()
  const [showForm, setShowForm]       = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)
  const canAdd = flockStatus === 'active'

  const { data: mortalities = [], isLoading, isError } = useQuery<Mortality[]>({
    queryKey: ['mortalities', flockId],
    queryFn: () => mortalitiesApi.list(flockId).then(res => res.data),
    staleTime: 30_000,
    gcTime: 10 * 60 * 1000,
  })

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: { entry_date: new Date().toISOString().split('T')[0] },
  })

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
      queryClient.invalidateQueries({ queryKey: ['mortalities', flockId] })
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const first = axiosErr?.response?.data?.errors
        ? Object.values(axiosErr.response.data.errors)[0]?.[0]
        : null
      setServerError(first ?? axiosErr?.response?.data?.message ?? 'حدث خطأ غير متوقع')
    }
  }

  if (isLoading) return (
    <div className="flex items-center justify-center py-20 text-slate-400">
      <span className="text-sm">جارٍ التحميل...</span>
    </div>
  )

  if (isError) return (
    <div className="m-4 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">
      <AlertCircle className="h-5 w-5 shrink-0" />
      <p className="text-sm">تعذّر تحميل سجلات النفوق</p>
    </div>
  )

  return (
    <div className="p-4 space-y-4">
      {canAdd && !showForm && (
        <div className="flex justify-end">
          <Button size="sm" onClick={() => setShowForm(true)}>
            <Plus className="me-1.5 h-4 w-4" />
            تسجيل نفوق
          </Button>
        </div>
      )}

      {canAdd && showForm && (
        <form onSubmit={handleSubmit(onSubmit)} className="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3" noValidate>
          <h3 className="text-sm font-semibold text-slate-700">تسجيل نفوق جديد</h3>
          <div className="grid grid-cols-2 gap-3">
            <Input {...register('entry_date')} id="entry_date" label="التاريخ" type="date" error={errors.entry_date?.message} required />
            <Input {...register('quantity', { valueAsNumber: true })} id="quantity" label="العدد" type="number" min={1} placeholder="مثال: 5" error={errors.quantity?.message} required />
          </div>
          <Input {...register('reason')} id="reason" label="السبب" placeholder="مثال: مرض تنفسي" error={errors.reason?.message} />
          <div className="flex flex-col gap-1">
            <label htmlFor="notes" className="text-sm font-medium text-slate-700">ملاحظات <span className="text-xs text-slate-400">(اختياري)</span></label>
            <textarea {...register('notes')} id="notes" rows={2} className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" />
            {errors.notes && <p className="text-xs text-red-500">{errors.notes.message}</p>}
          </div>
          {serverError && <p className="text-sm text-red-600">{serverError}</p>}
          <div className="flex gap-2 justify-end">
            <Button type="button" variant="outline" size="sm" onClick={() => { setShowForm(false); setServerError(null) }}>إلغاء</Button>
            <Button type="submit" size="sm" loading={isSubmitting}>حفظ</Button>
          </div>
        </form>
      )}

      {mortalities.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-16 text-center text-slate-400">
          <p className="text-base font-medium text-slate-600">لا توجد سجلات نفوق</p>
          {canAdd && <p className="mt-1 text-sm">اضغط «تسجيل نفوق» لإضافة أول سجل</p>}
        </div>
      ) : (
        <div className="overflow-x-auto rounded-xl border border-slate-200">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50">
              <tr>
                <th scope="col" className="px-4 py-3 text-start font-medium text-slate-600">التاريخ</th>
                <th scope="col" className="px-4 py-3 text-start font-medium text-slate-600">العدد</th>
                <th scope="col" className="px-4 py-3 text-start font-medium text-slate-600">السبب</th>
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

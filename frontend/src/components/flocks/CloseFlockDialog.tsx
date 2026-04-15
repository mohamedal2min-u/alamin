'use client'

import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { AlertTriangle } from 'lucide-react'
import { Dialog } from '@/components/ui/Dialog'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { flocksApi } from '@/lib/api/flocks'
import { formatNumber } from '@/lib/utils'
import type { Flock } from '@/types/flock'

const schema = z.object({
  close_date: z
    .string()
    .min(1, 'تاريخ الإغلاق مطلوب')
    .regex(/^\d{4}-\d{2}-\d{2}$/, 'صيغة التاريخ غير صحيحة'),
  notes: z.string().max(5000).optional().or(z.literal('')),
})
type FormData = z.infer<typeof schema>

interface Props {
  flock: Flock
  isOpen: boolean
  onClose: () => void
  onSuccess: (updated: Flock) => void
}

export function CloseFlockDialog({ flock, isOpen, onClose, onSuccess }: Props) {
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      close_date: new Date().toISOString().split('T')[0],
    },
  })

  const handleClose = () => {
    reset({ close_date: new Date().toISOString().split('T')[0] })
    setServerError(null)
    onClose()
  }

  const onSubmit = async (data: FormData) => {
    setServerError(null)
    try {
      const res = await flocksApi.update(flock.id, {
        status: 'closed',
        close_date: data.close_date,
        notes: data.notes || undefined,
      })
      onSuccess(res.data)
      handleClose()
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

  return (
    <Dialog isOpen={isOpen} onClose={handleClose} title="إغلاق الفوج">
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 pt-4" noValidate>

        {/* Warning banner */}
        <div className="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
          <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
          <div className="text-sm text-amber-800">
            <p className="font-semibold">هذا الإجراء لا يمكن التراجع عنه</p>
            <p className="mt-0.5 text-amber-700">
              سيتم إغلاق الفوج وتوزيع الأرباح/الخسائر على الشركاء تلقائياً.
            </p>
          </div>
        </div>

        {/* Flock summary */}
        <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm space-y-2">
          <p className="font-semibold text-slate-700 mb-3">{flock.name}</p>
          <div className="flex justify-between text-slate-600">
            <span>العدد الأولي</span>
            <span className="tabular-nums font-medium text-slate-900">{formatNumber(flock.initial_count)}</span>
          </div>
          <div className="flex justify-between text-slate-600">
            <span>إجمالي المبيعات</span>
            <span className="tabular-nums font-medium text-emerald-700">{formatNumber(Number(flock.total_sales))}</span>
          </div>
          <div className="flex justify-between text-slate-600">
            <span>إجمالي المصاريف</span>
            <span className="tabular-nums font-medium text-slate-900">{formatNumber(Number(flock.total_expenses))}</span>
          </div>
          <div className={`flex justify-between border-t border-slate-200 pt-2 font-semibold ${Number(flock.net_profit) >= 0 ? 'text-emerald-700' : 'text-red-600'}`}>
            <span>صافي الربح/الخسارة</span>
            <span className="tabular-nums">{formatNumber(Number(flock.net_profit))}</span>
          </div>
        </div>

        {/* Close date */}
        <Input
          {...register('close_date')}
          id="close_date"
          label="تاريخ الإغلاق"
          type="date"
          max={new Date().toISOString().split('T')[0]}
          error={errors.close_date?.message}
          required
        />

        {/* Notes */}
        <div className="space-y-1">
          <label htmlFor="close_notes" className="text-sm font-medium text-slate-700">
            ملاحظات <span className="text-xs text-slate-400">(اختياري)</span>
          </label>
          <textarea
            {...register('notes')}
            id="close_notes"
            rows={2}
            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
          />
        </div>

        {serverError && (
          <p className="text-sm text-red-600">{serverError}</p>
        )}

        <div className="flex gap-2 justify-end pt-1">
          <Button type="button" variant="outline" size="sm" onClick={handleClose}>
            إلغاء
          </Button>
          <Button
            type="submit"
            size="sm"
            loading={isSubmitting}
            className="bg-red-600 hover:bg-red-700 focus:ring-red-500/30"
          >
            تأكيد الإغلاق
          </Button>
        </div>
      </form>
    </Dialog>
  )
}

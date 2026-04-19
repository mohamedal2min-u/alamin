'use client'

import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Dialog } from '@/components/ui/Dialog'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { salesApi } from '@/lib/api/sales'
import { formatNumber } from '@/lib/utils'
import type { Sale } from '@/types/sale'

const schema = z.object({
  received_amount: z
    .number({ invalid_type_error: 'يجب إدخال رقم' })
    .min(0, 'لا يمكن أن يكون سالبًا'),
  notes: z.string().max(5000).optional().or(z.literal('')),
})
type FormData = z.infer<typeof schema>

interface Props {
  sale: Sale
  isOpen: boolean
  onClose: () => void
  onSuccess: (updated: Sale) => void
}

export function UpdatePaymentDialog({ sale, isOpen, onClose, onSuccess }: Props) {
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    watch,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: { received_amount: sale.received_amount },
  })

  const receivedAmount = Number(watch('received_amount') || 0)
  const remaining = Math.max(sale.net_amount - receivedAmount, 0)
  const paymentStatus =
    remaining <= 0 ? 'مدفوع' : receivedAmount > 0 ? 'جزئي' : 'غير مدفوع'

  const handleClose = () => {
    reset({ received_amount: sale.received_amount })
    setServerError(null)
    onClose()
  }

  const onSubmit = async (data: FormData) => {
    setServerError(null)
    try {
      const res = await salesApi.updatePayment(sale.id, {
        received_amount: data.received_amount,
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
    <Dialog isOpen={isOpen} onClose={handleClose} title="تحديث حالة الدفع">
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 pt-4" noValidate>
        {/* Summary */}
        <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm space-y-2">
          <div className="flex justify-between text-slate-600">
            <span>الصافي</span>
            <span className="tabular-nums font-semibold text-slate-900">
              {formatNumber(sale.net_amount)}
            </span>
          </div>
          <div className="flex justify-between text-slate-600">
            <span>المستلم الحالي</span>
            <span className="tabular-nums">{formatNumber(sale.received_amount)}</span>
          </div>
        </div>

        <Input
          {...register('received_amount', { valueAsNumber: true })}
          id="received_amount"
          label="المبلغ المستلم الجديد"
          type="number"
          min={0}
          step="0.01"
          placeholder="0.00"
          error={errors.received_amount?.message}
          required
        />

        {/* Live preview */}
        <div className="rounded-xl border border-slate-200 bg-white p-4 text-sm space-y-1.5">
          <div className="flex justify-between text-slate-600">
            <span>المتبقي بعد التحديث</span>
            <span className={`tabular-nums font-semibold ${remaining > 0 ? 'text-red-600' : 'text-primary-700'}`}>
              {formatNumber(Number(remaining.toFixed(2)))}
            </span>
          </div>
          <div className="flex justify-between text-slate-600">
            <span>الحالة</span>
            <span className="font-semibold text-slate-900">{paymentStatus}</span>
          </div>
        </div>

        {serverError && (
          <p className="text-sm text-red-600">{serverError}</p>
        )}

        <div className="flex gap-2 justify-end pt-1">
          <Button type="button" variant="outline" size="sm" onClick={handleClose}>
            إلغاء
          </Button>
          <Button type="submit" size="sm" loading={isSubmitting}>
            حفظ
          </Button>
        </div>
      </form>
    </Dialog>
  )
}


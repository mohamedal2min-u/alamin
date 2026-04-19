'use client'

import { useState } from 'react'
import { useForm, useFieldArray } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Plus, Trash2 } from 'lucide-react'
import { Dialog } from '@/components/ui/Dialog'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { salesApi } from '@/lib/api/sales'
import { formatNumber } from '@/lib/utils'
import type { Sale } from '@/types/sale'

// ── Schema ────────────────────────────────────────────────────────────────────
const itemSchema = z.object({
  birds_count:       z.number({ invalid_type_error: 'يجب إدخال رقم' }).int().min(1, 'عدد الطيور يجب أن يكون أكبر من 0'),
  total_weight_kg:   z.number({ invalid_type_error: 'يجب إدخال رقم' }).min(0.001, 'الوزن يجب أن يكون أكبر من 0'),
  unit_price_per_kg: z.number({ invalid_type_error: 'يجب إدخال رقم' }).min(0.001, 'السعر يجب أن يكون أكبر من 0'),
  notes:             z.string().max(5000).optional().or(z.literal('')),
})

const schema = z.object({
  sale_date:       z.string().min(1, 'تاريخ البيع مطلوب').regex(/^\d{4}-\d{2}-\d{2}$/, 'صيغة التاريخ غير صحيحة'),
  buyer_name:      z.string().max(190).optional().or(z.literal('')),
  reference_no:    z.string().max(100).optional().or(z.literal('')),
  discount_amount: z.number({ invalid_type_error: 'يجب إدخال رقم' }).min(0).optional().or(z.literal(0)),
  received_amount: z.number({ invalid_type_error: 'يجب إدخال رقم' }).min(0).optional().or(z.literal(0)),
  notes:           z.string().max(5000).optional().or(z.literal('')),
  items:           z.array(itemSchema).min(1, 'يجب إضافة سطر بيع واحد على الأقل'),
})
type FormData = z.infer<typeof schema>

// ── Props ─────────────────────────────────────────────────────────────────────
interface Props {
  flockId: number
  isOpen: boolean
  onClose: () => void
  onSuccess: (sale: Sale) => void
}

// ── Component ─────────────────────────────────────────────────────────────────
export function CreateSaleDialog({ flockId, isOpen, onClose, onSuccess }: Props) {
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    control,
    watch,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      sale_date:       new Date().toISOString().split('T')[0],
      discount_amount: 0,
      received_amount: 0,
      items: [{ birds_count: undefined as unknown as number, total_weight_kg: undefined as unknown as number, unit_price_per_kg: undefined as unknown as number }],
    },
  })

  const { fields, append, remove } = useFieldArray({ control, name: 'items' })

  // ── Live calculations ──────────────────────────────────────────────────────
  const watchedItems   = watch('items') ?? []
  const watchDiscount  = Number(watch('discount_amount') || 0)
  const watchReceived  = Number(watch('received_amount') || 0)

  const gross = watchedItems.reduce((sum, it) => {
    const w = Number(it.total_weight_kg ?? 0)
    const p = Number(it.unit_price_per_kg ?? 0)
    return sum + w * p
  }, 0)
  const net       = Math.max(gross - watchDiscount, 0)
  const remaining = Math.max(net - watchReceived, 0)

  const handleClose = () => {
    reset()
    setServerError(null)
    onClose()
  }

  const onSubmit = async (data: FormData) => {
    setServerError(null)
    try {
      const res = await salesApi.create(flockId, {
        sale_date:       data.sale_date,
        buyer_name:      data.buyer_name || undefined,
        reference_no:    data.reference_no || undefined,
        discount_amount: data.discount_amount || 0,
        received_amount: data.received_amount || 0,
        notes:           data.notes || undefined,
        items: data.items.map((it) => ({
          birds_count:       it.birds_count,
          total_weight_kg:   it.total_weight_kg,
          unit_price_per_kg: it.unit_price_per_kg,
          notes:             it.notes || undefined,
        })),
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
    <Dialog isOpen={isOpen} onClose={handleClose} title="تسجيل بيعة جديدة" className="max-w-2xl">
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-5 pt-4" noValidate>

        {/* Main info */}
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
          <Input
            {...register('sale_date')}
            id="sale_date"
            label="تاريخ البيع"
            type="date"
            error={errors.sale_date?.message}
            required
          />
          <Input
            {...register('buyer_name')}
            id="buyer_name"
            label="اسم المشتري"
            placeholder="اختياري"
            error={errors.buyer_name?.message}
          />
          <Input
            {...register('reference_no')}
            id="reference_no"
            label="رقم المرجع"
            placeholder="اختياري"
            error={errors.reference_no?.message}
          />
        </div>

        {/* Sale items */}
        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <p className="text-sm font-semibold text-slate-700">سطور البيع</p>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => append({ birds_count: undefined as unknown as number, total_weight_kg: undefined as unknown as number, unit_price_per_kg: undefined as unknown as number })}
            >
              <Plus className="h-3.5 w-3.5" />
              إضافة سطر
            </Button>
          </div>

          {errors.items && !Array.isArray(errors.items) && (
            <p className="text-xs text-red-500">{errors.items.message}</p>
          )}

          <div className="space-y-2">
            {fields.map((field, idx) => {
              const w = Number(watchedItems[idx]?.total_weight_kg ?? 0)
              const p = Number(watchedItems[idx]?.unit_price_per_kg ?? 0)
              const lineTotal = w * p

              return (
                <div
                  key={field.id}
                  className="rounded-xl border border-slate-200 bg-slate-50 p-3 space-y-2"
                >
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-semibold text-slate-500">سطر {idx + 1}</span>
                    {fields.length > 1 && (
                      <button
                        type="button"
                        onClick={() => remove(idx)}
                        className="rounded-lg p-1 text-slate-400 hover:bg-red-50 hover:text-red-600"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                    )}
                  </div>

                  <div className="grid grid-cols-3 gap-2">
                    <Input
                      {...register(`items.${idx}.birds_count`, { valueAsNumber: true })}
                      id={`birds_count_${idx}`}
                      label="عدد الطيور"
                      type="number"
                      min={1}
                      placeholder="مثال: 100"
                      error={errors.items?.[idx]?.birds_count?.message}
                      required
                    />
                    <Input
                      {...register(`items.${idx}.total_weight_kg`, { valueAsNumber: true })}
                      id={`total_weight_kg_${idx}`}
                      label="الوزن الكلي (كغ)"
                      type="number"
                      step="0.001"
                      min={0.001}
                      placeholder="مثال: 250.5"
                      error={errors.items?.[idx]?.total_weight_kg?.message}
                      required
                    />
                    <Input
                      {...register(`items.${idx}.unit_price_per_kg`, { valueAsNumber: true })}
                      id={`unit_price_per_kg_${idx}`}
                      label="سعر الكيلو"
                      type="number"
                      step="0.01"
                      min={0.001}
                      placeholder="مثال: 15.00"
                      error={errors.items?.[idx]?.unit_price_per_kg?.message}
                      required
                    />
                  </div>

                  {lineTotal > 0 && (
                    <p className="text-end text-xs font-semibold text-slate-600">
                      إجمالي السطر: <span className="tabular-nums">{formatNumber(Number(lineTotal.toFixed(2)))}</span>
                    </p>
                  )}
                </div>
              )
            })}
          </div>
        </div>

        {/* Financial fields */}
        <div className="grid grid-cols-2 gap-3">
          <Input
            {...register('discount_amount', { valueAsNumber: true })}
            id="discount_amount"
            label="الخصم"
            type="number"
            step="0.01"
            min={0}
            placeholder="0"
            error={errors.discount_amount?.message}
          />
          <Input
            {...register('received_amount', { valueAsNumber: true })}
            id="received_amount"
            label="المبلغ المستلم"
            type="number"
            step="0.01"
            min={0}
            placeholder="0"
            error={errors.received_amount?.message}
          />
        </div>

        {/* Live summary */}
        {gross > 0 && (
          <div className="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm space-y-1.5">
            <div className="flex justify-between text-slate-500">
              <span>الإجمالي قبل الخصم</span>
              <span className="tabular-nums">{formatNumber(Number(gross.toFixed(2)))}</span>
            </div>
            {watchDiscount > 0 && (
              <div className="flex justify-between text-amber-700">
                <span>الخصم</span>
                <span className="tabular-nums">- {formatNumber(Number(watchDiscount.toFixed(2)))}</span>
              </div>
            )}
            <div className="flex justify-between border-t border-slate-100 pt-1.5 font-semibold text-slate-900">
              <span>الصافي</span>
              <span className="tabular-nums">{formatNumber(Number(net.toFixed(2)))}</span>
            </div>
            {watchReceived > 0 && (
              <div className="flex justify-between text-primary-700">
                <span>المستلم</span>
                <span className="tabular-nums">{formatNumber(Number(watchReceived.toFixed(2)))}</span>
              </div>
            )}
            <div className={`flex justify-between font-semibold ${remaining > 0 ? 'text-red-600' : 'text-primary-700'}`}>
              <span>المتبقي</span>
              <span className="tabular-nums">{formatNumber(Number(remaining.toFixed(2)))}</span>
            </div>
          </div>
        )}

        {/* Notes */}
        <div className="space-y-1">
          <label htmlFor="notes" className="text-sm font-medium text-slate-700">
            ملاحظات <span className="text-xs text-slate-400">(اختياري)</span>
          </label>
          <textarea
            {...register('notes')}
            id="notes"
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
          <Button type="submit" size="sm" loading={isSubmitting}>
            تسجيل البيعة
          </Button>
        </div>
      </form>
    </Dialog>
  )
}


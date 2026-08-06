'use client'

import { useEffect, useState } from 'react'
import { useForm, useFieldArray } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Plus, Trash2 } from 'lucide-react'
import { Dialog } from '@/components/ui/Dialog'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { salesApi } from '@/lib/api/sales'
import { formatCurrency, getTodayLocalISO } from '@/lib/utils'
import type { Sale } from '@/types/sale'

// ── Schema ────────────────────────────────────────────────────────────────────
const optionalNumber = (fieldSchema: z.ZodNumber) => z.preprocess(
  (val) => (typeof val === 'number' && Number.isNaN(val)) ? undefined : val,
  fieldSchema.optional()
)

const itemSchema = z.object({
  birds_count:      z.number({ invalid_type_error: 'يجب إدخال رقم' }).int().min(1, 'عدد الطيور يجب أن يكون أكبر من 0'),
  // الوزن القائم = وزن الطيور + الأقفاص كما يُقرأ من الميزان مباشرة.
  gross_weight_kg:  z.number({ invalid_type_error: 'يجب إدخال رقم' }).min(0.001, 'الوزن يجب أن يكون أكبر من 0'),
  crates_count:     optionalNumber(z.number({ invalid_type_error: 'يجب إدخال رقم' }).int().min(0, 'لا يمكن أن يكون سالباً')),
  crate_weight_kg:  optionalNumber(z.number({ invalid_type_error: 'يجب إدخال رقم' }).min(0, 'لا يمكن أن يكون سالباً')),
  // اختياري — إن تُرك فارغاً تُسجَّل البيعة كدين وتُرحَّل إلى الذمم لتحديد السعر لاحقاً.
  unit_price_per_kg: optionalNumber(z.number({ invalid_type_error: 'يجب إدخال رقم' }).min(0.001, 'السعر يجب أن يكون أكبر من 0')),
  notes:             z.string().max(5000).optional().or(z.literal('')),
}).refine((data) => {
  const cratesWeight = (data.crates_count ?? 0) * (data.crate_weight_kg ?? 0)
  return data.gross_weight_kg - cratesWeight > 0
}, {
  message: 'الوزن الصافي يجب أن يكون أكبر من صفر — تحقق من وزن الأقفاص',
  path: ['gross_weight_kg'],
})

const schema = z.object({
  sale_date:       z.string().min(1, 'تاريخ البيع مطلوب').regex(/^\d{4}-\d{2}-\d{2}$/, 'صيغة التاريخ غير صحيحة'),
  buyer_name:      z.string().max(190).optional().or(z.literal('')),
  reference_no:    z.string().max(100).optional().or(z.literal('')),
  // وزن قبان السيارة الإلكتروني — اختياري، يملأ الوزن القائم للسطر الأول تلقائياً عند تعبئته.
  vehicle_weight_before_kg: optionalNumber(z.number({ invalid_type_error: 'يجب إدخال رقم' }).min(0, 'لا يمكن أن يكون سالباً')),
  vehicle_weight_after_kg:  optionalNumber(z.number({ invalid_type_error: 'يجب إدخال رقم' }).min(0, 'لا يمكن أن يكون سالباً')),
  weight_deduction_kg:      optionalNumber(z.number({ invalid_type_error: 'يجب إدخال رقم' }).min(0, 'لا يمكن أن يكون سالباً')),
  discount_amount: z.number({ invalid_type_error: 'يجب إدخال رقم' }).min(0).optional().or(z.literal(0)),
  received_amount: z.number({ invalid_type_error: 'يجب إدخال رقم' }).min(0).optional().or(z.literal(0)),
  notes:           z.string().max(5000).optional().or(z.literal('')),
  items:           z.array(itemSchema).min(1, 'يجب إضافة سطر بيع واحد على الأقل'),
}).refine((data) => {
  if (data.vehicle_weight_before_kg == null || data.vehicle_weight_after_kg == null) return true
  return data.vehicle_weight_after_kg > data.vehicle_weight_before_kg
}, {
  message: 'الوزن الثاني يجب أن يكون أكبر من الوزن الأول',
  path: ['vehicle_weight_after_kg'],
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
    setValue,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      sale_date:       getTodayLocalISO(),
      vehicle_weight_before_kg: undefined,
      vehicle_weight_after_kg:  undefined,
      weight_deduction_kg:      undefined,
      discount_amount: 0,
      received_amount: 0,
      items: [{
        birds_count:      undefined as unknown as number,
        gross_weight_kg:  undefined as unknown as number,
        crates_count:     undefined,
        crate_weight_kg:  undefined,
        unit_price_per_kg: undefined,
      }],
    },
  })

  const { fields, append, remove } = useFieldArray({ control, name: 'items' })

  // ── Live calculations ──────────────────────────────────────────────────────
  const watchedItems   = watch('items') ?? []
  const watchDiscount  = Number(watch('discount_amount') || 0)
  const watchReceived  = Number(watch('received_amount') || 0)

  // ── وزن قبان السيارة (قبل/بعد) ─────────────────────────────────────────────
  const watchVehicleBefore    = watch('vehicle_weight_before_kg')
  const watchVehicleAfter     = watch('vehicle_weight_after_kg')
  const watchWeightDeduction  = watch('weight_deduction_kg')

  const vehicleNetWeight = (watchVehicleBefore != null && watchVehicleAfter != null)
    ? Math.max(0, Number(watchVehicleAfter) - Number(watchVehicleBefore) - Number(watchWeightDeduction || 0))
    : null

  const vehicleWeightLocksFirstItem = vehicleNetWeight !== null && vehicleNetWeight > 0

  useEffect(() => {
    if (vehicleWeightLocksFirstItem) {
      setValue('items.0.gross_weight_kg', vehicleNetWeight as number, { shouldValidate: true })
    }
  }, [vehicleWeightLocksFirstItem, vehicleNetWeight, setValue])

  const netWeightOf = (it: { gross_weight_kg?: number; crates_count?: number; crate_weight_kg?: number }) => {
    const cratesWeight = Number(it.crates_count ?? 0) * Number(it.crate_weight_kg ?? 0)
    return Math.max(0, Number(it.gross_weight_kg ?? 0) - cratesWeight)
  }

  const gross = watchedItems.reduce((sum, it) => {
    const w = netWeightOf(it)
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
        vehicle_weight_before_kg: data.vehicle_weight_before_kg ?? undefined,
        vehicle_weight_after_kg:  data.vehicle_weight_after_kg ?? undefined,
        weight_deduction_kg:      data.weight_deduction_kg ?? undefined,
        discount_amount: data.discount_amount || 0,
        received_amount: data.received_amount || 0,
        notes:           data.notes || undefined,
        items: data.items.map((it) => ({
          birds_count:       it.birds_count,
          total_weight_kg:   netWeightOf(it),
          crates_count:      it.crates_count || undefined,
          crate_weight_kg:   it.crate_weight_kg || undefined,
          gross_weight_kg:   it.gross_weight_kg,
          unit_price_per_kg: it.unit_price_per_kg ?? undefined,
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

        {/* Vehicle weighbridge (before/after) */}
        <div className="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
          <p className="text-sm font-semibold text-slate-700">وزن السيارة (اختياري)</p>
          <div className="grid grid-cols-3 gap-2">
            <Input
              {...register('vehicle_weight_before_kg', { valueAsNumber: true })}
              id="vehicle_weight_before_kg"
              label="الوزن الأول (كغ)"
              type="number"
              step="0.001"
              min={0}
              placeholder="مثال: 9472"
              error={errors.vehicle_weight_before_kg?.message}
            />
            <Input
              {...register('vehicle_weight_after_kg', { valueAsNumber: true })}
              id="vehicle_weight_after_kg"
              label="الوزن الثاني (كغ)"
              type="number"
              step="0.001"
              min={0}
              placeholder="مثال: 15874"
              error={errors.vehicle_weight_after_kg?.message}
            />
            <Input
              {...register('weight_deduction_kg', { valueAsNumber: true })}
              id="weight_deduction_kg"
              label="خصم الوزن (كغم)"
              type="number"
              step="0.001"
              min={0}
              placeholder="اختياري"
              error={errors.weight_deduction_kg?.message}
            />
          </div>
          {vehicleNetWeight !== null && (
            <p className="text-end text-xs font-semibold text-slate-600">
              الوزن الصافي للسيارة: <span className="tabular-nums text-slate-900">{vehicleNetWeight.toFixed(2)} كغ</span>
            </p>
          )}
        </div>

        {/* Sale items */}
        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <p className="text-sm font-semibold text-slate-700">سطور البيع</p>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => append({
                birds_count:      undefined as unknown as number,
                gross_weight_kg:  undefined as unknown as number,
                crates_count:     undefined,
                crate_weight_kg:  undefined,
                unit_price_per_kg: undefined,
              })}
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
              const it           = watchedItems[idx] ?? {}
              const cratesWeight = Number(it.crates_count ?? 0) * Number(it.crate_weight_kg ?? 0)
              const netWeight    = netWeightOf(it)
              const p            = Number(it.unit_price_per_kg ?? 0)
              const lineTotal    = netWeight * p
              const hasCrates    = Number(it.crates_count ?? 0) > 0 && Number(it.crate_weight_kg ?? 0) > 0

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
                      {...register(`items.${idx}.crates_count`, { valueAsNumber: true })}
                      id={`crates_count_${idx}`}
                      label="عدد الأقفاص"
                      type="number"
                      min={0}
                      placeholder="اختياري"
                      error={errors.items?.[idx]?.crates_count?.message}
                    />
                    <Input
                      {...register(`items.${idx}.crate_weight_kg`, { valueAsNumber: true })}
                      id={`crate_weight_kg_${idx}`}
                      label="وزن القفص الواحد (كغ)"
                      type="number"
                      step="0.01"
                      min={0}
                      placeholder="اختياري"
                      error={errors.items?.[idx]?.crate_weight_kg?.message}
                    />
                  </div>

                  <div className="grid grid-cols-2 gap-2">
                    <Input
                      {...register(`items.${idx}.gross_weight_kg`, { valueAsNumber: true })}
                      id={`gross_weight_kg_${idx}`}
                      label="الوزن القائم (كغ)"
                      type="number"
                      step="0.001"
                      min={0.001}
                      placeholder="مثال: 250.5"
                      error={errors.items?.[idx]?.gross_weight_kg?.message}
                      disabled={idx === 0 && vehicleWeightLocksFirstItem}
                      required
                    />
                    <Input
                      {...register(`items.${idx}.unit_price_per_kg`, { valueAsNumber: true })}
                      id={`unit_price_per_kg_${idx}`}
                      label="سعر الكيلو"
                      type="number"
                      step="0.01"
                      min={0.001}
                      placeholder="اختياري"
                      error={errors.items?.[idx]?.unit_price_per_kg?.message}
                    />
                  </div>

                  {hasCrates && (
                    <p className="text-end text-xs font-semibold text-slate-500">
                      وزن الأقفاص: <span className="tabular-nums">{cratesWeight.toFixed(2)} كغ</span>
                      {' — '}
                      الوزن الصافي: <span className="tabular-nums text-slate-700">{netWeight.toFixed(2)} كغ</span>
                    </p>
                  )}

                  {lineTotal > 0 ? (
                    <p className="text-end text-xs font-semibold text-slate-600">
                      إجمالي السطر: <span className="tabular-nums">{formatCurrency(lineTotal)}</span>
                    </p>
                  ) : netWeight > 0 && !it.unit_price_per_kg && (
                    <p className="text-end text-xs font-semibold text-amber-600">
                      السعر غير محدد — ستُسجَّل البيعة كدين وتُرحَّل إلى الذمم والمراجعة لتحديد السعر لاحقاً.
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
              <span className="tabular-nums">{formatCurrency(gross)}</span>
            </div>
            {watchDiscount > 0 && (
              <div className="flex justify-between text-emerald-700">
                <span>الخصم</span>
                <span className="tabular-nums">- {formatCurrency(watchDiscount)}</span>
              </div>
            )}
            <div className="flex justify-between border-t border-slate-100 pt-1.5 font-semibold text-slate-900">
              <span>الصافي</span>
              <span className="tabular-nums">{formatCurrency(net)}</span>
            </div>
            {watchReceived > 0 && (
              <div className="flex justify-between text-primary-700">
                <span>المستلم</span>
                <span className="tabular-nums">{formatCurrency(watchReceived)}</span>
              </div>
            )}
            <div className={`flex justify-between font-semibold ${remaining > 0 ? 'text-red-600' : 'text-primary-700'}`}>
              <span>المتبقي</span>
              <span className="tabular-nums">{formatCurrency(remaining)}</span>
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


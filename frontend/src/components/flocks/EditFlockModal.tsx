'use client'

import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { X, Save, AlertCircle } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { flocksApi } from '@/lib/api/flocks'
import type { Flock } from '@/types/flock'

const editFlockSchema = z.object({
  name: z.string().min(2, 'اسم الفوج يجب أن يكون حرفين على الأقل').max(190),
  initial_count: z.number().int().positive('يجب أن يكون أكبر من الصفر'),
  chick_unit_price: z.number().min(0, 'لا يمكن أن يكون أقل من الصفر').nullable().optional().or(z.literal('')).or(z.null()),
  notes: z.string().max(1000).optional().or(z.literal('')),
})

type EditFlockForm = z.infer<typeof editFlockSchema>

interface EditFlockModalProps {
  flock: Flock
  isOpen: boolean
  onClose: () => void
  onSuccess: (updated: Flock) => void
}

export function EditFlockModal({ flock, isOpen, onClose, onSuccess }: EditFlockModalProps) {
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<EditFlockForm>({
    resolver: zodResolver(editFlockSchema),
    defaultValues: {
      name: flock.name,
      initial_count: flock.initial_count,
      chick_unit_price: flock.chick_unit_price || '',
      notes: flock.notes || '',
    },
  })

  const initialCount = watch('initial_count')
  const unitPrice = watch('chick_unit_price')
  const totalInvestment = (Number(initialCount) || 0) * (Number(unitPrice) || 0)

  const onSubmit = async (data: EditFlockForm) => {
    setServerError(null)
    try {
      const result = await flocksApi.update(flock.id, {
        name: data.name,
        initial_count: data.initial_count,
        chick_unit_price: data.chick_unit_price ? Number(data.chick_unit_price) : null,
        notes: data.notes || undefined,
      })
      onSuccess(result.data)
      onClose()
    } catch (err: any) {
      setServerError(err?.response?.data?.message || 'تعذّر تحديث البيانات')
    }
  }

  if (!isOpen) return null

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm animate-in fade-in duration-200">
      <div className="w-full max-w-lg bg-white rounded-3xl shadow-2xl overflow-hidden animate-in zoom-in-95 duration-200" dir="rtl">
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50/50">
          <h2 className="text-lg font-bold text-slate-800 Arabic-font">تعديل بيانات الفوج</h2>
          <button onClick={onClose} className="p-2 text-slate-400 hover:text-slate-600 transition-colors">
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit(onSubmit)} className="p-6 space-y-5">
          <Input
            {...register('name')}
            label="اسم الفوج"
            error={errors.name?.message}
            required
          />

          <div className="grid grid-cols-2 gap-4">
            <Input
              {...register('initial_count', { valueAsNumber: true })}
              label="العدد الكلي"
              type="number"
              error={errors.initial_count?.message}
              required
            />
            <Input
              {...register('chick_unit_price', { valueAsNumber: true })}
              label="سعر الصوص ($)"
              type="number"
              step="0.01"
              error={errors.chick_unit_price?.message}
            />
          </div>

          <div className="rounded-2xl bg-indigo-50/50 border border-indigo-100 p-4">
            <div className="flex items-center justify-between">
              <span className="text-xs font-bold text-slate-500">إجمالي الاستثمار المحسوب:</span>
              <span className="text-base font-black text-indigo-700 tabular-nums">
                {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(totalInvestment)}
              </span>
            </div>
          </div>

          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-bold text-slate-700 Arabic-font">الملاحظات</label>
            <textarea
              {...register('notes')}
              rows={3}
              className="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 transition-all cursor-text placeholder:text-slate-300"
              placeholder="تبدأ كتابة الملاحظات هنا..."
            />
          </div>

          {serverError && (
            <div className="flex items-center gap-2 px-4 py-3 rounded-2xl border border-red-100 bg-red-50 text-red-600 text-xs font-bold">
              <AlertCircle className="w-4 h-4" />
              {serverError}
            </div>
          )}

          <div className="flex items-center gap-3 pt-2">
            <Button type="submit" loading={isSubmitting} className="flex-1 rounded-2xl h-12">
              <Save className="w-4 h-4 ml-2" />
              حفظ التغييرات
            </Button>
            <Button type="button" variant="outline" onClick={onClose} disabled={isSubmitting} className="flex-1 rounded-2xl h-12">
              إلغاء
            </Button>
          </div>
        </form>
      </div>
    </div>
  )
}


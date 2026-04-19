'use client'

import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useRouter } from 'next/navigation'
import Link from 'next/link'
import { ArrowRight } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'
import { flocksApi } from '@/lib/api/flocks'

// ── Validation ────────────────────────────────────────────────────────────────
const createFlockSchema = z.object({
  name: z
    .string()
    .min(2, 'اسم الفوج يجب أن يكون حرفين على الأقل')
    .max(190, 'اسم الفوج طويل جداً'),
  start_date: z
    .string()
    .min(1, 'تاريخ البدء مطلوب')
    .regex(/^\d{4}-\d{2}-\d{2}$/, 'صيغة التاريخ غير صحيحة'),
  initial_count: z
    .number({ invalid_type_error: 'يجب إدخال رقم صحيح' })
    .int('يجب أن يكون عدداً صحيحاً')
    .positive('العدد الأولي يجب أن يكون أكبر من الصفر'),
  chick_unit_price: z
    .number({ invalid_type_error: 'يجب إدخال رقم' })
    .min(0, 'سعر الصوص لا يمكن أن يكون أقل من الصفر')
    .optional()
    .or(z.literal(''))
    .or(z.null()),
  chick_paid_amount: z
    .number({ invalid_type_error: 'يجب إدخال رقم' })
    .min(0, 'المبلغ المدفوع لا يمكن أن يكون أقل من الصفر')
    .optional()
    .or(z.literal(''))
    .or(z.null()),
  notes: z.string().max(1000, 'الملاحظات طويلة جداً').optional().or(z.literal('')),
})
type CreateFlockForm = z.infer<typeof createFlockSchema>

// ── Component ─────────────────────────────────────────────────────────────────
export default function CreateFlockPage() {
  const router = useRouter()
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<CreateFlockForm>({
    resolver: zodResolver(createFlockSchema),
    defaultValues: {
      start_date: new Date().toISOString().split('T')[0],
      initial_count: 0,
      chick_unit_price: '',
      chick_paid_amount: '',
    },
  })

  const initialCount = watch('initial_count')
  const unitPrice = watch('chick_unit_price')
  const totalInvestment = (Number(initialCount) || 0) * (Number(unitPrice) || 0)

  const onSubmit = async (data: CreateFlockForm) => {
    setServerError(null)
    try {
      const result = await flocksApi.create({
        name: data.name,
        start_date: data.start_date,
        initial_count: data.initial_count,
        chick_unit_price: data.chick_unit_price ? Number(data.chick_unit_price) : null,
        chick_paid_amount: data.chick_paid_amount ? Number(data.chick_paid_amount) : 0,
        notes: data.notes || undefined,
      })
      router.push(`/flocks/${result.data.id}`)
    } catch (err: unknown) {
      const axiosErr = err as {
        response?: { data?: { message?: string; errors?: Record<string, string[]> } }
      }
      const firstValidation = axiosErr?.response?.data?.errors
        ? Object.values(axiosErr.response.data.errors)[0]?.[0]
        : null
      setServerError(firstValidation ?? axiosErr?.response?.data?.message ?? 'حدث خطأ غير متوقع')
    }
  }

  return (
    <div className="mx-auto max-w-xl space-y-6">
      {/* Back */}
      <Link
        href="/flocks"
        className="inline-flex items-center gap-1.5 text-sm text-slate-500 transition hover:text-slate-900"
      >
        <ArrowRight className="h-4 w-4" />
        العودة للأفواج
      </Link>

      <div>
        <h1 className="text-2xl font-bold text-slate-900">فوج جديد</h1>
        <p className="mt-1 text-sm text-slate-500">أدخل بيانات الفوج الجديد للمزرعة</p>
      </div>

      <Card>
        <CardHeader>
          <h2 className="font-semibold text-slate-800">بيانات الفوج</h2>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-5" noValidate>
            <Input
              {...register('name')}
              id="name"
              label="اسم الفوج"
              placeholder="مثال: فوج أبريل 2026"
              error={errors.name?.message}
              required
            />

            <Input
              {...register('start_date')}
              id="start_date"
              label="تاريخ البدء"
              type="date"
              error={errors.start_date?.message}
              required
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Input
                {...register('initial_count', { valueAsNumber: true })}
                id="initial_count"
                label="العدد الأولي (عدد الكتاكيت)"
                type="number"
                min={1}
                placeholder="مثال: 5000"
                error={errors.initial_count?.message}
                required
              />

              <Input
                {...register('chick_unit_price', { valueAsNumber: true })}
                id="chick_unit_price"
                label="سعر الصوص الواحد ($)"
                type="number"
                step="0.01"
                min={0}
                placeholder="مثال: 0.50"
                error={errors.chick_unit_price?.message}
              />

              <Input
                {...register('chick_paid_amount', { valueAsNumber: true })}
                id="chick_paid_amount"
                label="المبلغ المدفوع حالياً ($)"
                type="number"
                step="0.01"
                min={0}
                placeholder="مثال: 1000"
                error={errors.chick_paid_amount?.message}
              />
            </div>

            <p className="text-[11px] text-red-500 font-medium">
              إذا تم ترك المبلغ المدفوع فارغاً أو أقل من الإجمالي، سيتم اعتبار الباقي ديناً في كشف الحساب.
            </p>

            {totalInvestment > 0 && (
              <div className="rounded-xl bg-primary-50/50 border border-primary-100 p-4 dark:bg-primary-900/10 dark:border-primary-800/40">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium text-slate-600 dark:text-slate-400">إجمالي تكلفة الشراء التقديرية:</span>
                  <span className="text-lg font-black text-primary-700 dark:text-primary-400">
                    {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(totalInvestment)}
                  </span>
                </div>
              </div>
            )}

            {/* Notes textarea (not in Input component) */}
            <div className="flex flex-col gap-1.5">
              <label htmlFor="notes" className="text-sm font-medium text-slate-700">
                ملاحظات
                <span className="ms-1 text-xs font-normal text-slate-400">(اختياري)</span>
              </label>
              <textarea
                {...register('notes')}
                id="notes"
                rows={3}
                placeholder="أي ملاحظات إضافية عن الفوج..."
                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
              />
              {errors.notes && (
                <p className="text-xs text-red-600">{errors.notes.message}</p>
              )}
            </div>

            {serverError && (
              <div className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                {serverError}
              </div>
            )}

            <div className="flex items-center justify-end gap-3 pt-2">
              <Button
                type="button"
                variant="outline"
                onClick={() => router.back()}
              >
                إلغاء
              </Button>
              <Button type="submit" loading={isSubmitting}>
                إنشاء الفوج
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}


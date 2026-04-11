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
    formState: { errors, isSubmitting },
  } = useForm<CreateFlockForm>({
    resolver: zodResolver(createFlockSchema),
    defaultValues: {
      start_date: new Date().toISOString().split('T')[0],
    },
  })

  const onSubmit = async (data: CreateFlockForm) => {
    setServerError(null)
    try {
      const result = await flocksApi.create({
        name: data.name,
        start_date: data.start_date,
        initial_count: data.initial_count,
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

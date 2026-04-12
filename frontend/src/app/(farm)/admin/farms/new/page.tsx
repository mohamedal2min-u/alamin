'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { ArrowRight } from 'lucide-react'
import Link from 'next/link'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { adminApi } from '@/lib/api/admin'

const schema = z.object({
  name:       z.string().min(1, 'اسم المزرعة مطلوب').max(190, 'الاسم طويل جداً'),
  location:   z.string().max(500).optional(),
  started_at: z.string().optional(),
})
type FormData = z.infer<typeof schema>

export default function NewFarmPage() {
  const router = useRouter()
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({ resolver: zodResolver(schema) })

  const onSubmit = async (data: FormData) => {
    setServerError(null)
    try {
      await adminApi.createFarm({
        name:       data.name,
        location:   data.location || undefined,
        started_at: data.started_at || undefined,
      })
      router.replace('/admin/farms')
    } catch (err: unknown) {
      const axiosError = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const firstError = axiosError?.response?.data?.errors
        ? Object.values(axiosError.response.data.errors)[0]?.[0]
        : null
      setServerError(firstError ?? axiosError?.response?.data?.message ?? 'حدث خطأ غير متوقع')
    }
  }

  return (
    <div className="mx-auto max-w-lg space-y-6">
      {/* Back link */}
      <Link
        href="/admin/farms"
        className="flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700"
      >
        <ArrowRight className="h-4 w-4" />
        العودة إلى المداجن
      </Link>

      <div>
        <h1 className="text-2xl font-bold text-slate-900">مزرعة جديدة</h1>
        <p className="mt-0.5 text-sm text-slate-500">أضف مزرعة جديدة إلى النظام</p>
      </div>

      <div className="rounded-2xl border border-slate-200 bg-white p-6">
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
          <Input
            {...register('name')}
            id="name"
            label="اسم المزرعة"
            placeholder="مثال: مزرعة الأمل"
            error={errors.name?.message}
            required
          />

          <Input
            {...register('location')}
            id="location"
            label="الموقع"
            placeholder="مثال: محافظة الرياض"
            error={errors.location?.message}
          />

          <Input
            {...register('started_at')}
            id="started_at"
            label="تاريخ البدء"
            type="date"
            error={errors.started_at?.message}
          />

          {serverError && (
            <div className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
              {serverError}
            </div>
          )}

          <div className="flex gap-3 pt-2">
            <Button type="submit" loading={isSubmitting} className="flex-1">
              إنشاء المزرعة
            </Button>
            <Button type="button" variant="outline" asChild>
              <Link href="/admin/farms">إلغاء</Link>
            </Button>
          </div>
        </form>
      </div>
    </div>
  )
}

'use client'

import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useRouter, useSearchParams } from 'next/navigation'
import { useAuthStore } from '@/stores/auth.store'
import { useFarmStore } from '@/stores/farm.store'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import type { Farm } from '@/types/farm'

// ── Zod schema ────────────────────────────────────────────────────────────────
const loginSchema = z.object({
  login: z.string().min(1, 'البريد الإلكتروني أو رقم الواتساب مطلوب'),
  password: z.string().min(1, 'كلمة المرور مطلوبة'),
})
type LoginForm = z.infer<typeof loginSchema>

// ── Component ─────────────────────────────────────────────────────────────────
export default function LoginPage() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const { login } = useAuthStore()
  const { setFarms, setCurrentFarm } = useFarmStore()
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginForm>({ resolver: zodResolver(loginSchema) })

  const onSubmit = async (data: LoginForm) => {
    setServerError(null)
    try {
      await login(data.login, data.password)

      // Hydrate farm store from user returned by auth store
      const user = useAuthStore.getState().user
      if (user?.farms?.length) {
        const farms: Farm[] = user.farms.map((f) => ({
          id: f.id,
          name: f.name,
          status: f.status,
          role: f.role,
          is_primary: f.is_primary,
        }))
        setFarms(farms)

        // Auto-select primary farm, or first one
        const primaryFarm = farms.find((f) => f.is_primary) ?? farms[0]
        setCurrentFarm(primaryFarm)
      }

      const from = searchParams.get('from') ?? '/flocks'
      router.replace(from)
    } catch (err: unknown) {
      const axiosError = err as { response?: { data?: { message?: string } } }
      setServerError(
        axiosError?.response?.data?.message ?? 'حدث خطأ غير متوقع، حاول مجدداً'
      )
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-primary-50 to-slate-100 p-4">
      <div className="w-full max-w-md">
        {/* Logo / Brand */}
        <div className="mb-8 text-center">
          <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-600 shadow-lg">
            <span className="text-3xl">🐔</span>
          </div>
          <h1 className="text-2xl font-bold text-slate-900">دجاجاتي</h1>
          <p className="mt-1 text-sm text-slate-500">نظام إدارة المداجن</p>
        </div>

        {/* Card */}
        <div className="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
          <h2 className="mb-6 text-xl font-semibold text-slate-800">تسجيل الدخول</h2>

          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
            <Input
              {...register('login')}
              id="login"
              label="البريد الإلكتروني أو الواتساب"
              placeholder="أدخل البريد الإلكتروني أو رقم الواتساب"
              type="text"
              autoComplete="username"
              error={errors.login?.message}
              required
            />

            <Input
              {...register('password')}
              id="password"
              label="كلمة المرور"
              placeholder="أدخل كلمة المرور"
              type="password"
              autoComplete="current-password"
              error={errors.password?.message}
              required
            />

            {serverError && (
              <div className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                {serverError}
              </div>
            )}

            <Button
              type="submit"
              className="w-full"
              size="lg"
              loading={isSubmitting}
            >
              دخول
            </Button>
          </form>
        </div>

        <p className="mt-6 text-center text-xs text-slate-400">
          © {new Date().getFullYear()} دجاجاتي — جميع الحقوق محفوظة
        </p>
      </div>
    </div>
  )
}

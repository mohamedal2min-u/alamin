'use client'

import { useState, Suspense } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useRouter, useSearchParams } from 'next/navigation'
import { useAuthStore } from '@/stores/auth.store'
import { useFarmStore } from '@/stores/farm.store'
import { canAccessRoute, getDefaultRoute } from '@/lib/roles'
import { apiClient } from '@/lib/api/client'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import type { Farm } from '@/types/farm'
import type { FarmRole } from '@/types/auth'

// ── Mode ──────────────────────────────────────────────────────────────────────
type AuthMode = 'login' | 'register'

// ── Zod Schemas ───────────────────────────────────────────────────────────────
const loginSchema = z.object({
  login: z.string().min(1, 'البريد الإلكتروني أو رقم الواتساب مطلوب'),
  password: z.string().min(1, 'كلمة المرور مطلوبة'),
})
type LoginForm = z.infer<typeof loginSchema>

const registerSchema = z
  .object({
    name: z.string().min(1, 'الاسم مطلوب').max(150),
    whatsapp: z.string().min(1, 'رقم الواتساب مطلوب').max(30),
    password: z.string().min(8, 'كلمة المرور يجب أن تكون 8 أحرف على الأقل'),
    password_confirmation: z.string().min(1, 'تأكيد كلمة المرور مطلوب'),
    email: z.string().email('صيغة البريد الإلكتروني غير صحيحة').max(190).or(z.literal('')).optional(),
    location: z.string().max(255).optional(),
    farm_name: z.string().max(190).optional(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'تأكيد كلمة المرور غير متطابق',
    path: ['password_confirmation'],
  })
type RegisterForm = z.infer<typeof registerSchema>

// ── Tab Button ────────────────────────────────────────────────────────────────
function TabButton({
  active,
  onClick,
  children,
}: {
  active: boolean
  onClick: () => void
  children: React.ReactNode
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'relative flex-1 py-3 text-sm font-medium transition-all duration-300 rounded-xl',
        active
          ? 'bg-primary-600 text-white shadow-md'
          : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
      )}
    >
      {children}
    </button>
  )
}

// ── Component ─────────────────────────────────────────────────────────────────
function LoginPageInner() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const { login } = useAuthStore()
  const { setFarms, setCurrentFarm } = useFarmStore()

  const [mode, setMode] = useState<AuthMode>('login')
  const [serverError, setServerError] = useState<string | null>(null)
  const [registerSuccess, setRegisterSuccess] = useState(false)

  // ── Login Form ────────────────────────────────────────────────────────────
  const loginForm = useForm<LoginForm>({ resolver: zodResolver(loginSchema) })

  const onLogin = async (data: LoginForm) => {
    setServerError(null)
    try {
      await login(data.login, data.password)

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

        const primaryFarm = farms.find((f) => f.is_primary) ?? farms[0]
        setCurrentFarm(primaryFarm)

        const role = (primaryFarm?.role ?? null) as FarmRole | null
        const from = searchParams.get('from')
        const destination =
          from && canAccessRoute(role, from) ? from : getDefaultRoute(role)
        router.replace(destination)
      } else {
        router.replace(getDefaultRoute(null))
      }
    } catch (err: unknown) {
      const axiosError = err as { response?: { data?: { message?: string } } }
      setServerError(
        axiosError?.response?.data?.message ?? 'حدث خطأ غير متوقع، حاول مجدداً'
      )
    }
  }

  // ── Register Form ─────────────────────────────────────────────────────────
  const registerForm = useForm<RegisterForm>({ resolver: zodResolver(registerSchema) })

  const onRegister = async (data: RegisterForm) => {
    setServerError(null)
    try {
      await apiClient.post('/auth/register-request', {
        name: data.name,
        whatsapp: data.whatsapp,
        password: data.password,
        password_confirmation: data.password_confirmation,
        email: data.email || undefined,
        location: data.location || undefined,
        farm_name: data.farm_name || undefined,
      })
      setRegisterSuccess(true)
    } catch (err: unknown) {
      const axiosError = err as {
        response?: { data?: { message?: string; errors?: Record<string, string[]> } }
      }
      const validationErrors = axiosError?.response?.data?.errors
      if (validationErrors) {
        // Map Laravel field-level errors to react-hook-form
        Object.entries(validationErrors).forEach(([field, messages]) => {
          registerForm.setError(field as keyof RegisterForm, {
            message: messages[0],
          })
        })
      } else {
        setServerError(
          axiosError?.response?.data?.message ?? 'حدث خطأ غير متوقع، حاول مجدداً'
        )
      }
    }
  }

  // ── Tab Switch Handler ────────────────────────────────────────────────────
  const switchMode = (newMode: AuthMode) => {
    setServerError(null)
    setRegisterSuccess(false)
    setMode(newMode)
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-primary-50 to-slate-100 p-4">
      <div className="w-full max-w-md">
        {/* Logo / Brand */}
        <div className="mb-8 text-center">
          {/* Logo Section */}
          <div className="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-primary-600 shadow-xl border border-primary-700/20">
            <img
              src="/logo.png"
              alt="Dajajati"
              className="h-12 w-12 object-contain brightness-0 invert"
            />
          </div>

          <div className="text-center">
            <h1 className="text-3xl font-bold tracking-tight text-slate-900">
              دجاجاتي
            </h1>
            <p className="mt-2 text-sm text-slate-600">
              نظام إدارة مزارع الدواجن الذكي
            </p>
          </div>
        </div>

        {/* Card */}
        <div className="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
          {/* Tabs Switcher */}
          <div className="mb-6 flex gap-1 rounded-xl bg-slate-100 p-1">
            <TabButton
              active={mode === 'login'}
              onClick={() => switchMode('login')}
            >
              تسجيل الدخول
            </TabButton>
            <TabButton
              active={mode === 'register'}
              onClick={() => switchMode('register')}
            >
              حساب جديد
            </TabButton>
          </div>

          {/* ── Login View ─────────────────────────────────────────────── */}
          {mode === 'login' && (
            <form
              onSubmit={loginForm.handleSubmit(onLogin)}
              className="space-y-4"
              noValidate
            >
              <Input
                {...loginForm.register('login')}
                id="login"
                label="البريد الإلكتروني أو الواتساب"
                placeholder="أدخل البريد الإلكتروني أو رقم الواتساب"
                type="text"
                autoComplete="username"
                error={loginForm.formState.errors.login?.message}
                required
              />

              <Input
                {...loginForm.register('password')}
                id="password"
                label="كلمة المرور"
                placeholder="أدخل كلمة المرور"
                type="password"
                autoComplete="current-password"
                error={loginForm.formState.errors.password?.message}
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
                loading={loginForm.formState.isSubmitting}
              >
                دخول
              </Button>
            </form>
          )}

          {/* ── Register View ──────────────────────────────────────────── */}
          {mode === 'register' && !registerSuccess && (
            <form
              onSubmit={registerForm.handleSubmit(onRegister)}
              className="space-y-4"
              noValidate
            >
              <Input
                {...registerForm.register('name')}
                id="reg-name"
                label="الاسم الكامل"
                placeholder="أدخل اسمك الكامل"
                type="text"
                autoComplete="name"
                error={registerForm.formState.errors.name?.message}
                required
              />

              <Input
                {...registerForm.register('whatsapp')}
                id="reg-whatsapp"
                label="رقم الواتساب"
                placeholder="مثال: 966501234567"
                type="tel"
                dir="ltr"
                className="text-left"
                autoComplete="tel"
                error={registerForm.formState.errors.whatsapp?.message}
                required
              />

              <Input
                {...registerForm.register('email')}
                id="reg-email"
                label="البريد الإلكتروني"
                placeholder="اختياري"
                type="email"
                dir="ltr"
                className="text-left"
                autoComplete="email"
                error={registerForm.formState.errors.email?.message}
              />

              <Input
                {...registerForm.register('password')}
                id="reg-password"
                label="كلمة المرور"
                placeholder="8 أحرف على الأقل"
                type="password"
                autoComplete="new-password"
                error={registerForm.formState.errors.password?.message}
                required
              />

              <Input
                {...registerForm.register('password_confirmation')}
                id="reg-password-confirm"
                label="تأكيد كلمة المرور"
                placeholder="أعد إدخال كلمة المرور"
                type="password"
                autoComplete="new-password"
                error={registerForm.formState.errors.password_confirmation?.message}
                required
              />

              <Input
                {...registerForm.register('farm_name')}
                id="reg-farm-name"
                label="اسم المزرعة"
                placeholder="اختياري"
                type="text"
                error={registerForm.formState.errors.farm_name?.message}
              />

              <Input
                {...registerForm.register('location')}
                id="reg-location"
                label="الموقع"
                placeholder="اختياري — المدينة أو المنطقة"
                type="text"
                error={registerForm.formState.errors.location?.message}
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
                loading={registerForm.formState.isSubmitting}
              >
                إرسال طلب الاشتراك
              </Button>

              <p className="text-center text-xs text-slate-400">
                سيتم مراجعة طلبك من الإدارة والتواصل معك عبر الواتساب
              </p>
            </form>
          )}

          {/* ── Register Success State ─────────────────────────────────── */}
          {mode === 'register' && registerSuccess && (
            <div className="py-6 text-center">
              <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-100">
                <svg
                  className="h-7 w-7 text-green-600"
                  fill="none"
                  viewBox="0 0 24 24"
                  strokeWidth={2}
                  stroke="currentColor"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M4.5 12.75l6 6 9-13.5"
                  />
                </svg>
              </div>
              <h3 className="mb-2 text-lg font-semibold text-slate-800">
                تم إرسال طلبك بنجاح
              </h3>
              <p className="mb-6 text-sm text-slate-500">
                ستتم مراجعة طلبك من الإدارة والتواصل معك قريباً عبر الواتساب
              </p>
              <Button
                variant="outline"
                onClick={() => switchMode('login')}
                className="w-full"
              >
                العودة لتسجيل الدخول
              </Button>
            </div>
          )}
        </div>

        <p className="mt-6 text-center text-xs text-slate-400">
          © {new Date().getFullYear()} دجاجتي — جميع الحقوق محفوظة
        </p>
      </div>
    </div>
  )
}

export default function LoginPage() {
  return (
    <Suspense fallback={
      <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-primary-50 to-slate-100">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary-200 border-t-primary-600" />
      </div>
    }>
      <LoginPageInner />
    </Suspense>
  )
}

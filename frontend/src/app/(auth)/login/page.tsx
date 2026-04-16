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
import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'
import { ShieldCheck, Sparkles, Server, Brain, Cpu } from 'lucide-react'

// ── Local Utils (Fallback for build issues) ──────────────────────────────────
function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

// ── Mode ──────────────────────────────────────────────────────────────────────
type AuthMode = 'login' | 'register'

// ── Zod Schemas ───────────────────────────────────────────────────────────────
const loginSchema = z.object({
  login: z.string().min(1, 'رقم الواتساب أو البريد الإلكتروني مطلوب'),
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
          ? 'bg-emerald-600 text-white shadow-md'
          : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 hover:bg-emerald-50 dark:hover:bg-slate-700'
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
    <div className="flex min-h-[100dvh] items-center justify-center bg-white dark:bg-slate-900 px-5 py-8" dir="rtl">
      <div className="w-full max-w-sm">
        {/* ── Card ──────────────────────────────────────────────── */}
        <div className="rounded-[1.75rem] border border-slate-200/80 dark:border-slate-700/60 bg-white dark:bg-slate-800 p-5 shadow-sm shadow-slate-200/40">
          {/* ── Brand Section (Now inside card) ─────────────────── */}
          <div className="flex flex-col items-center mb-5">
            {/* Logo */}
            <div className="mb-4">
              <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 shadow-xl shadow-emerald-200/50 transform transition-transform hover:scale-105 active:scale-95">
                <img
                  src="/logo.png"
                  alt="الياسين"
                  className="h-12 w-12 object-contain brightness-0 invert"
                />
              </div>
            </div>

            {/* Brand Name */}
            <h1 className="text-[1.75rem] font-bold tracking-tight text-emerald-950 dark:text-emerald-100 mb-1 leading-none text-center">
              الياسين
            </h1>

            {/* Subtitle with pulsing dot */}
            <div className="inline-flex items-center gap-2 px-3 py-1 bg-emerald-50/50 border border-emerald-100/50 rounded-full">
              <span className="relative flex h-2 w-2 justify-center items-center">
                <span className="animate-ping absolute inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500 opacity-75" />
                <span className="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-600" />
              </span>
              <span className="text-[10px] font-bold text-emerald-700 tracking-wide text-center">
                نظام إدارة مزارع الدواجن الذكي
              </span>
            </div>
          </div>

          <div className="h-px bg-slate-100/60 dark:bg-slate-700/60 mb-5" />

          {/* Tabs */}
          <div className="mb-4 flex gap-1 rounded-2xl bg-slate-100/80 dark:bg-slate-700/50 p-1">
            <TabButton active={mode === 'login'} onClick={() => switchMode('login')}>
              تسجيل الدخول
            </TabButton>
            <TabButton active={mode === 'register'} onClick={() => switchMode('register')}>
              حساب جديد
            </TabButton>
          </div>

          {/* ── Login ──────────────────────────────────────────── */}
          {mode === 'login' && (
            <form
              onSubmit={loginForm.handleSubmit(onLogin)}
              className="space-y-3.5"
              noValidate
            >
              <Input
                {...loginForm.register('login')}
                id="login"
                label="رقم الواتساب أو البريد الإلكتروني"
                placeholder="أدخل رقم الواتساب أو البريد الإلكتروني"
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
                <div className="rounded-xl bg-red-50 px-3.5 py-2.5 text-xs font-medium text-red-600 border border-red-100">
                  {serverError}
                </div>
              )}

              <Button
                type="submit"
                className="w-full !rounded-xl !h-11 !text-sm !font-bold touch-manipulation"
                size="lg"
                loading={loginForm.formState.isSubmitting}
              >
                دخول
              </Button>
            </form>
          )}

          {/* ── Register ───────────────────────────────────────── */}
          {mode === 'register' && !registerSuccess && (
            <form
              onSubmit={registerForm.handleSubmit(onRegister)}
              className="space-y-3"
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
                <div className="rounded-xl bg-red-50 px-3.5 py-2.5 text-xs font-medium text-red-600 border border-red-100">
                  {serverError}
                </div>
              )}

              <Button
                type="submit"
                className="w-full !rounded-xl !h-11 !text-sm !font-bold touch-manipulation"
                size="lg"
                loading={registerForm.formState.isSubmitting}
              >
                إرسال طلب الاشتراك
              </Button>

              <p className="text-center text-[10px] text-slate-400 leading-relaxed">
                سيتم مراجعة طلبك من الإدارة والتواصل معك عبر الواتساب
              </p>
            </form>
          )}

          {/* ── Register Success ────────────────────────────── */}
          {mode === 'register' && registerSuccess && (
            <div className="py-8 text-center">
              <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 border border-emerald-100">
                <svg
                  className="h-7 w-7 text-emerald-600"
                  fill="none"
                  viewBox="0 0 24 24"
                  strokeWidth={2.5}
                  stroke="currentColor"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M4.5 12.75l6 6 9-13.5"
                  />
                </svg>
              </div>
              <h3 className="mb-2 text-base font-bold text-slate-800 dark:text-slate-200">
                تم إرسال طلبك بنجاح
              </h3>
              <p className="mb-6 text-xs text-slate-500 leading-relaxed">
                ستتم مراجعة طلبك من الإدارة والتواصل معك قريباً عبر الواتساب
              </p>
              <Button
                variant="outline"
                onClick={() => switchMode('login')}
                className="w-full !rounded-xl"
              >
                العودة لتسجيل الدخول
              </Button>
            </div>
          )}

          {/* ── Trust Badges ───────────────────────────────────── */}
          <div className="mt-5 pt-4 border-t border-slate-100/60 dark:border-slate-700/60 flex items-center justify-between px-2">
            <div className="flex flex-col items-center gap-2 flex-1 relative">
              <div className="flex h-9 w-9 items-center justify-center rounded-2xl bg-emerald-50/80 text-emerald-600 ring-1 ring-emerald-100/50 shadow-sm shadow-emerald-100/20">
                <ShieldCheck className="h-[20px] w-[20px]" strokeWidth={2} />
              </div>
              <span className="text-[9px] font-bold text-slate-500">حماية فائقة</span>
            </div>

            <div className="flex flex-col items-center gap-2 flex-1 relative">
              {/* Subtle divider */}
              <div className="absolute top-3 -right-2 h-5 w-px bg-slate-200/60" />
              <div className="flex h-9 w-9 items-center justify-center rounded-2xl bg-emerald-50/80 text-emerald-600 ring-1 ring-emerald-100/50 shadow-sm shadow-emerald-100/20">
                <Cpu className="h-[20px] w-[20px]" strokeWidth={2} />
              </div>
              <span className="text-[9px] font-bold text-slate-500">تحليل ذكي</span>
            </div>

            <div className="flex flex-col items-center gap-2 flex-1 relative">
              {/* Subtle divider */}
              <div className="absolute top-3 -right-2 h-5 w-px bg-slate-200/60" />
              <div className="flex h-9 w-9 items-center justify-center rounded-2xl bg-emerald-50/80 text-emerald-600 ring-1 ring-emerald-100/50 shadow-sm shadow-emerald-100/20">
                <Server className="h-[20px] w-[20px]" strokeWidth={2} />
              </div>
              <span className="text-[9px] font-bold text-slate-500">مزامنة سحابية</span>
            </div>
          </div>
        </div>

        {/* ── Footer ─────────────────────────────────────────── */}
        <div className="mt-5 flex justify-center">
          <p className="text-[10px] font-medium text-slate-400">
            © {new Date().getFullYear()} نظام الياسين — جميع الحقوق محفوظة
          </p>
        </div>
      </div>
    </div>
  )
}

export default function LoginPage() {
  return (
    <Suspense fallback={
      <div className="flex min-h-[100dvh] items-center justify-center bg-white">
        <div className="h-6 w-6 animate-spin rounded-full border-2 border-slate-200 border-t-emerald-500" />
      </div>
    }>
      <LoginPageInner />
    </Suspense>
  )
}

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
        'flex-1 rounded-xl py-2.5 px-4 text-xs font-semibold transition-all duration-200',
        active
          ? 'bg-[#1a1c21] text-white shadow-[0_2px_10px_rgba(0,0,0,0.5)] ring-1 ring-white/10'
          : 'text-slate-500 hover:text-slate-300 hover:bg-white/[0.02]'
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
          partner_id: f.partner_id,
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
    <div className="flex min-h-[100dvh] items-center justify-center bg-[#0b1224] px-5 py-8 relative overflow-hidden" dir="rtl">
      {/* ── Background Branding Glow (Minimalist) ───────────────── */}
      <div 
        className="absolute inset-x-0 -top-40 h-[100dvh] pointer-events-none opacity-10"
        style={{ background: 'radial-gradient(circle at 50% 0%, #10b981 0%, transparent 60%)' }}
      />
      
      <div className="w-full max-w-sm relative z-10">
        {/* ── Card ──────────────────────────────────────────────── */}
        <div className="rounded-[2.25rem] border border-white/[0.05] bg-[#14161b] p-6 shadow-[0_32px_64px_-16px_rgba(0,0,0,0.8)] dark ring-1 ring-white/[0.01]">
          {/* ── Brand Section (Now inside card) ─────────────────── */}
          <div className="flex flex-col items-center mb-4">
            {/* Logo */}
            <div className="mb-4 relative flex items-center justify-center">
              {/* Breathing ambient glow behind */}
              <div
                className="absolute inset-[-8px] rounded-[2.8rem] bg-emerald-500/20 blur-2xl pointer-events-none"
                style={{ animation: 'logo-glow-pulse 3s ease-in-out infinite' }}
              />

              {/* Spinning gradient border container */}
              <div className="relative h-28 w-28">
                {/* The spinning conic gradient — creates the "laser ring" border */}
                <div className="absolute inset-[-2px] rounded-[2.4rem] overflow-hidden pointer-events-none">
                  <div
                    className="absolute animate-spin"
                    style={{
                      inset: '-75%',
                      background: 'conic-gradient(from 0deg, transparent 0deg, transparent 120deg, rgba(16,185,129,0.95) 150deg, rgba(255,255,255,0.85) 165deg, rgba(16,185,129,0.95) 180deg, transparent 210deg, transparent 360deg)',
                      animationDuration: '4s',
                      animationTimingFunction: 'linear',
                    }}
                  />
                </div>

                {/* Logo box on top */}
                <div
                  className="relative flex h-full w-full items-center justify-center rounded-[2.25rem] overflow-hidden transform transition-all duration-300 hover:scale-105 active:scale-95"
                  style={{
                    background: 'linear-gradient(145deg, #1e2129 0%, #12141a 100%)',
                    boxShadow: '0 20px 50px rgba(0,0,0,0.9)',
                  }}
                >
                  {/* Glass top highlight */}
                  <div className="absolute inset-x-0 top-0 h-1/2 bg-gradient-to-b from-white/[0.08] to-transparent pointer-events-none rounded-t-[2.25rem]" />
                  <img
                    src="/ymd-logo.png?v=7"
                    alt="YMD"
                    className="h-24 w-24 object-contain relative z-10"
                  />
                </div>
              </div>
            </div>

            {/* Brand Name */}
            <h1 className="text-[2.25rem] font-bold tracking-tight text-white mb-0.5 leading-none text-center">
              YMD
            </h1>

            {/* Subtitle Badge (Minimalist) */}
            <div className="inline-flex items-center gap-1.5 px-3 py-1 bg-black/30 border border-white/[0.06] rounded-full">
              <span className="flex h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]" />
              <span className="text-[10px] font-bold text-slate-400 tracking-[0.1em] text-center uppercase">
                Yasin Modern Digital
              </span>
            </div>
          </div>

          <div className="h-px bg-white/[0.04] mb-5" />

          {/* Tabs - Apple Style (Matte) */}
          <div className="mb-5 flex gap-1 rounded-xl bg-black/60 p-1 border border-white/[0.04]">
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
              className="space-y-3"
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
                className="w-full !rounded-[1.15rem] !h-12 !text-sm !font-bold touch-manipulation bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-400 text-white shadow-lg shadow-emerald-900/40 border-t border-white/20 transition-all duration-200 active:scale-[0.98]"
                size="lg"
                loading={loginForm.formState.isSubmitting}
              >
                دخول للنظام
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
              <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 border border-primary-100">
                <svg
                  className="h-7 w-7 text-primary-600"
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
          <div className="mt-5 pt-4 border-t border-white/[0.04] flex items-center justify-between px-2">
            {/* Shield */}
            <div className="flex flex-col items-center gap-2 flex-1 relative group cursor-default">
              <div className="relative flex items-center justify-center">
                {/* Pulsing ring */}
                <div
                  className="absolute inset-0 rounded-xl bg-emerald-500/30"
                  style={{ animation: 'icon-ring-pulse 2.5s ease-out 0.0s infinite' }}
                />
                <div
                  className="relative flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/40 transition-all duration-300 group-hover:scale-110 group-hover:bg-emerald-500/20"
                  style={{ boxShadow: '0 0 20px rgba(16,185,129,0.28), inset 0 1px 0 rgba(255,255,255,0.07)' }}
                >
                  <div className="absolute inset-x-0 top-0 h-1/2 bg-gradient-to-b from-white/[0.07] to-transparent rounded-t-xl pointer-events-none" />
                  <ShieldCheck className="h-5 w-5 relative z-10" strokeWidth={1.75} />
                </div>
              </div>
              <span className="text-[10px] font-bold text-slate-400 group-hover:text-emerald-400 transition-colors">حماية فائقة</span>
            </div>

            {/* CPU */}
            <div className="flex flex-col items-center gap-2 flex-1 relative group cursor-default">
              <div className="relative flex items-center justify-center">
                <div
                  className="absolute inset-0 rounded-xl bg-blue-500/30"
                  style={{ animation: 'icon-ring-pulse 2.5s ease-out 0.8s infinite' }}
                />
                <div
                  className="relative flex h-12 w-12 items-center justify-center rounded-xl bg-blue-500/10 text-blue-400 border border-blue-500/40 transition-all duration-300 group-hover:scale-110 group-hover:bg-blue-500/20"
                  style={{ boxShadow: '0 0 20px rgba(59,130,246,0.28), inset 0 1px 0 rgba(255,255,255,0.07)' }}
                >
                  <div className="absolute inset-x-0 top-0 h-1/2 bg-gradient-to-b from-white/[0.07] to-transparent rounded-t-xl pointer-events-none" />
                  <Cpu className="h-5 w-5 relative z-10" strokeWidth={1.75} />
                </div>
              </div>
              <span className="text-[10px] font-bold text-slate-400 group-hover:text-blue-400 transition-colors">تحليل ذكي</span>
            </div>

            {/* Server */}
            <div className="flex flex-col items-center gap-2 flex-1 relative group cursor-default">
              <div className="relative flex items-center justify-center">
                <div
                  className="absolute inset-0 rounded-xl bg-cyan-500/30"
                  style={{ animation: 'icon-ring-pulse 2.5s ease-out 1.6s infinite' }}
                />
                <div
                  className="relative flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/40 transition-all duration-300 group-hover:scale-110 group-hover:bg-cyan-500/20"
                  style={{ boxShadow: '0 0 20px rgba(6,182,212,0.28), inset 0 1px 0 rgba(255,255,255,0.07)' }}
                >
                  <div className="absolute inset-x-0 top-0 h-1/2 bg-gradient-to-b from-white/[0.07] to-transparent rounded-t-xl pointer-events-none" />
                  <Server className="h-5 w-5 relative z-10" strokeWidth={1.75} />
                </div>
              </div>
              <span className="text-[10px] font-bold text-slate-400 group-hover:text-cyan-400 transition-colors">مزامنة سحابية</span>
            </div>
          </div>
        </div>

        {/* ── Footer ─────────────────────────────────────────── */}
        <div className="mt-4 flex justify-center">
          <p className="text-[9px] font-medium text-slate-500">
            © {new Date().getFullYear()} YMD System — جميع الحقوق محفوظة
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
        <div className="h-6 w-6 animate-spin rounded-full border-2 border-slate-200 border-t-primary-500" />
      </div>
    }>
      <LoginPageInner />
    </Suspense>
  )
}



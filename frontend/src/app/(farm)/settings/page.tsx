'use client'

import { useEffect, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Camera, CheckCircle2, Eye, EyeOff, Lock, Moon, Sun, User } from 'lucide-react'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth.store'
import { useThemeStore } from '@/stores/theme.store'
import { profileApi } from '@/lib/api/profile'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { useLayoutStore } from '@/stores/layout.store'

// ── Schemas ───────────────────────────────────────────────────────────────────
const profileSchema = z.object({
  name:     z.string().min(1, 'الاسم مطلوب').max(150),
  email:    z.string().email('صيغة البريد غير صحيحة').max(190).optional().or(z.literal('')),
  whatsapp: z.string().max(30).optional().or(z.literal('')),
})
type ProfileForm = z.infer<typeof profileSchema>

const passwordSchema = z
  .object({
    current_password:      z.string().min(1, 'كلمة المرور الحالية مطلوبة'),
    password:              z.string().min(8, 'كلمة المرور الجديدة 8 أحرف على الأقل'),
    password_confirmation: z.string().min(1, 'تأكيد كلمة المرور مطلوب'),
  })
  .refine((d) => d.password === d.password_confirmation, {
    message: 'كلمة المرور وتأكيدها غير متطابقين',
    path: ['password_confirmation'],
  })
type PasswordForm = z.infer<typeof passwordSchema>

// ── Helpers ───────────────────────────────────────────────────────────────────
function getInitials(name: string) {
  return name.trim().charAt(0).toUpperCase()
}

function SectionCard({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="rounded-2xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-800/60 overflow-hidden">
      <div className="px-5 py-4 border-b border-slate-100 dark:border-slate-700">
        <h2 className="text-sm font-bold text-slate-700 dark:text-slate-200">{title}</h2>
      </div>
      <div className="p-5">{children}</div>
    </div>
  )
}

// ── Main Page ─────────────────────────────────────────────────────────────────
export default function SettingsPage() {
  const { user, setUser } = useAuthStore()
  const { theme, setTheme } = useThemeStore()
  const { setPageTitle, setPageSubtitle } = useLayoutStore()

  const fileInputRef = useRef<HTMLInputElement>(null)
  const [avatarPreview, setAvatarPreview] = useState<string | null>(null)
  const [avatarUploading, setAvatarUploading] = useState(false)

  const [showCurrent, setShowCurrent] = useState(false)
  const [showNew, setShowNew]         = useState(false)
  const [showConfirm, setShowConfirm] = useState(false)

  useEffect(() => {
    setPageTitle('إعدادات الحساب')
    setPageSubtitle(null)
  }, [setPageTitle, setPageSubtitle])

  // ── Profile form ──────────────────────────────────────────────────────────
  const {
    register: regProfile,
    handleSubmit: handleProfile,
    formState: { errors: profileErrors, isSubmitting: profileSaving },
  } = useForm<ProfileForm>({
    resolver: zodResolver(profileSchema),
    defaultValues: {
      name:     user?.name ?? '',
      email:    user?.email ?? '',
      whatsapp: user?.whatsapp ?? '',
    },
  })

  const onProfileSubmit = async (data: ProfileForm) => {
    try {
      const res = await profileApi.updateProfile({
        name:     data.name,
        email:    data.email || null,
        whatsapp: data.whatsapp || null,
      })
      setUser(res.data)
      toast.success('تم تحديث البيانات الشخصية')
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })
        ?.response?.data?.message ?? 'حدث خطأ غير متوقع'
      toast.error(msg)
    }
  }

  // ── Password form ─────────────────────────────────────────────────────────
  const {
    register: regPw,
    handleSubmit: handlePw,
    reset: resetPw,
    formState: { errors: pwErrors, isSubmitting: pwSaving },
  } = useForm<PasswordForm>({ resolver: zodResolver(passwordSchema) })

  const onPasswordSubmit = async (data: PasswordForm) => {
    try {
      await profileApi.changePassword(data)
      toast.success('تم تغيير كلمة المرور بنجاح')
      resetPw()
    } catch (err: unknown) {
      const axErr = err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } }
      const first = axErr?.response?.data?.errors
        ? Object.values(axErr.response.data.errors)[0]?.[0]
        : null
      toast.error(first ?? axErr?.response?.data?.message ?? 'حدث خطأ غير متوقع')
    }
  }

  // ── Avatar upload ─────────────────────────────────────────────────────────
  const handleAvatarChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return

    // Preview
    const reader = new FileReader()
    reader.onload = (ev) => setAvatarPreview(ev.target?.result as string)
    reader.readAsDataURL(file)

    setAvatarUploading(true)
    try {
      const res = await profileApi.uploadAvatar(file)
      if (user) {
        setUser({ ...user, avatar_path: res.avatar_path, avatar_url: res.avatar_url })
      }
      toast.success('تم تحديث الصورة الشخصية')
    } catch {
      toast.error('فشل رفع الصورة، حاول مجدداً')
      setAvatarPreview(null)
    } finally {
      setAvatarUploading(false)
      if (fileInputRef.current) fileInputRef.current.value = ''
    }
  }

  const currentAvatar = avatarPreview ?? user?.avatar_url ?? null

  // ── Render ────────────────────────────────────────────────────────────────
  return (
    <div className="px-4 py-5 space-y-4 max-w-2xl mx-auto">

      {/* ── 1. Avatar + Profile ─────────────────────────────────────────── */}
      <SectionCard title="الصورة والبيانات الشخصية">

        {/* Avatar */}
        <div className="flex flex-col items-center mb-6">
          <div className="relative group">
            <div className="h-24 w-24 rounded-full overflow-hidden border-4 border-white dark:border-slate-700 shadow-lg">
              {currentAvatar ? (
                <img
                  src={currentAvatar}
                  alt="صورة الحساب"
                  className="h-full w-full object-cover"
                />
              ) : (
                <div className="h-full w-full bg-emerald-100 dark:bg-emerald-900 flex items-center justify-center">
                  <span className="text-3xl font-black text-emerald-700 dark:text-emerald-300">
                    {user?.name ? getInitials(user.name) : <User className="h-8 w-8" />}
                  </span>
                </div>
              )}
            </div>

            {/* Upload overlay */}
            <button
              type="button"
              onClick={() => fileInputRef.current?.click()}
              disabled={avatarUploading}
              className="absolute inset-0 flex flex-col items-center justify-center rounded-full bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-200 disabled:opacity-70 cursor-pointer"
            >
              {avatarUploading ? (
                <div className="h-5 w-5 rounded-full border-2 border-white border-t-transparent animate-spin" />
              ) : (
                <>
                  <Camera className="h-5 w-5 text-white" />
                  <span className="text-[10px] font-bold text-white mt-0.5">تغيير</span>
                </>
              )}
            </button>
          </div>

          <input
            ref={fileInputRef}
            type="file"
            accept="image/jpeg,image/jpg,image/png,image/webp"
            className="hidden"
            onChange={handleAvatarChange}
          />

          <p className="mt-2 text-xs text-slate-400 dark:text-slate-500">
            اضغط على الصورة لتغييرها · الحجم الأقصى 2MB
          </p>
        </div>

        {/* Profile form */}
        <form onSubmit={handleProfile(onProfileSubmit)} className="space-y-3" noValidate>
          <Input
            {...regProfile('name')}
            id="settings_name"
            label="الاسم الكامل"
            placeholder="أدخل اسمك"
            error={profileErrors.name?.message}
            required
          />

          <Input
            {...regProfile('email')}
            id="settings_email"
            label="البريد الإلكتروني"
            type="email"
            placeholder="example@email.com"
            error={profileErrors.email?.message}
          />

          <Input
            {...regProfile('whatsapp')}
            id="settings_whatsapp"
            label="رقم الواتساب"
            type="tel"
            placeholder="05xxxxxxxx"
            error={profileErrors.whatsapp?.message}
          />

          <div className="flex justify-end pt-1">
            <Button type="submit" size="sm" loading={profileSaving}>
              <CheckCircle2 className="me-1.5 h-4 w-4" />
              حفظ البيانات
            </Button>
          </div>
        </form>
      </SectionCard>

      {/* ── 2. Password ─────────────────────────────────────────────────── */}
      <SectionCard title="تغيير كلمة المرور">
        <form onSubmit={handlePw(onPasswordSubmit)} className="space-y-3" noValidate>

          {/* Current password */}
          <div className="space-y-1">
            <label htmlFor="cur_pw" className="text-sm font-medium text-slate-700 dark:text-slate-300">
              كلمة المرور الحالية <span className="text-red-500">*</span>
            </label>
            <div className="relative">
              <input
                {...regPw('current_password')}
                id="cur_pw"
                type={showCurrent ? 'text' : 'password'}
                placeholder="••••••••"
                className="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
              />
              <button
                type="button"
                onClick={() => setShowCurrent((v) => !v)}
                className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
              >
                {showCurrent ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
              </button>
            </div>
            {pwErrors.current_password && (
              <p className="text-xs text-red-500">{pwErrors.current_password.message}</p>
            )}
          </div>

          {/* New password */}
          <div className="space-y-1">
            <label htmlFor="new_pw" className="text-sm font-medium text-slate-700 dark:text-slate-300">
              كلمة المرور الجديدة <span className="text-red-500">*</span>
            </label>
            <div className="relative">
              <input
                {...regPw('password')}
                id="new_pw"
                type={showNew ? 'text' : 'password'}
                placeholder="8 أحرف على الأقل"
                className="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
              />
              <button
                type="button"
                onClick={() => setShowNew((v) => !v)}
                className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
              >
                {showNew ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
              </button>
            </div>
            {pwErrors.password && (
              <p className="text-xs text-red-500">{pwErrors.password.message}</p>
            )}
          </div>

          {/* Confirm password */}
          <div className="space-y-1">
            <label htmlFor="confirm_pw" className="text-sm font-medium text-slate-700 dark:text-slate-300">
              تأكيد كلمة المرور <span className="text-red-500">*</span>
            </label>
            <div className="relative">
              <input
                {...regPw('password_confirmation')}
                id="confirm_pw"
                type={showConfirm ? 'text' : 'password'}
                placeholder="أعد كتابة كلمة المرور"
                className="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
              />
              <button
                type="button"
                onClick={() => setShowConfirm((v) => !v)}
                className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
              >
                {showConfirm ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
              </button>
            </div>
            {pwErrors.password_confirmation && (
              <p className="text-xs text-red-500">{pwErrors.password_confirmation.message}</p>
            )}
          </div>

          <div className="flex justify-end pt-1">
            <Button type="submit" size="sm" loading={pwSaving}>
              <Lock className="me-1.5 h-4 w-4" />
              تغيير كلمة المرور
            </Button>
          </div>
        </form>
      </SectionCard>

      {/* ── 3. Theme ─────────────────────────────────────────────────────── */}
      <SectionCard title="المظهر">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="h-10 w-10 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
              {theme === 'dark'
                ? <Moon className="h-5 w-5 text-indigo-400" />
                : <Sun className="h-5 w-5 text-amber-500" />
              }
            </div>
            <div>
              <p className="text-sm font-bold text-slate-800 dark:text-slate-100">
                {theme === 'dark' ? 'الوضع الليلي' : 'الوضع النهاري'}
              </p>
              <p className="text-xs text-slate-400 dark:text-slate-500 mt-0.5">
                {theme === 'dark' ? 'الشاشة داكنة — أريح للعين ليلاً' : 'الشاشة فاتحة — مناسب للنهار'}
              </p>
            </div>
          </div>

          {/* Toggle switch */}
          <button
            type="button"
            onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
            className={`relative inline-flex h-7 w-13 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-300 focus:outline-none ${
              theme === 'dark' ? 'bg-indigo-500' : 'bg-slate-200'
            }`}
            role="switch"
            aria-checked={theme === 'dark'}
          >
            <span
              className={`pointer-events-none inline-block h-6 w-6 rounded-full bg-white shadow-lg transform transition-transform duration-300 ${
                theme === 'dark' ? '-translate-x-6' : 'translate-x-0'
              }`}
            />
          </button>
        </div>
      </SectionCard>

    </div>
  )
}

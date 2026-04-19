'use client'

import { useRouter } from 'next/navigation'
import { Settings, LogOut } from 'lucide-react'
import { useAuthStore } from '@/stores/auth.store'
import { useFarmStore } from '@/stores/farm.store'
import { apiClient } from '@/lib/api/client'

export default function UnauthorizedPage() {
  const router = useRouter()
  const { logout } = useAuthStore()
  const { clearFarm } = useFarmStore()

  const handleLogout = async () => {
    try {
      await apiClient.post('/auth/logout')
    } catch {
      // ignore
    } finally {
      logout()
      clearFarm()
      router.replace('/login')
    }
  }

  return (
    <div className="flex flex-col items-center justify-center py-24 text-center px-6" dir="rtl">
      <div className="mb-4 text-6xl">🚫</div>
      <h1 className="mb-2 text-2xl font-bold text-slate-900">غير مصرح لك بالوصول</h1>
      <p className="mb-8 max-w-sm text-sm text-slate-500">
        ليس لديك صلاحية لعرض هذه الصفحة. إذا كنت تعتقد أن هذا خطأ،
        يرجى التواصل مع مدير المزرعة.
      </p>

      <div className="flex flex-col gap-3 w-full max-w-xs">
        <button
          onClick={() => router.push('/settings')}
          className="flex items-center justify-center gap-2 rounded-xl bg-slate-100 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-200"
        >
          <Settings className="h-4 w-4" />
          الإعدادات
        </button>

        <button
          onClick={handleLogout}
          className="flex items-center justify-center gap-2 rounded-xl bg-red-50 px-5 py-3 text-sm font-semibold text-red-600 transition hover:bg-red-100"
        >
          <LogOut className="h-4 w-4" />
          تسجيل الخروج
        </button>
      </div>
    </div>
  )
}

'use client'

import { FarmSelector } from './FarmSelector'
import { useAuthStore } from '@/stores/auth.store'
import { useRouter } from 'next/navigation'
import { LogOut, User } from 'lucide-react'
import { apiClient } from '@/lib/api/client'

export function Header() {
  const { user, logout } = useAuthStore()
  const router = useRouter()

  const handleLogout = async () => {
    try {
      await apiClient.post('/auth/logout')
    } catch {
      // ignore — logout locally regardless
    } finally {
      logout()
      router.replace('/login')
    }
  }

  return (
    <header className="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-6">
      {/* Farm selector — RTL: appears on the right */}
      <FarmSelector />

      {/* User menu — RTL: appears on the left */}
      <div className="flex items-center gap-3">
        <div className="flex items-center gap-2 text-sm text-slate-600">
          <User className="h-4 w-4" />
          <span className="max-w-[120px] truncate font-medium">{user?.name}</span>
        </div>
        <button
          onClick={handleLogout}
          className="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm text-slate-500 transition hover:bg-red-50 hover:text-red-600"
          title="تسجيل الخروج"
        >
          <LogOut className="h-4 w-4" />
          <span>خروج</span>
        </button>
      </div>
    </header>
  )
}

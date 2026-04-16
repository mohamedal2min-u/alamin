'use client'

import { FarmSelector } from './FarmSelector'
import { useAuthStore } from '@/stores/auth.store'
import { useRouter } from 'next/navigation'
import Link from 'next/link'
import { LogOut, User, RefreshCcw } from 'lucide-react'
import { apiClient } from '@/lib/api/client'
import { ChickenLogo } from './ChickenLogo'
import { LiveStatus } from './LiveStatus'
import { useLayoutStore } from '@/stores/layout.store'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { cn } from '@/lib/utils'

export function Header() {
  const { user, logout } = useAuthStore()
  const { pageTitle, pageSubtitle } = useLayoutStore()
  const queryClient = useQueryClient()
  const router = useRouter()
  const [isRefreshing, setIsRefreshing] = useState(false)

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

  const handleGlobalRefresh = async () => {
    setIsRefreshing(true)
    try {
      await queryClient.invalidateQueries()
    } finally {
      setTimeout(() => setIsRefreshing(false), 500)
    }
  }

  return (
    <header
      className="sticky top-0 z-50 w-full flex items-center justify-between border-b border-emerald-800/80 bg-emerald-950 px-4 sm:px-6 backdrop-blur-md"
      style={{ height: 'var(--header-height)', boxShadow: '0 1px 3px rgba(0,0,0,0.2)' }}
    >
      {/* Brand & Dynamic Title */}
      <div className="flex items-center gap-3 sm:gap-5">
        <div className="flex items-center gap-2.5">
          <div className="flex h-10 w-10 items-center justify-center rounded-[1.25rem] bg-gradient-to-br from-emerald-400 to-emerald-600 text-white shrink-0 shadow-lg shadow-emerald-950/20 border border-emerald-400/30">
            <ChickenLogo className="h-6.5 w-6.5 brightness-0 invert" />
          </div>
          <div className="flex flex-col min-w-0 pr-0.5">
            <h1 className="text-lg sm:text-xl font-black tracking-tighter text-white leading-none truncate drop-shadow-sm">
              {pageTitle}
            </h1>
            {pageSubtitle && (
              <span className="text-[9px] font-black text-emerald-400 uppercase tracking-widest mt-0.5 truncate opacity-90">
                {pageSubtitle}
              </span>
            )}
          </div>
        </div>
        
        <div className="hidden md:block h-5 w-px bg-emerald-800/60" />
        
        <div className="hidden sm:flex items-center gap-2.5">
          <LiveStatus />
          <button
            onClick={handleGlobalRefresh}
            disabled={isRefreshing}
            className="group p-2 rounded-xl bg-emerald-900/50 border border-emerald-800/60 text-emerald-400 hover:text-white hover:bg-emerald-900 transition-colors duration-200 active:scale-95 disabled:opacity-50"
            title="تحديث البيانات"
          >
            <RefreshCcw className={cn("h-4 w-4", isRefreshing && "animate-spin")} />
          </button>
        </div>
      </div>

      {/* Actions */}
      <div className="flex items-center gap-2.5 sm:gap-3">
        {/* Farm selector in desktop header */}
        <div className="hidden lg:block bg-emerald-900/40 rounded-xl p-0.5 border border-emerald-800/60">
           <FarmSelector />
        </div>

        <div className="hidden sm:block h-5 w-px bg-emerald-800/60" />

        {/* User menu */}
        <div className="flex items-center gap-2 sm:gap-3">
          <Link
            href="/settings"
            className="flex items-center gap-2 rounded-xl bg-emerald-900/40 border border-emerald-800/60 pl-3 pr-1 py-1 transition-colors duration-200 hover:bg-emerald-900/60"
            title="إعدادات الحساب"
          >
            <div className="h-7 w-7 rounded-lg overflow-hidden bg-emerald-100 flex items-center justify-center text-emerald-900 font-bold text-xs shrink-0">
              {user?.avatar_url ? (
                <img src={user.avatar_url} alt="" className="h-full w-full object-cover" />
              ) : (
                user?.name?.charAt(0) || <User className="h-4 w-4" />
              )}
            </div>
            <span className="hidden md:block max-w-[100px] truncate text-xs font-semibold text-emerald-50 leading-none">
              {user?.name}
            </span>
          </Link>
          
          <button
            onClick={handleLogout}
            className="group flex items-center justify-center h-8 w-8 sm:w-auto sm:px-3 sm:h-8 rounded-xl bg-rose-500/10 border border-rose-500/20 text-xs font-bold text-rose-400 transition-colors duration-200 hover:bg-rose-500 hover:text-white hover:border-rose-500"
            title="تسجيل الخروج"
          >
            <LogOut className="h-3.5 w-3.5 sm:-mr-0.5 group-hover:-translate-x-0.5 transition-transform duration-200" />
            <span className="hidden sm:inline-block mr-1.5">خروج</span>
          </button>
        </div>
      </div>
    </header>
  )
}

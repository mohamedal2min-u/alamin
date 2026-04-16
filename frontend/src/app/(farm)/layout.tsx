'use client'

import { Sidebar } from '@/components/layout/Sidebar'
import { Header } from '@/components/layout/Header'
import { RoleGuard } from '@/components/layout/RoleGuard'
import { BottomNav } from '@/components/layout/BottomNav'
import { MoreMenu } from '@/components/layout/MoreMenu'
import { FlockGlobalHeader } from '@/components/layout/FlockGlobalHeader'
import { useFarmStore } from '@/stores/farm.store'
import { useLayoutStore } from '@/stores/layout.store'
import { useQueryClient } from '@tanstack/react-query'
import { useState} from 'react'
import { RefreshCcw } from 'lucide-react'
import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}
import { usePathname } from 'next/navigation'

export default function FarmLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname()
  const isAccountingOrOpsPage = 
    pathname?.endsWith('/dashboard') || 
    pathname?.endsWith('/worker') || 
    pathname?.includes('/expenses') || 
    pathname?.includes('/sales') ||
    pathname?.includes('/inventory')

  const currentFarm = useFarmStore((s) => s.currentFarm)
  const { pageTitle, pageSubtitle } = useLayoutStore()
  const queryClient = useQueryClient()
  const role = currentFarm?.role ?? null
  const isSuperAdmin = role === 'super_admin'
  const [isMoreMenuOpen, setIsMoreMenuOpen] = useState(false)
  const [isRefreshing, setIsRefreshing] = useState(false)

  const handleGlobalRefresh = async () => {
    setIsRefreshing(true)
    try {
      await queryClient.invalidateQueries()
    } finally {
      setTimeout(() => setIsRefreshing(false), 500)
    }
  }

  // ── Layout A: Super Admin (Classic Sidebar) ──────────────────────────────
  if (isSuperAdmin) {
    return (
      <div className="flex h-screen overflow-hidden bg-slate-100 dark:bg-slate-900">
        <Sidebar />
        <div className="flex flex-1 flex-col overflow-hidden">
          <Header />
          <main className="flex-1 overflow-y-auto p-5 thin-scrollbar">
            <RoleGuard>{children}</RoleGuard>
          </main>
        </div>
      </div>
    )
  }

  // ── Layout B: Regular Users (Mobile-First / Bottom Nav) ──────────────────
  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-900 pb-[72px]">
      {isAccountingOrOpsPage ? (
        <FlockGlobalHeader />
      ) : (
        <header className="sticky top-0 z-40 w-full bg-white/95 dark:bg-slate-900/95 backdrop-blur-lg border-b border-slate-100 dark:border-slate-700/60" dir="rtl">
          <div className="mx-auto flex max-w-2xl items-center justify-between px-5 min-h-[56px] py-2">
            {/* Page Title */}
            <div className="flex flex-col min-w-0">
              <h1 className="text-[17px] font-extrabold text-slate-900 dark:text-slate-100 leading-tight truncate tracking-tight">
                {pageTitle || 'الإعدادات'}
              </h1>
              {pageSubtitle && (
                <span className="text-[11px] font-semibold text-emerald-600 leading-none mt-0.5 truncate">
                  {pageSubtitle}
                </span>
              )}
            </div>

            {/* Right Actions */}
            <div className="flex items-center gap-2.5 shrink-0">
              <button
                onClick={handleGlobalRefresh}
                disabled={isRefreshing}
                className="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-400 dark:text-slate-500 active:scale-95 active:bg-slate-100 dark:active:bg-slate-700 transition-all duration-200 disabled:opacity-40"
                aria-label="تحديث البيانات"
              >
                <RefreshCcw className={cn("h-4 w-4", isRefreshing && "animate-spin")} />
              </button>

              <div className="flex items-center gap-2 bg-emerald-50 border border-emerald-100/60 px-2.5 py-1.5 rounded-lg">
                <div className="h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0" />
                <span className="text-[11px] font-bold text-emerald-700 truncate max-w-[90px]">
                  {currentFarm?.name}
                </span>
              </div>
            </div>
          </div>
        </header>
      )}

      {/* ─── Main Content ─── */}
      <div className="mx-auto max-w-2xl">
        <main>
          <RoleGuard>{children}</RoleGuard>
        </main>
      </div>

      {/* ─── Navigation Layer ─── */}
      <BottomNav onMoreClick={() => setIsMoreMenuOpen(true)} />
      <MoreMenu isOpen={isMoreMenuOpen} onClose={() => setIsMoreMenuOpen(false)} />
    </div>
  )
}

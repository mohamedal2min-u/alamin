'use client'

import { Sidebar } from '@/components/layout/Sidebar'
import { Header } from '@/components/layout/Header'
import { RoleGuard } from '@/components/layout/RoleGuard'
import { BottomNav } from '@/components/layout/BottomNav'
import { MoreMenu } from '@/components/layout/MoreMenu'
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
  const isWorkerPage = pathname?.endsWith('/worker')
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
      <div className="flex h-screen overflow-hidden bg-slate-100">
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
    <div className="min-h-screen bg-white pb-[72px]">
      {/* ─── Clean White Header ─── */}
      {!isWorkerPage && (
        <header className="sticky top-0 z-40 w-full bg-white/95 backdrop-blur-lg border-b border-slate-100">
          <div className="mx-auto flex max-w-2xl items-center justify-between px-5 h-[52px]">
            {/* Page Title */}
            <div className="flex flex-col min-w-0">
              <h1 className="text-[17px] font-extrabold text-slate-900 leading-tight truncate tracking-tight">
                {pageTitle}
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
                className="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-50 text-slate-400 active:scale-95 active:bg-slate-100 transition-all duration-200 disabled:opacity-40"
                aria-label="تحديث البيانات"
              >
                <RefreshCcw className={cn("h-[18px] w-[18px]", isRefreshing && "animate-spin")} />
              </button>

              <div className="flex items-center gap-1.5 rounded-xl bg-emerald-50 border border-emerald-100/60 px-3 py-1.5">
                <div className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
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

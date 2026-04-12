'use client'

import { Sidebar } from '@/components/layout/Sidebar'
import { Header } from '@/components/layout/Header'
import { RoleGuard } from '@/components/layout/RoleGuard'
import { BottomNav } from '@/components/layout/BottomNav'
import { MoreMenu } from '@/components/layout/MoreMenu'
import { ChickenLogo } from '@/components/layout/ChickenLogo'
import { LiveStatus } from '@/components/layout/LiveStatus'
import { useFarmStore } from '@/stores/farm.store'
import { useLayoutStore } from '@/stores/layout.store'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { RefreshCcw } from 'lucide-react'
import { cn } from '@/lib/utils'

export default function FarmLayout({ children }: { children: React.ReactNode }) {
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
    <div className="min-h-screen bg-slate-100 pb-20">
      {/* Mobile Header */}
      <header
        className="sticky top-0 z-40 w-full flex items-center justify-between border-b border-emerald-800/80 bg-emerald-950 px-4 backdrop-blur-md"
        style={{ height: 'var(--header-height)', boxShadow: '0 1px 3px rgba(0,0,0,0.2)' }}
      >
        <div className="flex items-center gap-2.5 min-w-0">
          <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-white shrink-0">
            <ChickenLogo className="h-5 w-5" />
          </div>
          <div className="flex flex-col min-w-0">
            <h1 className="text-sm font-bold text-white leading-none truncate">{pageTitle}</h1>
            {pageSubtitle ? (
              <span className="text-[10px] font-semibold text-emerald-400 uppercase tracking-widest mt-0.5 truncate">{pageSubtitle}</span>
            ) : (
              <LiveStatus className="h-3.5 scale-75 origin-right -mt-0.5 border-none bg-transparent px-0" />
            )}
          </div>
        </div>
        
        <div className="flex items-center gap-2">
          <button
            onClick={handleGlobalRefresh}
            disabled={isRefreshing}
            className="p-2 rounded-lg bg-emerald-900/50 border border-emerald-800/60 text-emerald-400 active:scale-95 transition-colors duration-200 disabled:opacity-50"
          >
            <RefreshCcw className={cn("h-4 w-4", isRefreshing && "animate-spin")} />
          </button>
          
          <div className="text-[10px] font-bold text-emerald-100 bg-emerald-900/40 border border-emerald-800/60 px-3 py-1.5 rounded-xl truncate max-w-[120px] font-mono">
            {currentFarm?.name}
          </div>
        </div>
      </header>

      {/* Desktop Containerization */}
      <div className="mx-auto max-w-2xl bg-white min-h-screen border-x border-slate-200/40" style={{ boxShadow: 'var(--shadow-card)' }}>
        <main className="p-4">
          <RoleGuard>{children}</RoleGuard>
        </main>
      </div>

      {/* Navigation Layer */}
      <BottomNav onMoreClick={() => setIsMoreMenuOpen(true)} />
      <MoreMenu isOpen={isMoreMenuOpen} onClose={() => setIsMoreMenuOpen(false)} />
    </div>
  )
}

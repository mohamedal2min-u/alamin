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

  const isUnauthorized = pathname === '/unauthorized'

  // ── Layout B: Regular Users (Mobile-First / Bottom Nav) ──────────────────
  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-900 pb-[72px]">
      {!isUnauthorized && role && <FlockGlobalHeader />}

      {/* ─── Main Content ─── */}
      <div className="mx-auto max-w-2xl">
        <main>
          <RoleGuard>{children}</RoleGuard>
        </main>
      </div>

      {/* ─── Navigation Layer ─── */}
      {!isUnauthorized && role && (
        <>
          <BottomNav onMoreClick={() => setIsMoreMenuOpen(true)} />
          <MoreMenu isOpen={isMoreMenuOpen} onClose={() => setIsMoreMenuOpen(false)} />
        </>
      )}
    </div>
  )
}


// frontend/src/components/layout/RoleGuard.tsx
'use client'

import { useEffect, useState } from 'react'
import { usePathname, useRouter } from 'next/navigation'
import { useFarmStore } from '@/stores/farm.store'
import { canAccessRoute } from '@/lib/roles'
import type { FarmRole } from '@/types/auth'

/**
 * Client-side route guard that reads the current farm's role from Zustand and
 * redirects to /unauthorized if the role cannot access the current pathname.
 *
 * Renders children immediately on the first paint (before store hydration)
 * to avoid a flash of the unauthorized page on hard refresh.
 * Once the store is hydrated and the role is known, access is re-evaluated.
 */
export function RoleGuard({ children }: { children: React.ReactNode }) {
  const pathname = usePathname()
  const router   = useRouter()
  const [mounted, setMounted] = useState(false)

  const role: FarmRole | null = useFarmStore((s) => s.currentFarm?.role ?? null)

  // Wait for client mount so the Zustand store is hydrated from localStorage
  useEffect(() => {
    setMounted(true)
  }, [])

  useEffect(() => {
    if (!mounted || role === null) return
    if (!canAccessRoute(role, pathname)) {
      router.replace('/unauthorized')
    }
  }, [mounted, role, pathname, router])

  // Before mount: render children without restriction (store not yet hydrated)
  if (!mounted || role === null) return <>{children}</>

  // After mount: suppress content while redirect is in progress
  if (!canAccessRoute(role, pathname)) return null

  return <>{children}</>
}

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
 * Shows a blank screen while the store hydrates to prevent unauthorized content
 * from flashing before the role is known.
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

  // Before mount: show nothing until store hydration is complete
  if (!mounted || role === null) return null

  // After mount: suppress content while redirect is in progress
  if (!canAccessRoute(role, pathname)) return null

  return <>{children}</>
}

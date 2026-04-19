'use client'

import { useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { useFarmStore } from '@/stores/farm.store'
import { getDefaultRoute } from '@/lib/roles'

/**
 * Root "/" redirects smartly based on user role.
 * Workers go to /worker, Admins to /dashboard, etc.
 */
export default function RootPage() {
  const router = useRouter()
  const currentFarm = useFarmStore((s) => s.currentFarm)
  const role = currentFarm?.role ?? null

  useEffect(() => {
    // If the farm/role is loaded, redirect to the correct home
    if (role) {
      router.replace(getDefaultRoute(role))
    } else {
      // If we don't have a role yet (hydrating or not logged in),
      // we fallback to /flocks after a short timeout, 
      // which will then trigger /login via middleware/proxies if needed.
      const timer = setTimeout(() => {
        router.replace('/dashboard')
      }, 1000)
      return () => clearTimeout(timer)
    }
  }, [role, router])

  return (
    <div className="flex min-h-screen items-center justify-center bg-white dark:bg-slate-900">
      <div className="h-8 w-8 animate-spin rounded-full border-2 border-slate-200 border-t-primary-500" />
    </div>
  )
}


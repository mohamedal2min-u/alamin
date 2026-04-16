// frontend/src/lib/roles.ts

import { useFarmStore } from '@/stores/farm.store'
import type { FarmRole } from '@/types/auth'

// ── Re-export for convenience ─────────────────────────────────────────────────
export type { FarmRole }

// ── Nav hrefs visible to each role ────────────────────────────────────────────
// Keys must match the `href` values in ALL_NAV_ITEMS in Sidebar.tsx
export const NAV_HREFS_BY_ROLE: Record<FarmRole, readonly string[]> = {
  // super_admin: إدارة النظام فقط — لا يدخل في التشغيل اليومي للمزارع
  super_admin: ['/admin/farms', '/admin/registration-requests'],

  // farm_admin: كامل الصلاحيات على مزرعته
  farm_admin: ['/dashboard', '/flocks', '/inventory', '/sales', '/expenses', '/partners', '/workers', '/workers/new', '/reports'],

  worker:  ['/worker'],
  partner: ['/flocks', '/reports'],
}

// ── Route access guard ────────────────────────────────────────────────────────

/**
 * Returns true if the given role may navigate to the given pathname.
 *
 * super_admin: dashboard + /admin/* فقط — محجوب عن صفحات التشغيل اليومي
 * farm_admin:  كامل الوصول لمزرعته
 * worker:      الوردية (worker) فقط
 * partner:     الأفواج (قراءة) + التقارير فقط
 * /unauthorized متاحة دائماً
 */
export function canAccessRoute(role: FarmRole | null, pathname: string): boolean {
  if (!role) return true // Store not yet hydrated — defer to server enforcement

  if (pathname === '/unauthorized') return true

  // إعدادات الحساب متاحة لجميع الأدوار
  if (pathname === '/settings') return true

  if (role === 'super_admin') {
    if (pathname === '/dashboard') return true
    if (pathname.startsWith('/admin/')) return true
    return false
  }

  if (role === 'farm_admin') return true

  if (role === 'worker') {
    if (pathname === '/worker') return true
    return false
  }

  if (role === 'partner') {
    if (pathname === '/flocks') return true
    if (pathname.startsWith('/flocks/') && pathname !== '/flocks/new') return true
    if (pathname === '/reports' || pathname.startsWith('/reports/')) return true
    return false
  }

  return false
}

// ── Post-login default landing route ─────────────────────────────────────────

/**
 * super_admin يهبط على صفحة المداجن (نظرة عامة على كل المزارع).
 * worker يعود للوردية مباشرة.
 * بقية الأدوار تهبط على الأفواج.
 */
export function getDefaultRoute(role: FarmRole | null): string {
  if (role === 'super_admin') return '/admin/farms'
  if (role === 'worker') return '/worker'
  return '/flocks'
}

// ── Hooks ─────────────────────────────────────────────────────────────────────

/** Returns the role for the currently selected farm, or null if not loaded. */
export function useCurrentRole(): FarmRole | null {
  return useFarmStore((s) => s.currentFarm?.role ?? null)
}

/**
 * Returns true when the current user can only view data (partner role).
 * Use this hook in page components to hide create/edit/delete buttons.
 */
export function useIsReadOnly(): boolean {
  return useCurrentRole() === 'partner'
}

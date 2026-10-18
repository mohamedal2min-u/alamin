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
  farm_admin: ['/dashboard', '/flocks', '/inventory', '/sales', '/expenses', '/partners', '/workers', '/workers/new', '/reports', '/reports/flock-comparison', '/accounting', '/accounting/contacts', '/prayer'],

  // supervisor (مشرف): تشغيل المزرعة بلا أسعار ولا دفعات ولا إدارة حسابات أو شركاء؛ يرى الأسعار التي يضعها المحاسب
  supervisor: ['/dashboard', '/flocks', '/inventory', '/sales', '/expenses', '/reports', '/accounting', '/prayer'],

  // accountant: يحدد الأسعار فقط — صفحة المحاسبة وحدها
  accountant: ['/accounting', '/accounting/contacts', '/reports/flock-comparison', '/prayer'],
  partner: ['/dashboard', '/flocks', '/inventory', '/sales', '/expenses', '/reports', '/reports/flock-comparison', '/my-wallet', '/prayer'],
}

// ── Route access guard ────────────────────────────────────────────────────────

/**
 * Returns true if the given role may navigate to the given pathname.
 *
 * super_admin: dashboard + /admin/* فقط — محجوب عن صفحات التشغيل اليومي
 * farm_admin:  كامل الوصول لمزرعته
 * supervisor:  تشغيل المزرعة وقراءة المحاسبة؛ بلا شركاء ولا إدارة حسابات
 * accountant:  صفحة المحاسبة (الأسعار) فقط
 * partner:     الأفواج (قراءة) + التقارير فقط
 * /unauthorized متاحة دائماً
 */
export function canAccessRoute(role: FarmRole | null, pathname: string): boolean {
  if (!role) return true // Store not yet hydrated — defer to server enforcement

  if (pathname === '/unauthorized') return true

  // إعدادات الحساب متاحة لجميع الأدوار
  if (pathname === '/settings' || pathname === '/prayer') return true

  if (role === 'super_admin') {
    if (pathname === '/dashboard') return true
    if (pathname.startsWith('/admin/')) return true
    return false
  }

  if (role === 'farm_admin') return true

  if (role === 'supervisor') {
    // لا شركاء ولا إدارة حسابات ولا محفظة ولا مقارنة الأفواج (تقرير مالي)
    if (pathname === '/reports/flock-comparison') return false
    return !(pathname.startsWith('/partners') || pathname.startsWith('/workers') || pathname.startsWith('/my-wallet') || pathname.startsWith('/admin'))
  }

  if (role === 'accountant') {
    return pathname === '/accounting' || pathname.startsWith('/accounting/') || pathname === '/reports/flock-comparison'
  }

  if (role === 'partner') {
    if (pathname === '/dashboard') return true
    if (pathname === '/flocks') return true
    if (pathname.startsWith('/flocks/') && pathname !== '/flocks/new') return true
    if (pathname === '/inventory') return true
    if (pathname === '/sales') return true
    if (pathname === '/expenses') return true
    if (pathname === '/reports' || pathname.startsWith('/reports/')) return true
    if (pathname === '/my-wallet') return true
    return false
  }

  return false
}

// ── Post-login default landing route ─────────────────────────────────────────

/**
 * super_admin يهبط على صفحة المداجن (نظرة عامة على كل المزارع).
 * accountant يهبط على صفحة المحاسبة، و farm_admin على الصفحة الرئيسية (Dashboard).
 * partner يهبط على الأفواج.
 */
export function getDefaultRoute(role: FarmRole | null): string {
  if (role === 'super_admin') return '/admin/farms'
  if (role === 'accountant') return '/accounting'
  return '/dashboard'
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

/**
 * تحديد الأسعار للمحاسب فقط (ولمدير النظام). مدير المزرعة يضيف السجلات بلا أسعار —
 * الخادم يتجاهل حقول السعر منه، فنخفيها في النماذج.
 */
export function useCanSetPrices(): boolean {
  const role = useCurrentRole()
  return role === 'accountant' || role === 'super_admin'
}

/** تسجيل الدفعات والمستلم: المحاسب (ومدير النظام) فقط — لا مدير المزرعة ولا المشرف ولا الشريك. */
export function useCanRecordPayments(): boolean {
  const role = useCurrentRole()
  return role === 'accountant' || role === 'super_admin'
}

/** المشرف يرى صفحات المال (المحاسبة) للقراءة فقط: لا أسعار ولا دفعات ولا سجل تدقيق. */
export function useIsSupervisor(): boolean {
  return useCurrentRole() === 'supervisor'
}

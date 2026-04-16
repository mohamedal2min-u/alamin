// frontend/src/components/layout/Sidebar.tsx
'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { cn } from '@/lib/utils'
import {
  Lock, LayoutDashboard, Bird, Package, ShoppingCart,
  Receipt, Users, BarChart3, Building2, ClipboardList,
} from 'lucide-react'
import { useFarmStore } from '@/stores/farm.store'
import { ChickenLogo } from './ChickenLogo'
import { NAV_HREFS_BY_ROLE } from '@/lib/roles'
import type { FarmRole } from '@/types/auth'

// ── Nav items — merged list, filtered per role via NAV_HREFS_BY_ROLE ──────────
const ALL_NAV_ITEMS = [
  // مشتركة
  { label: 'لوحة التحكم',    href: '/dashboard',                     icon: LayoutDashboard },

  // إدارة النظام — super_admin فقط
  { label: 'المداجن',         href: '/admin/farms',                   icon: Building2 },
  { label: 'طلبات التسجيل',  href: '/admin/registration-requests',   icon: ClipboardList },

  // تشغيل المزرعة — farm_admin / worker / partner
  { label: 'الأفواج',        href: '/flocks',                        icon: Bird },
  { label: 'المخزون',        href: '/inventory',                     icon: Package },
  { label: 'المبيعات',       href: '/sales',                         icon: ShoppingCart },
  { label: 'المصروفات',      href: '/expenses',                      icon: Receipt },
  { label: 'الشركاء',        href: '/partners',                      icon: Users },
  { label: 'العمال',         href: '/workers',                       icon: Users },
  { label: 'التقارير',       href: '/reports',                       icon: BarChart3 },

  // الوردية للعامل فقط
  { label: 'الوردية',        href: '/worker',                        icon: ClipboardList },
]

export function Sidebar() {
  const pathname    = usePathname()
  const currentFarm = useFarmStore((s) => s.currentFarm)
  const role: FarmRole | null = currentFarm?.role ?? null

  // Filter nav items by role; show all if role not yet loaded
  const allowedHrefs = role ? NAV_HREFS_BY_ROLE[role] : ALL_NAV_ITEMS.map((i) => i.href)
  const navItems     = ALL_NAV_ITEMS.filter(({ href }) => allowedHrefs.includes(href))
  const isPartner    = role === 'partner'
  const isSuperAdmin = role === 'super_admin'

  return (
    <aside className="flex w-60 flex-col border-l border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800">

      {/* Brand */}
      <div className="flex items-center gap-2.5 border-b border-slate-200/60 dark:border-slate-700/60 px-5" style={{ height: 'var(--header-height)' }}>
        <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-600 text-white shrink-0 shadow-sm">
          <ChickenLogo className="h-4.5 w-4.5 brightness-0 invert" />
        </div>
        <span className="text-lg font-black tracking-tighter text-emerald-950 dark:text-emerald-100 leading-none">الياسين</span>
      </div>

      {/* Super admin badge */}
      {isSuperAdmin && (
        <div className="mx-3 mt-3 flex items-center gap-1.5 rounded-xl bg-primary-50 px-3 py-2 text-xs font-semibold text-primary-700 border border-primary-100">
          <Lock className="h-3.5 w-3.5 shrink-0" />
          <span>مدير النظام</span>
        </div>
      )}

      {/* Read-only badge — partner only */}
      {isPartner && (
        <div className="mx-3 mt-3 flex items-center gap-1.5 rounded-xl bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 border border-amber-100">
          <Lock className="h-3.5 w-3.5 shrink-0" />
          <span>وضع القراءة فقط</span>
        </div>
      )}

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto px-3 py-4 thin-scrollbar">
        <ul className="space-y-0.5">
          {navItems.map(({ label, href, icon: Icon }) => {
            const isActive = pathname === href || pathname.startsWith(`${href}/`)
            return (
              <li key={href}>
                <Link
                  href={href}
                  className={cn(
                    'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors duration-200',
                    isActive
                      ? 'bg-primary-50 text-primary-700 font-semibold'
                      : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-slate-100'
                  )}
                >
                  <Icon
                    className={cn(
                      'h-[18px] w-[18px]',
                      isActive ? 'text-primary-600' : 'text-slate-400'
                    )}
                  />
                  {label}
                </Link>
              </li>
            )
          })}
        </ul>
      </nav>
    </aside>
  )
}

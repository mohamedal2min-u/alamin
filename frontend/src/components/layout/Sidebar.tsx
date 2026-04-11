'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { cn } from '@/lib/utils'
import {
  LayoutDashboard,
  Bird,
  Package,
  ShoppingCart,
  Receipt,
  Users,
  BarChart3,
} from 'lucide-react'

const NAV_ITEMS = [
  { label: 'لوحة التحكم', href: '/dashboard',  icon: LayoutDashboard },
  { label: 'الأفواج',     href: '/flocks',      icon: Bird },
  { label: 'المخزون',     href: '/inventory',   icon: Package },
  { label: 'المبيعات',    href: '/sales',       icon: ShoppingCart },
  { label: 'المصروفات',   href: '/expenses',    icon: Receipt },
  { label: 'الشركاء',     href: '/partners',    icon: Users },
  { label: 'التقارير',    href: '/reports',     icon: BarChart3 },
]

export function Sidebar() {
  const pathname = usePathname()

  return (
    <aside className="flex w-60 flex-col border-l border-slate-200 bg-white">
      {/* Brand */}
      <div className="flex h-16 items-center gap-3 border-b border-slate-200 px-5">
        <span className="text-2xl">🐔</span>
        <span className="text-lg font-bold text-slate-900">دجاجاتي</span>
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto px-3 py-4">
        <ul className="space-y-1">
          {NAV_ITEMS.map(({ label, href, icon: Icon }) => {
            const isActive =
              pathname === href || pathname.startsWith(`${href}/`)
            return (
              <li key={href}>
                <Link
                  href={href}
                  className={cn(
                    'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                    isActive
                      ? 'bg-primary-50 text-primary-700'
                      : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                  )}
                >
                  <Icon
                    className={cn(
                      'h-5 w-5',
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

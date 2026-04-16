// frontend/src/components/layout/BottomNav.tsx
'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { cn } from '@/lib/utils'
import { 
  LayoutDashboard, Bird, Package, ClipboardList,
  MoreHorizontal
} from 'lucide-react'
import { useCurrentRole } from '@/lib/roles'
import { NAV_HREFS_BY_ROLE } from '@/lib/roles'

interface BottomNavProps {
  onMoreClick: () => void
}

export function BottomNav({ onMoreClick }: BottomNavProps) {
  const pathname = usePathname()
  const role = useCurrentRole()

  if (!role || role === 'super_admin') return null

  const allowedHrefs = NAV_HREFS_BY_ROLE[role]
  
  const navItems = [
    { label: 'الرئيسية', href: '/dashboard', icon: LayoutDashboard },
    { label: 'الوردية',  href: '/worker',    icon: ClipboardList },
    { label: 'الأفواج',  href: '/flocks',    icon: Bird },
    { label: 'المخزون',  href: '/inventory', icon: Package },
  ].filter(item => allowedHrefs.includes(item.href))

  return (
    <nav
      className="fixed bottom-0 left-0 right-0 z-50 bg-white/95 dark:bg-slate-900/95 backdrop-blur-lg border-t border-slate-100 dark:border-slate-700/60"
      style={{ paddingBottom: 'env(safe-area-inset-bottom, 4px)' }}
    >
      <div className="mx-auto flex max-w-2xl items-center justify-around px-4 h-[64px]">
        {navItems.map(({ label, href, icon: Icon }) => {
          const isActive = pathname === href || pathname.startsWith(`${href}/`)
          return (
            <Link
              key={href}
              href={href}
              className={cn(
                'relative flex flex-col items-center justify-center gap-1 min-w-[56px] py-1.5 transition-all duration-200 active:scale-95',
                isActive ? 'text-emerald-600' : 'text-slate-400'
              )}
            >
              <Icon 
                className={cn(
                  'h-6 w-6 transition-all duration-300', 
                  isActive && 'scale-110'
                )} 
                strokeWidth={isActive ? 2.5 : 1.8}
              />
              <span className={cn(
                'text-[10px] font-bold leading-none transition-colors duration-200',
                isActive ? 'text-emerald-700' : 'text-slate-400'
              )}>
                {label}
              </span>
              {/* Active Indicator Dot */}
              {isActive && (
                <span className="absolute -top-0.5 h-[3px] w-6 rounded-full bg-emerald-500 transition-all duration-300" />
              )}
            </Link>
          )
        })}

        {/* More Button */}
        <button
          onClick={onMoreClick}
          className="flex flex-col items-center justify-center gap-1 min-w-[56px] py-1.5 text-slate-400 transition-all duration-200 active:scale-95"
        >
          <MoreHorizontal className="h-6 w-6" strokeWidth={1.8} />
          <span className="text-[10px] font-bold leading-none">المزيد</span>
        </button>
      </div>
    </nav>
  )
}

// frontend/src/components/layout/BottomNav.tsx
'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { cn } from '@/lib/utils'
import { 
  LayoutDashboard, Bird, Package, Receipt, ClipboardList,
  Menu 
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
    { label: 'الرئيسية', href: '/dashboard', icon: LayoutDashboard, color: 'text-primary-600', activeBg: 'bg-primary-50' },
    { label: 'الوردية',  href: '/worker',    icon: ClipboardList,   color: 'text-emerald-500', activeBg: 'bg-emerald-50' },
    { label: 'الأفواج',  href: '/flocks',    icon: Bird,            color: 'text-orange-500',  activeBg: 'bg-orange-50' },
    { label: 'المخزون', href: '/inventory', icon: Package,         color: 'text-indigo-500',  activeBg: 'bg-indigo-50' },
    { label: 'المصروفات', href: '/expenses',  icon: Receipt,         color: 'text-red-500',     activeBg: 'bg-red-50' },
  ].filter(item => allowedHrefs.includes(item.href))

  return (
    <nav
      className="fixed bottom-0 left-0 right-0 z-50 border-t border-slate-200 bg-white"
      style={{ paddingBottom: 'env(safe-area-inset-bottom, 8px)' }}
    >
      <div className="mx-auto flex max-w-2xl items-center justify-around px-2 py-2.5">
        {navItems.map(({ label, href, icon: Icon, color, activeBg }) => {
          const isActive = pathname === href || pathname.startsWith(`${href}/`)
          return (
            <Link
              key={href}
              href={href}
              className={cn(
                'relative flex flex-col items-center gap-0.5 rounded-2xl px-3 py-2 transition-all duration-200 active:scale-95',
                isActive ? activeBg : 'text-slate-400 hover:text-slate-600'
              )}
            >
              <Icon 
                className={cn(
                  'h-[22px] w-[22px] transition-colors duration-200', 
                  isActive ? color : 'text-slate-400'
                )} 
              />
              <span className={cn(
                'text-[10px] font-bold leading-none transition-colors duration-200',
                isActive ? 'text-slate-900' : 'text-slate-400'
              )}>
                {label}
              </span>
              {isActive && (
                <span className={cn(
                  'absolute -bottom-0.5 h-[3px] w-5 rounded-full transition-all duration-300',
                  color.replace('text', 'bg')
                )} />
              )}
            </Link>
          )
        })}

        {/* More Button */}
        <button
          onClick={onMoreClick}
          className="flex flex-col items-center gap-0.5 rounded-2xl px-3 py-2 text-slate-400 transition-all duration-200 active:scale-95 hover:text-slate-600"
        >
          <Menu className="h-[22px] w-[22px]" />
          <span className="text-[10px] font-bold leading-none">المزيد</span>
        </button>
      </div>
    </nav>
  )
}

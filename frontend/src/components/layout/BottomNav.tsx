// frontend/src/components/layout/BottomNav.tsx
'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { cn } from '@/lib/utils'
import { Bird, Warehouse, ClipboardCheck, LayoutGrid } from 'lucide-react'
import { useCurrentRole, getDefaultRoute } from '@/lib/roles'
import { NAV_HREFS_BY_ROLE } from '@/lib/roles'

import { useAuthStore } from '@/stores/auth.store'

import { useScrollDirection } from '@/hooks/useScrollDirection'

interface BottomNavProps {
  onMoreClick: () => void
}

export function BottomNav({ onMoreClick }: BottomNavProps) {
  const pathname = usePathname()
  const role = useCurrentRole()
  const { user } = useAuthStore()
  const { scrollDirection, isAtTop } = useScrollDirection()

  if (!role || role === 'super_admin') return null

  const allowedHrefs = NAV_HREFS_BY_ROLE[role]
  const homeHref = getDefaultRoute(role)
  
  // Logic: Hide on scroll UP as requested, show on scroll DOWN or at TOP
  const shouldHide = scrollDirection === 'up' && !isAtTop

  const regularItems = [
    { label: 'الوردية',  href: '/worker',    icon: ClipboardCheck },
    { label: 'المخزون',  href: '/inventory', icon: Warehouse },
    { label: 'الأفواج',  href: '/flocks',    icon: Bird },
  ].filter(item => allowedHrefs.includes(item.href) && item.href !== homeHref)

  // أيقونة داخل كبسولة: عند التفعيل تظهر خلفية ناعمة والأيقونة ممتلئة قليلاً
  const iconPill = (Icon: React.ElementType, active: boolean) => (
    <span
      className={cn(
        'flex h-8 w-14 items-center justify-center rounded-full transition-all duration-300',
        active ? 'bg-primary-100 dark:bg-primary-900/40' : 'bg-transparent'
      )}
    >
      <Icon
        className={cn('h-[22px] w-[22px] transition-colors duration-200', active ? 'text-primary-700 dark:text-primary-300' : 'text-slate-500 dark:text-slate-400')}
        strokeWidth={active ? 2.2 : 1.7}
      />
    </span>
  )

  const renderItem = (item: { label: string, href: string, icon: React.ElementType }) => {
    const isActive = pathname === item.href || pathname.startsWith(`${item.href}/`)
    return (
      <Link
        key={item.href}
        href={item.href}
        aria-current={isActive ? 'page' : undefined}
        className="flex min-w-[64px] flex-col items-center justify-center gap-0.5 py-1.5 transition-transform duration-200 active:scale-95"
      >
        {iconPill(item.icon, isActive)}
        <span className={cn('text-[11px] leading-none transition-colors duration-200', isActive ? 'font-extrabold text-primary-700 dark:text-primary-300' : 'font-semibold text-slate-500 dark:text-slate-400')}>
          {item.label}
        </span>
      </Link>
    )
  }

  const renderMoreButton = () => (
    <button
      key="more-button"
      onClick={onMoreClick}
      className="flex min-w-[64px] flex-col items-center justify-center gap-0.5 py-1.5 transition-transform duration-200 active:scale-95"
    >
      {iconPill(LayoutGrid, false)}
      <span className="text-[11px] font-semibold leading-none text-slate-500 dark:text-slate-400">المزيد</span>
    </button>
  )

  const isHomeActive = pathname === homeHref
  const avatarUrl = user?.avatar_url || '/default-avatar.jpg'

  return (
    <nav
      className={cn(
        "fixed bottom-0 left-0 right-0 z-50 transition-all duration-500 ease-in-out",
        shouldHide ? "translate-y-full opacity-0" : "translate-y-0 opacity-100",
        "bg-white dark:bg-slate-900 border-t border-slate-100 dark:border-slate-800 shadow-[0_-8px_30px_rgba(0,0,0,0.08)] dark:shadow-[0_-8px_30px_rgba(0,0,0,0.4)]"
      )}
      style={{ paddingBottom: 'env(safe-area-inset-bottom, 4px)' }}
    >
      <div className="mx-auto flex max-w-2xl items-center justify-around px-2 sm:px-4 h-[78px]">
        {/* Home Avatar Button (First Position) - Dynamic Role-based Href */}
        <div className="flex flex-col items-center justify-center">
          <Link
            href={homeHref}
            className={cn(
              "flex h-[60px] w-[60px] items-center justify-center rounded-full border-4 shadow-xl transition-all active:scale-95",
              isHomeActive 
                ? "border-primary-500 bg-white dark:bg-slate-800 scale-110 -translate-y-1" 
                : "border-white dark:border-slate-800 bg-slate-100 dark:bg-slate-700"
            )}
          >
            <div className="h-full w-full rounded-full overflow-hidden">
              <img 
                src={avatarUrl} 
                alt="الرئيسية" 
                className="h-full w-full object-cover"
              />
            </div>
          </Link>
          <span className={cn(
            "text-[10px] font-black mt-1 transition-colors",
            isHomeActive ? "text-primary-700 dark:text-primary-400" : "text-slate-400"
          )}>
            الرئيسية
          </span>
        </div>

        {/* Regular Navigation Items */}
        {regularItems.map(renderItem)}

        {/* More Button */}
        {renderMoreButton()}
      </div>
    </nav>
  )
}


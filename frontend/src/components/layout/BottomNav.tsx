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

import { useAuthStore } from '@/stores/auth.store'

interface BottomNavProps {
  onMoreClick: () => void
}

export function BottomNav({ onMoreClick }: BottomNavProps) {
  const pathname = usePathname()
  const role = useCurrentRole()
  const { user } = useAuthStore()

  if (!role || role === 'super_admin') return null

  const allowedHrefs = NAV_HREFS_BY_ROLE[role]
  
  const regularItems = [
    { label: 'الوردية',  href: '/worker',    icon: ClipboardList },
    { label: 'الأفواج',  href: '/flocks',    icon: Bird },
    { label: 'المخزون',  href: '/inventory', icon: Package },
  ].filter(item => allowedHrefs.includes(item.href))

  const renderItem = (item: { label: string, href: string, icon: any }) => {
    const isActive = pathname === item.href || pathname.startsWith(`${item.href}/`)
    return (
      <Link
        key={item.href}
        href={item.href}
        className={cn(
          'relative flex flex-col items-center justify-center gap-1 min-w-[56px] py-1.5 transition-all duration-200 active:scale-95',
          isActive ? 'text-emerald-600' : 'text-slate-400'
        )}
      >
        <item.icon 
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
          {item.label}
        </span>
        {isActive && (
          <span className="absolute -top-0.5 h-[3px] w-6 rounded-full bg-emerald-500 transition-all duration-300" />
        )}
      </Link>
    )
  }

  const renderMoreButton = () => (
    <button
      key="more-button"
      onClick={onMoreClick}
      className="flex flex-col items-center justify-center gap-1 min-w-[56px] py-1.5 text-slate-400 transition-all duration-200 active:scale-95"
    >
      <MoreHorizontal className="h-6 w-6" strokeWidth={1.8} />
      <span className="text-[10px] font-bold leading-none">المزيد</span>
    </button>
  )

  const isHomeActive = pathname === '/dashboard'
  const avatarUrl = user?.profile_picture_url || '/default-avatar.jpg'

  return (
    <nav
      className="fixed bottom-0 left-0 right-0 z-50 bg-white dark:bg-slate-900 border-t border-slate-100 dark:border-slate-800 shadow-[0_-8px_30px_rgba(0,0,0,0.08)] dark:shadow-[0_-8px_30px_rgba(0,0,0,0.4)]"
      style={{ paddingBottom: 'env(safe-area-inset-bottom, 4px)' }}
    >
      <div className="mx-auto flex max-w-2xl items-center justify-around px-2 sm:px-4 h-[78px]">
        {/* Home Avatar Button (First Position) */}
        <div className="flex flex-col items-center justify-center">
          <Link
            href="/dashboard"
            className={cn(
              "flex h-[60px] w-[60px] items-center justify-center rounded-full border-4 shadow-xl transition-all active:scale-90",
              isHomeActive 
                ? "border-emerald-500 bg-white dark:bg-slate-800 scale-110 -translate-y-1" 
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
            isHomeActive ? "text-emerald-700 dark:text-emerald-400" : "text-slate-400"
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

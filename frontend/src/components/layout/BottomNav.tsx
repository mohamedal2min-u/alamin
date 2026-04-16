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
          'relative flex flex-col items-center justify-center gap-1 min-w-[64px] py-2 transition-all duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] active:scale-90',
          isActive ? 'text-emerald-500' : 'text-slate-400 dark:text-slate-500'
        )}
      >
        <div className={cn(
          "relative flex items-center justify-center p-2 rounded-2xl transition-all duration-500",
          isActive && "bg-emerald-50 dark:bg-emerald-500/10 shadow-sm"
        )}>
          <item.icon 
            className={cn(
              'h-5 w-5 transition-transform duration-500', 
              isActive && 'scale-110 rotate-[5deg]'
            )} 
            strokeWidth={isActive ? 2.5 : 2}
          />
          {isActive && (
            <span className="absolute -top-1 -right-1 flex h-2 w-2">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
              <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
          )}
        </div>
        <span className={cn(
          'text-[10px] font-bold tracking-tight transition-all duration-300',
          isActive ? 'text-emerald-600 dark:text-emerald-400 scale-105' : 'opacity-80'
        )}>
          {item.label}
        </span>
      </Link>
    )
  }

  const renderMoreButton = () => (
    <button
      key="more-button"
      onClick={onMoreClick}
      className="flex flex-col items-center justify-center gap-1 min-w-[64px] py-2 text-slate-400 dark:text-slate-500 transition-all duration-300 active:scale-90"
    >
      <div className="p-2 rounded-2xl hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
        <MoreHorizontal className="h-5 w-5" strokeWidth={2} />
      </div>
      <span className="text-[10px] font-bold opacity-80">المزيد</span>
    </button>
  )

  const isHomeActive = pathname === '/dashboard'
  const avatarUrl = user?.profile_picture_url || '/default-avatar.jpg'

  return (
    <nav
      className="fixed bottom-0 left-0 right-0 z-50 px-4 pb-[env(safe-area-inset-bottom,12px)] pt-2"
    >
      <div className="mx-auto max-w-lg mb-2">
        <div className="relative flex items-center justify-around h-[72px] rounded-[32px] bg-white/80 dark:bg-slate-900/80 backdrop-blur-2xl border border-white/40 dark:border-slate-800/50 shadow-[0_8px_32px_rgba(0,0,0,0.12)] dark:shadow-[0_8px_32px_rgba(0,0,0,0.4)] px-2">
          
          {/* Home Avatar Button (First Position) */}
          <div className="flex flex-col items-center justify-center p-1">
            <Link
              href="/dashboard"
              className={cn(
                "relative group flex h-[58px] w-[58px] items-center justify-center rounded-full transition-all duration-500 active:scale-90",
                isHomeActive 
                  ? "ring-4 ring-emerald-500/30 scale-110 -translate-y-2" 
                  : "ring-2 ring-white dark:ring-slate-800 hover:ring-emerald-200"
              )}
            >
              <div className={cn(
                "h-full w-full rounded-full overflow-hidden border-2 shadow-inner transition-colors duration-500",
                isHomeActive ? "border-emerald-500" : "border-white dark:border-slate-800"
              )}>
                <img 
                  src={avatarUrl} 
                  alt="الرئيسية" 
                  className="h-full w-full object-cover"
                />
              </div>
              
              {/* Home Glow Effect */}
              {isHomeActive && (
                <div className="absolute inset-0 rounded-full bg-emerald-400/20 blur-xl animate-pulse -z-10" />
              )}
            </Link>
            <span className={cn(
              "text-[9px] font-black mt-1 transition-all duration-300 uppercase tracking-widest",
              isHomeActive ? "text-emerald-700 dark:text-emerald-300 translate-y-[-4px]" : "text-slate-400 opacity-60"
            )}>
              {isHomeActive ? '● الرئيسية' : 'الرئيسية'}
            </span>
          </div>

          {/* Divider */}
          <div className="h-8 w-[1px] bg-slate-100 dark:bg-slate-800 mx-1 opacity-50" />

          {/* Regular Navigation Items */}
          {regularItems.map(renderItem)}

          {/* More Button */}
          {renderMoreButton()}
        </div>
      </div>
    </nav>
  )
}

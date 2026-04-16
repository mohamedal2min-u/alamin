// frontend/src/components/layout/BottomNav.tsx
'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { cn } from '@/lib/utils'
import { 
  Wheat, Bird, Package,
  Grid2x2, Ellipsis
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
    { label: 'الوردية',  href: '/worker',    icon: Wheat,   activeColor: 'text-amber-500',   activeBg: 'bg-amber-50 dark:bg-amber-950/40' },
    { label: 'الأفواج',  href: '/flocks',    icon: Bird,    activeColor: 'text-sky-500',     activeBg: 'bg-sky-50 dark:bg-sky-950/40' },
    { label: 'المخزون',  href: '/inventory', icon: Package, activeColor: 'text-violet-500',  activeBg: 'bg-violet-50 dark:bg-violet-950/40' },
  ].filter(item => allowedHrefs.includes(item.href))

  const isHomeActive = pathname === '/dashboard'
  const avatarUrl = user?.profile_picture_url || '/default-avatar.jpg'

  return (
    <nav
      className="fixed bottom-0 left-0 right-0 z-50"
      style={{ paddingBottom: 'env(safe-area-inset-bottom, 0px)' }}
    >
      {/* Frosted glass background */}
      <div className="absolute inset-0 bg-white/80 dark:bg-slate-900/85 backdrop-blur-2xl border-t border-slate-200/60 dark:border-slate-700/40" />

      <div className="relative mx-auto flex max-w-lg items-end justify-around px-3 pb-1.5 pt-1">
        
        {/* ── Home Avatar (First Position) ── */}
        <Link
          href="/dashboard"
          className="flex flex-col items-center justify-end group"
        >
          <div
            className={cn(
              "relative h-[52px] w-[52px] rounded-2xl overflow-hidden transition-all duration-300 active:scale-90",
              isHomeActive 
                ? "ring-[3px] ring-emerald-400 ring-offset-2 ring-offset-white dark:ring-offset-slate-900 shadow-lg shadow-emerald-200/50 dark:shadow-emerald-900/30 -translate-y-1" 
                : "ring-2 ring-slate-200 dark:ring-slate-600 group-hover:ring-emerald-300 shadow-md"
            )}
          >
            <img 
              src={avatarUrl} 
              alt="الرئيسية" 
              className="h-full w-full object-cover"
            />
            {isHomeActive && (
              <div className="absolute inset-0 bg-gradient-to-t from-emerald-500/20 to-transparent" />
            )}
          </div>
          <span className={cn(
            "text-[10px] font-black mt-1.5 transition-colors duration-200",
            isHomeActive ? "text-emerald-600 dark:text-emerald-400" : "text-slate-400 group-hover:text-slate-500"
          )}>
            الرئيسية
          </span>
        </Link>

        {/* ── Regular Navigation Items ── */}
        {regularItems.map((item) => {
          const isActive = pathname === item.href || pathname.startsWith(`${item.href}/`)
          const Icon = item.icon
          return (
            <Link
              key={item.href}
              href={item.href}
              className="flex flex-col items-center justify-end group min-w-[52px]"
            >
              <div
                className={cn(
                  "flex items-center justify-center h-10 w-10 rounded-xl transition-all duration-300 active:scale-90",
                  isActive 
                    ? `${item.activeBg} ${item.activeColor} scale-110 -translate-y-0.5 shadow-sm` 
                    : "text-slate-400 group-hover:text-slate-500 group-hover:bg-slate-50 dark:group-hover:bg-slate-800"
                )}
              >
                <Icon 
                  className="h-[22px] w-[22px] transition-all duration-300" 
                  strokeWidth={isActive ? 2.4 : 1.6}
                />
              </div>
              <span className={cn(
                'text-[10px] font-bold leading-none mt-1.5 transition-colors duration-200',
                isActive ? item.activeColor : 'text-slate-400 group-hover:text-slate-500'
              )}>
                {item.label}
              </span>
            </Link>
          )
        })}

        {/* ── More Button ── */}
        <button
          onClick={onMoreClick}
          className="flex flex-col items-center justify-end group min-w-[52px]"
        >
          <div className="flex items-center justify-center h-10 w-10 rounded-xl text-slate-400 transition-all duration-300 active:scale-90 group-hover:text-slate-500 group-hover:bg-slate-50 dark:group-hover:bg-slate-800">
            <Ellipsis className="h-[22px] w-[22px]" strokeWidth={1.6} />
          </div>
          <span className="text-[10px] font-bold leading-none mt-1.5 text-slate-400 group-hover:text-slate-500">المزيد</span>
        </button>
      </div>
    </nav>
  )
}

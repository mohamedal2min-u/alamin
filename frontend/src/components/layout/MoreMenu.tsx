// frontend/src/components/layout/MoreMenu.tsx
'use client'

import {
  ShoppingCart, Users, BarChart3, LogOut, X, Receipt, ChevronLeft, Settings, Calculator, Wallet
} from 'lucide-react'
import Link from 'next/link'
import { FarmSelector } from './FarmSelector'
import { useAuthStore } from '@/stores/auth.store'
import { apiClient } from '@/lib/api/client'
import { useRouter } from 'next/navigation'
import { cn } from '@/lib/utils'
import { useCurrentRole } from '@/lib/roles'
import { NAV_HREFS_BY_ROLE } from '@/lib/roles'

interface MoreMenuProps {
  isOpen: boolean
  onClose: () => void
}

export function MoreMenu({ isOpen, onClose }: MoreMenuProps) {
  const { user, logout } = useAuthStore()
  const router = useRouter()
  const role = useCurrentRole()

  if (!isOpen || !role) return null

  const handleLogout = async () => {
    try {
      await apiClient.post('/auth/logout')
    } catch {
      // ignore
    } finally {
      logout()
      onClose()
      router.replace('/login')
    }
  }

  const allowedHrefs = NAV_HREFS_BY_ROLE[role]

  const items = [
    { label: 'المبيعات',  href: '/sales',      icon: ShoppingCart, color: 'text-primary-600', bg: 'bg-primary-50' },
    { label: 'المصروفات', href: '/expenses',    icon: Receipt,      color: 'text-orange-600',  bg: 'bg-orange-50' },
    { label: 'الشركاء',   href: '/partners',   icon: Users,        color: 'text-blue-600',    bg: 'bg-blue-50' },
    { label: 'العمال',    href: '/workers',    icon: Users,        color: 'text-slate-600',   bg: 'bg-slate-50' },
    { label: 'المحاسبة',  href: '/accounting', icon: Calculator,   color: 'text-violet-600',  bg: 'bg-violet-50' },
    { label: 'التقارير',  href: '/reports',    icon: BarChart3,    color: 'text-sky-600',     bg: 'bg-sky-50' },
    { label: 'محفظتي',   href: '/my-wallet',  icon: Wallet,       color: 'text-emerald-600', bg: 'bg-emerald-50' },
  ].filter(item => allowedHrefs.includes(item.href))

  return (
    <div className="fixed inset-0 z-[100] flex items-end justify-center">
      {/* Backdrop */}
      <div 
        className="fixed inset-0 bg-black/50 backdrop-blur-[4px] animate-in fade-in duration-300" 
        onClick={onClose} 
      />
      
      {/* Sheet */}
      <div 
        className="relative w-full max-w-2xl rounded-t-[32px] bg-white dark:bg-slate-900 animate-in slide-in-from-bottom duration-300 border-t border-slate-100 dark:border-slate-800"
        style={{ boxShadow: '0 -10px 40px rgba(0,0,0,0.15)' }}
      >
        {/* Handle */}
        <div className="flex justify-center pt-3 pb-1">
          <div className="h-1 w-10 rounded-full bg-slate-200 dark:bg-slate-600" />
        </div>

        {/* Header */}
        <div className="flex items-center justify-between px-6 pb-4 pt-2">
          <Link
            href="/settings"
            onClick={onClose}
            className="flex items-center gap-3 group"
          >
            <div className="h-10 w-10 rounded-full overflow-hidden bg-primary-50 flex items-center justify-center text-primary-700 text-sm font-bold shrink-0">
              <img 
                src={user?.avatar_url || '/default-avatar.jpg'} 
                alt="" 
                className="h-full w-full object-cover" 
              />
            </div>
            <div>
              <p className="text-sm font-bold text-slate-900 dark:text-slate-100 group-hover:text-primary-700 transition-colors">{user?.name}</p>
              <p className="text-[11px] text-primary-600 font-medium flex items-center gap-1">
                <Settings className="h-3 w-3" />
                إعدادات الحساب
              </p>
            </div>
          </Link>
          <button
            onClick={onClose}
            className="h-9 w-9 rounded-full bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 active:scale-95 transition-transform"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Farm Selector Section */}
        <div className="mx-5 mb-4 p-3.5 rounded-2xl bg-slate-50/80 dark:bg-slate-700/50 border border-slate-100/80 dark:border-slate-600/50">
          <p className="text-[10px] font-bold text-slate-400 mb-2 px-0.5 uppercase tracking-wider">المزرعة الحالية</p>
          <FarmSelector />
        </div>

        {/* Navigation List — Vertical for easier scanning */}
        <div className="px-5 mb-4 space-y-1">
          {items.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              onClick={onClose}
              className="flex items-center gap-3.5 px-4 py-3.5 rounded-2xl transition-all duration-200 active:scale-[0.98] active:bg-slate-50 dark:active:bg-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 group"
            >
              <div className={cn(
                "h-10 w-10 rounded-xl flex items-center justify-center shrink-0",
                item.bg
              )}>
                <item.icon className={cn("h-5 w-5", item.color)} />
              </div>
              <span className="text-sm font-bold text-slate-700 dark:text-slate-200 flex-1">{item.label}</span>
              <ChevronLeft className="h-4 w-4 text-slate-300 dark:text-slate-500 group-hover:text-slate-400 dark:group-hover:text-slate-400 transition-colors" />
            </Link>
          ))}
        </div>

        {/* Logout */}
        <div className="px-5 pb-6">
          <button
            onClick={handleLogout}
            className="w-full flex items-center justify-center gap-2 py-3.5 rounded-2xl text-sm font-bold text-rose-600 bg-rose-50/80 active:bg-rose-100 active:scale-[0.98] transition-all duration-200"
          >
            <LogOut className="h-4 w-4" />
            تسجيل الخروج
          </button>
        </div>

        <div style={{ height: 'env(safe-area-inset-bottom, 8px)' }} />
      </div>
    </div>
  )
}


// frontend/src/components/layout/MoreMenu.tsx
'use client'

import {
  ShoppingCart, Users, BarChart3, LogOut, X, Receipt, Settings, Calculator, Wallet, UserCog, BellRing, Scale, Contact
} from 'lucide-react'
import Link from 'next/link'
import { AppInstallButton } from '@/components/auth/AppInstallButton'
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
    { label: 'المبيعات',  desc: 'بيع الطيور والمستلم',   href: '/sales',      icon: ShoppingCart, color: 'text-primary-600', bg: 'bg-primary-50 dark:bg-primary-900/30' },
    { label: 'المصروفات', desc: 'علف، دواء، ماء وغيرها', href: '/expenses',   icon: Receipt,      color: 'text-emerald-600', bg: 'bg-emerald-50 dark:bg-emerald-900/30' },
    { label: 'الشركاء',   desc: 'الحصص والحركات',        href: '/partners',   icon: Users,        color: 'text-blue-600',    bg: 'bg-blue-50 dark:bg-blue-900/30' },
    { label: 'المحاسبون', desc: 'حسابات الفريق',         href: '/workers',    icon: UserCog,      color: 'text-slate-600',   bg: 'bg-slate-100 dark:bg-slate-700' },
    { label: 'المحاسبة',  desc: 'الذمم والمراجعة',       href: '/accounting', icon: Calculator,   color: 'text-violet-600',  bg: 'bg-violet-50 dark:bg-violet-900/30' },
    { label: 'حسابات الموردين والتجار', desc: 'الأرصدة وكشوف الحساب', href: '/accounting/contacts', icon: Contact, color: 'text-orange-600', bg: 'bg-orange-50 dark:bg-orange-900/30' },
    { label: 'التقارير',  desc: 'ملخصات الأداء',         href: '/reports',    icon: BarChart3,    color: 'text-sky-600',     bg: 'bg-sky-50 dark:bg-sky-900/30' },
    { label: 'مقارنة الأفواج', desc: 'التحويل والتكلفة لكل فوج', href: '/reports/flock-comparison', icon: Scale, color: 'text-amber-600', bg: 'bg-amber-50 dark:bg-amber-900/30' },
    { label: 'الأذان',    desc: 'أوقات الصلاة والتنبيه', href: '/prayer',    icon: BellRing,     color: 'text-amber-600',   bg: 'bg-amber-50 dark:bg-amber-900/30' },
    { label: 'محفظتي',    desc: 'رصيدك وحصتك',          href: '/my-wallet',  icon: Wallet,       color: 'text-emerald-600', bg: 'bg-emerald-50 dark:bg-emerald-900/30' },
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
        className="relative max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-t-[32px] bg-white dark:bg-slate-900 animate-in slide-in-from-bottom duration-300 border-t border-slate-100 dark:border-slate-800"
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

        {/* Navigation cards */}
        <div className="px-5 mb-4 grid grid-cols-2 gap-3">
          {items.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              onClick={onClose}
              className="flex flex-col gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm transition-all duration-200 active:scale-[0.97] hover:border-primary-200 hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
            >
              <div className={cn("h-11 w-11 rounded-xl flex items-center justify-center", item.bg)}>
                <item.icon className={cn("h-5 w-5", item.color)} />
              </div>
              <div>
                <p className="text-sm font-extrabold text-slate-800 dark:text-slate-100">{item.label}</p>
                <p className="mt-0.5 text-[11px] font-medium text-slate-400">{item.desc}</p>
              </div>
            </Link>
          ))}
        </div>

        {/* Logout */}
        {/* تحميل/تحديث تطبيق أندرويد: يظهر هنا أيضاً لمن هو مسجّل الدخول */}
        <div className="mb-3 px-5">
          <AppInstallButton light />
        </div>

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


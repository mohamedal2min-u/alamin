// frontend/src/components/layout/MoreMenu.tsx
'use client'

import {
  ShoppingCart, Users, BarChart3, Settings, LogOut, X, User, Receipt
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
    { label: 'المبيعات', href: '/sales',    icon: ShoppingCart, color: 'text-emerald-500', bg: 'bg-emerald-50' },
    { label: 'الشركاء',  href: '/partners', icon: Users,        color: 'text-blue-500',    bg: 'bg-blue-50' },
    { label: 'العمال',   href: '/workers', icon: Users,      color: 'text-orange-500',  bg: 'bg-orange-50' },
    { label: 'التقارير',  href: '/reports',  icon: BarChart3,    color: 'text-sky-500',     bg: 'bg-sky-50' },
  ].filter(item => allowedHrefs.includes(item.href))

  return (
    <div className="fixed inset-0 z-[100] flex items-end justify-center bg-slate-900/50 backdrop-blur-sm animate-in fade-in duration-200">
      <div 
        className="fixed inset-0" 
        onClick={onClose} 
      />
      
      <div className="relative w-full max-w-2xl rounded-t-2xl bg-white p-6 animate-in slide-in-from-bottom-full duration-300" style={{ boxShadow: 'var(--shadow-float)' }}>
        {/* Handle */}
        <div className="mx-auto mb-5 h-1 w-10 rounded-full bg-slate-200" />

        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-3">
            <div className="h-9 w-9 rounded-xl bg-primary-100 flex items-center justify-center text-primary-600">
              <User className="h-4 w-4" />
            </div>
            <div>
              <p className="font-bold text-sm text-slate-900">{user?.name}</p>
              <p className="text-[11px] text-slate-500 font-medium">مرحباً بك في دجاجتي</p>
            </div>
          </div>
          <button 
            onClick={onClose}
            className="rounded-xl p-2 bg-slate-100 text-slate-400 hover:bg-slate-200 transition-colors duration-200 active:scale-95"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Farm Selector Section */}
        <div className="mb-6 p-4 rounded-2xl bg-slate-50 border border-slate-100">
          <p className="text-[10px] font-bold text-slate-400 mb-2.5 px-1 uppercase tracking-wider">المزرعة الحالية</p>
          <FarmSelector />
        </div>

        {/* Navigation Grid */}
        <div className="grid grid-cols-3 gap-2.5 mb-6">
          {items.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              onClick={onClose}
              className={cn(
                "flex flex-col items-center justify-center p-4 rounded-2xl border border-slate-100 transition-all duration-200 active:scale-95",
                item.bg, "hover:border-slate-200"
              )}
            >
              <item.icon className={cn("h-6 w-6 mb-1.5", item.color)} />
              <span className="text-xs font-bold text-slate-700">{item.label}</span>
            </Link>
          ))}
          
          <button
            className="flex flex-col items-center justify-center p-4 rounded-2xl bg-slate-50 border border-slate-100 hover:bg-primary-50 hover:border-primary-100 transition-all duration-200 group active:scale-95"
          >
            <Settings className="h-6 w-6 text-slate-500 mb-1.5 group-hover:text-primary-600 transition-colors duration-200" />
            <span className="text-xs font-bold text-slate-600 group-hover:text-primary-700">الإعدادات</span>
          </button>
        </div>

        {/* Logout */}
        <button
          onClick={handleLogout}
          className="w-full flex items-center justify-center gap-2 p-3.5 rounded-2xl bg-red-50 text-red-600 text-sm font-bold hover:bg-red-100 transition-colors duration-200 active:scale-[0.98]"
        >
          <LogOut className="h-4 w-4" />
          تسجيل الخروج
        </button>

        <div className="h-safe-offset-4" />
      </div>
    </div>
  )
}

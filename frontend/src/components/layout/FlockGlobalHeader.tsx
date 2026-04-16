'use client'

import { cn } from '@/lib/utils'
import { Calendar, UserCircle, RefreshCcw } from 'lucide-react'
import { FlockStatusBadge } from '@/components/flocks/FlockStatusBadge'
import { formatDate, formatNumber } from '@/lib/utils'
import { profileApi } from '@/lib/api/profile'
import { useAuthStore } from '@/stores/auth.store'
import { useFarmStore } from '@/stores/farm.store'
import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { flocksApi } from '@/lib/api/flocks'
import type { Flock } from '@/types/flock'

export function FlockGlobalHeader() {
  const { user, setUser } = useAuthStore()
  const { currentFarm } = useFarmStore()
  const queryClient = useQueryClient()
  
  const [isUploading, setIsUploading] = useState(false)
  const [isRefreshing, setIsRefreshing] = useState(false)

  // Fetch flocks to get the active one
  const { data: flocks = [], isLoading } = useQuery<Flock[]>({
    queryKey: ['flocks', currentFarm?.id],
    queryFn: () => flocksApi.list().then(res => res.data),
    enabled: !!currentFarm,
    staleTime: 5000,
  })

  // Determine active flock
  const activeFlock = flocks.find(f => f.status === 'active') ?? null
  let roleLabel = 'المربي'
  if (currentFarm?.role === 'super_admin' || currentFarm?.role === 'farm_admin') {
    roleLabel = 'مدير المدجنة'
  } else if (currentFarm?.role === 'partner') {
    roleLabel = 'شريك'
  }

  const handleGlobalRefresh = async () => {
    setIsRefreshing(true)
    try {
      await queryClient.invalidateQueries()
    } finally {
      setTimeout(() => setIsRefreshing(false), 500)
    }
  }

  const handleAvatarUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file || !user) return

    setIsUploading(true)
    try {
      const res = await profileApi.uploadAvatar(file)
      setUser({ ...user, avatar_url: res.avatar_url })
    } catch (error) {
      console.error('Failed to upload avatar', error)
      alert('حدث خطأ أثناء رفع الصورة')
    } finally {
      setIsUploading(false)
      e.target.value = ''
    }
  }

  const mortalityRate = activeFlock 
    ? (activeFlock.initial_count > 0 
        ? ((activeFlock.total_mortality ?? 0) / activeFlock.initial_count * 100).toFixed(1)
        : '0.0')
    : '0.0'

  if (!currentFarm) return null

  return (
    <header className="sticky top-0 z-40 w-full bg-slate-50/90 dark:bg-slate-900/90 backdrop-blur-md p-2 border-b border-transparent dark:border-slate-800" dir="rtl">
      <div className="rounded-2xl bg-white border border-emerald-100 overflow-hidden shadow-sm mx-auto max-w-2xl">
        {/* Top: User (Right) + Flock Name (Center) + Status (Left) */}
        <div className="flex items-center justify-between px-2.5 py-1.5 border-b border-emerald-50/50">
          
          {/* User Profile (Right visually in RTL) */}
          <div className="w-[35%] flex justify-start">
            <div className="flex items-center gap-2 text-right">
              <button 
                onClick={() => document.getElementById('global-avatar-upload')?.click()}
                disabled={isUploading}
                className={cn(
                  "h-8 w-8 shrink-0 rounded-[8px] bg-emerald-50 border border-emerald-100/50 flex flex-col items-center justify-center overflow-hidden transition-all hover:opacity-80 active:scale-95",
                  isUploading && "animate-pulse opacity-50 cursor-wait"
                )}
                title="تغيير الصورة الشخصية"
              >
                {user?.avatar_url ? (
                  <img src={user.avatar_url} alt="Profile" className="h-full w-full object-cover" />
                ) : (
                  <UserCircle className="h-4 w-4 text-emerald-600/70" />
                )}
              </button>
              <div className="flex flex-col justify-center">
                <span className="text-[11px] font-black text-slate-800 leading-none truncate max-w-[80px]">
                  {user?.name || 'مستخدم'}
                </span>
                <span className="text-[9px] font-bold text-slate-500 mt-[2px] leading-none">
                  {roleLabel}
                </span>
              </div>
            </div>
            
            <input 
              id="global-avatar-upload" 
              type="file" 
              accept="image/*" 
              className="hidden" 
              onChange={handleAvatarUpload} 
            />
          </div>

          {/* Flock Info (Center) */}
          <div className="w-[30%] flex flex-col items-center justify-center text-center">
            <h2 className="text-[12px] font-black text-emerald-950 leading-none truncate mb-0.5">
              {activeFlock?.name ?? (isLoading ? 'تحميل...' : 'لا فوج')}
            </h2>
            {activeFlock && (
              <div className="flex items-center justify-center gap-1 text-[9px] font-bold text-emerald-600/80 leading-none">
                <Calendar className="h-2.5 w-2.5" />
                <span>{formatDate(activeFlock.start_date)}</span>
              </div>
            )}
          </div>

          {/* Status (Left visually in RTL) */}
          <div className="w-[35%] flex justify-end items-center gap-2">
             <button
                onClick={handleGlobalRefresh}
                disabled={isRefreshing}
                className="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-slate-50 text-slate-400 active:scale-95 transition-all disabled:opacity-40"
              >
                <RefreshCcw className={cn("h-3 w-3", isRefreshing && "animate-spin")} />
            </button>
            {activeFlock && <FlockStatusBadge status={activeFlock.status} />}
          </div>

        </div>

        {/* Metrics: 4 columns */}
        {activeFlock ? (
          <div className="grid grid-cols-4 divide-x divide-x-reverse divide-emerald-50/50">
            <MetricItem label="الأولي" value={formatNumber(activeFlock.initial_count ?? 0)} color="text-slate-600" />
            <MetricItem label="النفوق" value={formatNumber(activeFlock.total_mortality ?? 0)} sub={`${mortalityRate}%`} color="text-rose-600" />
            <MetricItem label="المتبقي" value={formatNumber(activeFlock.remaining_count ?? 0)} sub="طير" color="text-emerald-600" />
            <MetricItem label="العمر" value={activeFlock.current_age_days ?? '—'} sub="يوم" color="text-emerald-950" />
          </div>
        ) : (
          !isLoading && (
            <div className="py-2 px-3 text-center text-[10px] font-semibold text-slate-400 bg-slate-50/50">
              يرجى إنشاء وتفعيل فوج للبدء بتسجيل البيانات
            </div>
          )
        )}
      </div>
    </header>
  )
}

function MetricItem({ label, value, sub, color }: { label: string; value: string | number; sub?: string; color: string }) {
  return (
    <div className="flex flex-col items-center justify-center py-1.5 px-1 text-center">
      <span className="text-[9px] font-bold text-slate-400 leading-tight block">{label}</span>
      <span className={cn("text-[17px] font-black tabular-nums leading-tight block", color)}>{value}</span>
      {sub && <span className="text-[9px] font-bold text-slate-400 leading-tight block">{sub}</span>}
    </div>
  )
}

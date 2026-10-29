'use client'

import { useEffect } from 'react'
import Link from 'next/link'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Bell, CheckCheck, Loader2 } from 'lucide-react'
import { useLayoutStore } from '@/stores/layout.store'
import { notificationsApi, formatTime } from '@/lib/api/notifications'
import { cn } from '@/lib/utils'

export default function NotificationsPage() {
  const { setPageTitle, setPageSubtitle } = useLayoutStore()
  const queryClient = useQueryClient()
  const { data, isLoading } = useQuery({ queryKey: ['notifications'], queryFn: notificationsApi.list })

  useEffect(() => {
    setPageTitle('الإشعارات')
    setPageSubtitle('التعديلات التي وصلتك')
  }, [setPageTitle, setPageSubtitle])

  const readAll = async () => {
    await notificationsApi.readAll()
    await queryClient.invalidateQueries({ queryKey: ['notifications'] })
  }

  const items = data?.data ?? []

  return (
    <div className="mx-auto max-w-2xl space-y-3 p-4">
      {(data?.unread ?? 0) > 0 && (
        <button onClick={readAll} className="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
          <CheckCheck className="h-4 w-4" /> تعليم الكل كمقروء
        </button>
      )}

      {isLoading && <div className="flex justify-center py-10"><Loader2 className="h-6 w-6 animate-spin text-slate-400" /></div>}

      {!isLoading && items.length === 0 && (
        <div className="flex flex-col items-center gap-2 py-16 text-slate-400">
          <Bell className="h-10 w-10" />
          <p className="text-sm font-bold">لا توجد إشعارات</p>
        </div>
      )}

      {items.map((n) => (
        <Link
          key={n.id}
          href={`/notifications/${n.id}`}
          className={cn(
            'block rounded-2xl border p-4 transition-colors',
            n.read
              ? 'border-slate-100 bg-white dark:border-slate-800 dark:bg-slate-900'
              : 'border-primary-200 bg-primary-50 dark:border-primary-800 dark:bg-primary-900/30'
          )}
        >
          <div className="flex items-center justify-between gap-2">
            <span className="text-sm font-black text-slate-800 dark:text-slate-100">{n.title}</span>
            <span className="shrink-0 text-[11px] font-bold text-slate-400">{formatTime(n.created_at)}</span>
          </div>
          <p className="mt-1 whitespace-pre-line text-xs font-semibold text-slate-600 dark:text-slate-300">{n.body}</p>
        </Link>
      ))}
    </div>
  )
}

'use client'

import { use, useEffect } from 'react'
import Link from 'next/link'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowRight, Bird, Loader2, User } from 'lucide-react'
import { useLayoutStore } from '@/stores/layout.store'
import { notificationsApi, formatTime } from '@/lib/api/notifications'
import { canAccessRoute, useCurrentRole } from '@/lib/roles'

/** تفاصيل التعديل: يفتح عند الضغط على إشعار الهاتف */
export default function NotificationDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params)
  const { setPageTitle, setPageSubtitle } = useLayoutStore()
  const queryClient = useQueryClient()
  const role = useCurrentRole()
  const { data: n, isLoading, isError } = useQuery({
    queryKey: ['notifications', Number(id)],
    queryFn: () => notificationsApi.get(Number(id)),
  })

  useEffect(() => {
    setPageTitle('تفاصيل التعديل')
    setPageSubtitle('')
  }, [setPageTitle, setPageSubtitle])

  // صار مقروءاً؛ نحدّث العدّاد والقائمة
  useEffect(() => {
    if (n) void queryClient.invalidateQueries({ queryKey: ['notifications'], exact: true })
  }, [n, queryClient])

  if (isLoading) return <div className="flex justify-center py-16"><Loader2 className="h-6 w-6 animate-spin text-slate-400" /></div>
  if (isError || !n) return <p className="p-6 text-center text-sm font-bold text-slate-500">الإشعار غير موجود</p>

  const rows = n.details?.rows ?? []
  const flockLink = n.link && canAccessRoute(role, n.link) ? n.link : null

  return (
    <div className="mx-auto max-w-2xl space-y-4 p-4">
      <Link href="/notifications" className="inline-flex items-center gap-1 text-xs font-bold text-slate-500">
        <ArrowRight className="h-4 w-4" /> كل الإشعارات
      </Link>

      <div className="rounded-2xl border border-slate-100 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <div className="flex items-center justify-between gap-2">
          <h2 className="text-base font-black text-slate-800 dark:text-slate-100">{n.title}</h2>
          <span className="text-[11px] font-bold text-slate-400">{formatTime(n.created_at)}</span>
        </div>
        <p className="mt-2 text-sm font-bold text-slate-700 dark:text-slate-200">{n.details?.what ?? n.body.split('\n')[0]}</p>
        <div className="mt-3 flex flex-wrap gap-2 text-xs font-bold text-slate-500">
          {n.actor && <span className="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 dark:bg-slate-800"><User className="h-3.5 w-3.5" /> {n.actor}</span>}
          {n.details?.flock && <span className="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 dark:bg-slate-800"><Bird className="h-3.5 w-3.5" /> {n.details.flock}</span>}
          {n.farm && <span className="rounded-lg bg-slate-100 px-2 py-1 dark:bg-slate-800">{n.farm}</span>}
        </div>
      </div>

      <div className="rounded-2xl border border-slate-100 bg-white dark:border-slate-800 dark:bg-slate-900">
        <h3 className="border-b border-slate-100 px-4 py-3 text-sm font-black text-slate-700 dark:border-slate-800 dark:text-slate-200">ما الذي تغيّر</h3>
        {rows.length === 0 ? (
          <p className="px-4 py-4 text-xs font-semibold text-slate-500">لا توجد تفاصيل إضافية لهذا الإشعار.</p>
        ) : (
          <ul className="divide-y divide-slate-100 dark:divide-slate-800">
            {rows.map((r, i) => (
              <li key={i} className="flex items-start justify-between gap-3 px-4 py-3 text-sm">
                <span className="shrink-0 font-bold text-slate-500">{r.label}</span>
                <span className="text-left font-black text-slate-800 dark:text-slate-100">
                  {r.from !== undefined && <><span className="text-rose-500 line-through">{r.from}</span><span className="mx-1.5 text-slate-400">←</span></>}
                  <span className={r.from !== undefined ? 'text-emerald-600' : ''}>{r.to}</span>
                </span>
              </li>
            ))}
          </ul>
        )}
      </div>

      {flockLink && (
        <Link href={flockLink} className="block rounded-2xl bg-primary-600 py-3 text-center text-sm font-black text-white">
          فتح الفوج
        </Link>
      )}
    </div>
  )
}

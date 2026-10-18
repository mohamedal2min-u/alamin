'use client'

import { useEffect, useState } from 'react'
import { useQuery, keepPreviousData } from '@tanstack/react-query'
import { History, Loader2, User, Bird } from 'lucide-react'
import { useFarmStore } from '@/stores/farm.store'
import { useLayoutStore } from '@/stores/layout.store'
import { dailyEntriesApi, type ActivityAction, type ActivityLogEntry, type DailyEntryType } from '@/lib/api/dailyEntries'
import { cn } from '@/lib/utils'

const TYPE_LABELS: Record<DailyEntryType, string> = {
  mortality: 'نفوق',
  feed_log: 'علف',
  medicine_log: 'دواء',
  water_log: 'ماء',
  note: 'ملاحظة',
  temperature_log: 'حرارة',
}

const ACTION_STYLES: Record<ActivityAction, { label: string; cls: string }> = {
  created: { label: 'أضاف', cls: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' },
  updated: { label: 'عدّل', cls: 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' },
  voided: { label: 'ألغى', cls: 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300' },
}

const FIELD_LABELS: Record<string, string> = {
  quantity: 'الكمية',
  reason: 'السبب',
  notes: 'ملاحظات',
  note_text: 'النص',
  note_type: 'النوع',
  temperature: 'الحرارة',
  entry_date: 'التاريخ',
  log_date: 'التاريخ',
  time_of_day: 'الفترة',
  item_name: 'الصنف',
  unit_label: 'الوحدة',
}

const HIDDEN_FIELDS = new Set(['item_id'])

const show = (v: unknown) => (v === null || v === undefined || v === '' ? '—' : String(v))

/** وصف مختصر للتغيير: الحقول المعدلة (قبل ← بعد) أو ملخص السجل */
function describe(e: ActivityLogEntry): { label: string; from?: string; to: string }[] {
  if (e.action === 'updated' && e.before && e.after) {
    return Object.keys(e.after)
      .filter((k) => !HIDDEN_FIELDS.has(k) && show(e.before?.[k]) !== show(e.after?.[k]))
      .map((k) => ({ label: FIELD_LABELS[k] ?? k, from: show(e.before?.[k]), to: show(e.after?.[k]) }))
  }
  const snap = (e.action === 'voided' ? e.before : e.after) ?? {}
  return ['item_name', 'quantity', 'temperature', 'note_text', 'reason']
    .filter((k) => snap[k] !== undefined && snap[k] !== null && snap[k] !== '')
    .map((k) => ({ label: FIELD_LABELS[k] ?? k, to: show(snap[k]) }))
}

const selectCls = 'min-w-0 flex-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2 py-2 text-xs font-bold text-slate-700 dark:text-slate-200'

export default function ActivityPage() {
  const { currentFarm } = useFarmStore()
  const { setPageTitle, setPageSubtitle } = useLayoutStore()
  const [page, setPage] = useState(1)
  const [flockId, setFlockId] = useState<number | undefined>()
  const [userId, setUserId] = useState<number | undefined>()
  const [type, setType] = useState<DailyEntryType | undefined>()

  useEffect(() => {
    setPageTitle('سجل النشاطات')
    setPageSubtitle(currentFarm?.name || null)
  }, [currentFarm, setPageTitle, setPageSubtitle])

  const { data, isLoading, isFetching } = useQuery({
    queryKey: ['activity-logs', currentFarm?.id, { page, flockId, userId, type }],
    queryFn: () => dailyEntriesApi.activity({ page, flock_id: flockId, user_id: userId, subject_type: type }),
    enabled: !!currentFarm,
    staleTime: 15_000,
    placeholderData: keepPreviousData,
  })

  const num = (v: string) => (v ? Number(v) : undefined)

  return (
    <div className="space-y-4 pb-24" dir="rtl">
      <div className="flex items-start gap-2 rounded-2xl bg-slate-50 dark:bg-slate-800/60 p-3">
        <History className="h-4 w-4 mt-0.5 shrink-0 text-primary-600" />
        <p className="text-xs text-slate-600 dark:text-slate-300">
          كل إضافة أو تعديل أو إلغاء لسجل يومي يُحفظ هنا باسم من قام به ووقته وسببه، ولا يمكن حذفه.
        </p>
      </div>

      {/* الفلاتر */}
      <div className="flex gap-2">
        <select aria-label="الفوج" className={selectCls} value={flockId ?? ''} onChange={(e) => { setFlockId(num(e.target.value)); setPage(1) }}>
          <option value="">كل الأفواج</option>
          {data?.filters.flocks.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
        </select>
        <select aria-label="المستخدم" className={selectCls} value={userId ?? ''} onChange={(e) => { setUserId(num(e.target.value)); setPage(1) }}>
          <option value="">كل المستخدمين</option>
          {data?.filters.users.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
        </select>
        <select aria-label="النوع" className={selectCls} value={type ?? ''} onChange={(e) => { setType((e.target.value || undefined) as DailyEntryType | undefined); setPage(1) }}>
          <option value="">كل الأنواع</option>
          {(Object.keys(TYPE_LABELS) as DailyEntryType[]).map((t) => <option key={t} value={t}>{TYPE_LABELS[t]}</option>)}
        </select>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-12"><Loader2 className="h-6 w-6 animate-spin text-slate-400" /></div>
      ) : !data?.data.length ? (
        <p className="py-12 text-center text-sm text-slate-400">لا توجد نشاطات مسجلة</p>
      ) : (
        <div className={cn('space-y-2', isFetching && 'opacity-70')}>
          {data.data.map((e) => {
            const a = ACTION_STYLES[e.action]
            const lines = describe(e)
            return (
              <div key={e.id} className="rounded-2xl border border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-800 p-3.5 space-y-1.5">
                <div className="flex items-center justify-between gap-2">
                  <div className="flex items-center gap-2 min-w-0">
                    <span className={cn('shrink-0 rounded-lg px-2 py-0.5 text-[11px] font-black', a.cls)}>{a.label}</span>
                    <span className="truncate text-sm font-bold text-slate-800 dark:text-slate-100">{TYPE_LABELS[e.subject_type] ?? e.subject_type}</span>
                  </div>
                  <span className="shrink-0 text-[10px] font-bold text-slate-400 tabular-nums">
                    {e.created_at ? new Date(e.created_at).toLocaleString('ar', { dateStyle: 'short', timeStyle: 'short' }) : ''}
                  </span>
                </div>

                {lines.length > 0 && (
                  <ul className="space-y-0.5 text-xs text-slate-600 dark:text-slate-300">
                    {lines.map((l) => (
                      <li key={l.label} className="break-words">
                        <span className="text-slate-400">{l.label}: </span>
                        {l.from !== undefined && <><s className="text-rose-400">{l.from}</s> ← </>}
                        <span className="font-bold">{l.to}</span>
                      </li>
                    ))}
                  </ul>
                )}

                {e.reason && (
                  <p className="text-xs text-slate-500 dark:text-slate-400"><span className="text-slate-400">السبب: </span>{e.reason}</p>
                )}

                <div className="flex items-center gap-3 text-[10px] font-bold text-slate-400">
                  <span className="flex items-center gap-1"><User className="h-3 w-3" />{e.user_name ?? '—'}</span>
                  {e.flock_name && <span className="flex items-center gap-1"><Bird className="h-3 w-3" />{e.flock_name}</span>}
                </div>
              </div>
            )
          })}

          {data.meta.last_page > 1 && (
            <div className="flex items-center justify-center gap-3 pt-3">
              <button disabled={page <= 1} onClick={() => setPage((p) => p - 1)} className="rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-xs font-bold text-slate-600 dark:text-slate-300 disabled:opacity-40">السابق</button>
              <span className="text-xs text-slate-500">صفحة {data.meta.current_page} من {data.meta.last_page}</span>
              <button disabled={page >= data.meta.last_page} onClick={() => setPage((p) => p + 1)} className="rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-xs font-bold text-slate-600 dark:text-slate-300 disabled:opacity-40">التالي</button>
            </div>
          )}
        </div>
      )}
    </div>
  )
}

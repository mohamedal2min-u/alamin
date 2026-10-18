'use client'

import { useState } from 'react'
import Link from 'next/link'
import { Syringe, Check, SkipForward, Undo2, AlertTriangle, Settings2 } from 'lucide-react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { vaccinationsApi, vaccineKeys } from '@/lib/api/vaccinations'
import { useFarmStore } from '@/stores/farm.store'
import { useCurrentRole } from '@/lib/roles'
import { cn, formatDate, getTodayLocalISO } from '@/lib/utils'
import { VACCINE_METHOD_LABELS, type FlockVaccination, type VaccinationStatus } from '@/types/vaccination'
import type { FlockStatus } from '@/types/flock'

interface Props {
  flockId: number
  flockStatus: FlockStatus
}

const STATUS_STYLE: Record<VaccinationStatus, { label: string; cls: string }> = {
  due:     { label: 'مستحق', cls: 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300' },
  done:    { label: 'تم',    cls: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' },
  skipped: { label: 'تُخطّي', cls: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' },
}

export function VaccinationsTab({ flockId, flockStatus }: Props) {
  const farmId = useFarmStore((s) => s.currentFarm?.id)
  const role = useCurrentRole()
  const canAct = flockStatus === 'active' && (role === 'farm_admin' || role === 'supervisor' || role === 'super_admin')
  const queryClient = useQueryClient()

  const [pending, setPending] = useState<{ id: number; status: 'done' | 'skipped' } | null>(null)
  const [note, setNote] = useState('')
  const [busy, setBusy] = useState(false)

  const { data, isLoading, isError } = useQuery({
    queryKey: vaccineKeys.flock(farmId, flockId),
    queryFn: () => vaccinationsApi.list(flockId),
    enabled: !!farmId,
    staleTime: 60_000,
  })

  const today = getTodayLocalISO()

  const submit = async (v: FlockVaccination, status: VaccinationStatus, withNote?: string) => {
    setBusy(true)
    try {
      const res = await vaccinationsApi.mark(flockId, v.id, status, withNote)
      toast.success(res.message)
      setPending(null)
      setNote('')
      queryClient.invalidateQueries({ queryKey: vaccineKeys.all(farmId) })
      queryClient.invalidateQueries({ queryKey: ['flock', flockId] })
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
      toast.error(msg ?? 'تعذّر الحفظ')
    } finally {
      setBusy(false)
    }
  }

  if (isLoading) {
    return <div className="p-6 text-center text-sm text-slate-400">جارٍ التحميل...</div>
  }
  if (isError || !data) {
    return <div className="p-6 text-center text-sm text-red-600">تعذّر تحميل اللقاحات</div>
  }

  const rows = data.data
  const noSaleBefore = data.withdrawal.no_sale_before

  return (
    <div className="space-y-3 p-4" dir="rtl">
      {noSaleBefore && noSaleBefore > today && (
        <div className="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-[12px] font-bold text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">
          <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
          <span>
            لا بيع قبل {formatDate(noSaleBefore)} — فترة سحب {data.withdrawal.source ?? ''}
          </span>
        </div>
      )}

      {role === 'farm_admin' && (
        <Link
          href="/vaccines"
          className="inline-flex items-center gap-1.5 text-xs font-bold text-primary-700 hover:underline dark:text-primary-400"
        >
          <Settings2 className="h-3.5 w-3.5" />
          تعديل برنامج اللقاحات
        </Link>
      )}

      {rows.length === 0 ? (
        <div className="flex flex-col items-center py-10 text-center text-slate-400">
          <Syringe className="mb-2 h-8 w-8 opacity-40" />
          <p className="text-sm">
            {flockStatus === 'active' ? 'برنامج اللقاحات فارغ' : 'لا لقاحات مسجلة لهذا الفوج'}
          </p>
        </div>
      ) : (
        <ul className="space-y-2">
          {rows.map((v) => {
            const overdue = v.status === 'due' && v.due_date < today
            const isToday = v.status === 'due' && v.due_date === today
            const st = STATUS_STYLE[v.status]
            return (
              <li
                key={v.id}
                className={cn(
                  'rounded-xl border bg-white px-3 py-2.5 dark:bg-slate-800',
                  overdue ? 'border-rose-200 dark:border-rose-900/60'
                    : isToday ? 'border-sky-300 dark:border-sky-800'
                    : 'border-slate-100 dark:border-slate-700',
                )}
              >
                <div className="flex items-start gap-3">
                  <div className="flex h-9 w-9 shrink-0 flex-col items-center justify-center rounded-lg bg-slate-50 dark:bg-slate-700">
                    <span className="text-[9px] font-bold text-slate-400">يوم</span>
                    <span className="text-sm font-black leading-none text-slate-700 dark:text-slate-100">{v.age_day}</span>
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-1.5">
                      <p className="text-[13px] font-bold text-slate-800 dark:text-slate-100">{v.title}</p>
                      <span className={cn('rounded-full px-2 py-0.5 text-[10px] font-bold', st.cls)}>{st.label}</span>
                      {overdue && <span className="text-[10px] font-bold text-rose-600">متأخر</span>}
                      {isToday && <span className="text-[10px] font-bold text-sky-600">اليوم</span>}
                    </div>
                    <p className="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                      {formatDate(v.due_date)} · {VACCINE_METHOD_LABELS[v.method] ?? v.method}
                      {v.withdrawal_days ? ` · سحب ${v.withdrawal_days} يوم` : ''}
                    </p>
                    {v.notes && <p className="mt-0.5 text-[11px] text-slate-400">{v.notes}</p>}
                    {v.status !== 'due' && (
                      <p className="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                        {v.done_by?.name ?? ''}{v.done_at ? ` · ${formatDate(v.done_at.slice(0, 10))}` : ''}
                        {v.note ? ` — ${v.note}` : ''}
                      </p>
                    )}
                  </div>

                  {canAct && v.status === 'due' && pending?.id !== v.id && (
                    <div className="flex shrink-0 gap-1.5">
                      <button
                        onClick={() => { setPending({ id: v.id, status: 'done' }); setNote('') }}
                        className="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-2.5 py-1.5 text-[11px] font-bold text-white active:scale-95"
                      >
                        <Check className="h-3.5 w-3.5" /> تم
                      </button>
                      <button
                        onClick={() => { setPending({ id: v.id, status: 'skipped' }); setNote('') }}
                        className="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-bold text-slate-600 active:scale-95 dark:border-slate-600 dark:text-slate-300"
                      >
                        <SkipForward className="h-3.5 w-3.5" /> تخطٍّ
                      </button>
                    </div>
                  )}
                  {canAct && v.status !== 'due' && (
                    <button
                      onClick={() => submit(v, 'due')}
                      disabled={busy}
                      title="تراجع"
                      className="shrink-0 rounded-lg p-1.5 text-slate-400 hover:bg-slate-50 hover:text-slate-600 disabled:opacity-50 dark:hover:bg-slate-700"
                    >
                      <Undo2 className="h-4 w-4" />
                    </button>
                  )}
                </div>

                {pending?.id === v.id && (
                  <div className="mt-2 flex gap-2">
                    <input
                      value={note}
                      onChange={(e) => setNote(e.target.value)}
                      maxLength={1000}
                      placeholder={pending.status === 'done' ? 'ملاحظة (رقم الدفعة...) — اختياري' : 'سبب التخطي — اختياري'}
                      className="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                    />
                    <button
                      onClick={() => submit(v, pending.status, note)}
                      disabled={busy}
                      className={cn(
                        'shrink-0 rounded-lg px-3 py-1.5 text-xs font-bold text-white disabled:opacity-50',
                        pending.status === 'done' ? 'bg-emerald-600' : 'bg-slate-600',
                      )}
                    >
                      {busy ? '...' : pending.status === 'done' ? 'تأكيد' : 'تخطٍّ'}
                    </button>
                    <button
                      onClick={() => setPending(null)}
                      className="shrink-0 rounded-lg px-2 py-1.5 text-xs font-bold text-slate-500"
                    >
                      إلغاء
                    </button>
                  </div>
                )}
              </li>
            )
          })}
        </ul>
      )}
    </div>
  )
}

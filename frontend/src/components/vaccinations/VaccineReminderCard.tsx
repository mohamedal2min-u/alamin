'use client'

import { useState } from 'react'
import { Syringe, Check } from 'lucide-react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { vaccinationsApi, vaccineKeys } from '@/lib/api/vaccinations'
import { useFarmStore } from '@/stores/farm.store'
import { useCurrentRole } from '@/lib/roles'
import { getTodayLocalISO } from '@/lib/utils'
import { VACCINE_METHOD_LABELS } from '@/types/vaccination'

/**
 * بطاقة تذكير: لقاحات اليوم والمتأخرة للفوج النشط مع زر «تم» بلمسة واحدة (مدير/مشرف).
 * لا تظهر إن لم يكن هناك شيء مستحق.
 */
export function VaccineReminderCard() {
  const farmId = useFarmStore((s) => s.currentFarm?.id)
  const role = useCurrentRole()
  const canAct = role === 'farm_admin' || role === 'supervisor' || role === 'super_admin'
  const queryClient = useQueryClient()
  const [busyId, setBusyId] = useState<number | null>(null)

  const { data } = useQuery({
    queryKey: vaccineKeys.active(farmId),
    queryFn: vaccinationsApi.active,
    enabled: !!farmId,
    staleTime: 5 * 60 * 1000,
    refetchOnWindowFocus: false,
  })

  const today = getTodayLocalISO()
  const rows = (data?.data ?? []).filter((v) => v.due_date <= today)
  if (!data?.flock || rows.length === 0) return null

  const markDone = async (id: number) => {
    setBusyId(id)
    try {
      await vaccinationsApi.mark(data.flock!.id, id, 'done')
      toast.success('تم تسجيل اللقاح')
      queryClient.invalidateQueries({ queryKey: vaccineKeys.all(farmId) })
      queryClient.invalidateQueries({ queryKey: ['flock', data.flock!.id] })
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
      toast.error(msg ?? 'تعذّر تسجيل اللقاح')
    } finally {
      setBusyId(null)
    }
  }

  return (
    <div className="rounded-2xl border border-sky-200 bg-sky-50/80 p-4 dark:border-sky-900/60 dark:bg-sky-950/30" dir="rtl">
      <div className="mb-3 flex items-center gap-2">
        <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-white shadow-sm dark:bg-slate-800">
          <Syringe className="h-4 w-4 text-sky-600" />
        </div>
        <h3 className="text-sm font-extrabold text-sky-900 dark:text-sky-200">لقاحات مستحقة</h3>
      </div>

      <ul className="space-y-2">
        {rows.map((v) => {
          const overdue = v.due_date < today
          return (
            <li
              key={v.id}
              className="flex items-center gap-3 rounded-xl bg-white px-3 py-2.5 shadow-sm dark:bg-slate-800"
            >
              <div className="min-w-0 flex-1">
                <p className="truncate text-[13px] font-bold text-slate-800 dark:text-slate-100">{v.title}</p>
                <p className="mt-0.5 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                  يوم {v.age_day} · {VACCINE_METHOD_LABELS[v.method] ?? v.method}
                  {overdue && <span className="ms-1.5 font-bold text-rose-600">متأخر ({v.due_date})</span>}
                </p>
              </div>
              {canAct && (
                <button
                  onClick={() => markDone(v.id)}
                  disabled={busyId !== null}
                  className="inline-flex shrink-0 items-center gap-1 rounded-xl bg-sky-600 px-3.5 py-2 text-xs font-bold text-white transition-all active:scale-95 disabled:opacity-50"
                >
                  <Check className="h-3.5 w-3.5" />
                  {busyId === v.id ? '...' : 'تم'}
                </button>
              )}
            </li>
          )
        })}
      </ul>
    </div>
  )
}

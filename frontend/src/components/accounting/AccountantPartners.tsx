'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Crown, Loader2, Lock, Users } from 'lucide-react'
import { partnersApi } from '@/lib/api/partners'
import type { Partner } from '@/lib/api/partners'
import { cn, formatNumber } from '@/lib/utils'
import type { Flock } from '@/types/flock'

/** الشركاء للمحاسب: يرى كل شريك وحصته وما يخصه من الربح، ويحدد الحصة. حصة المدير تُحسب تلقائياً. */
export function AccountantPartners({ flock, sharesLocked = false }: { flock: Flock | null; sharesLocked?: boolean }) {
  const qc = useQueryClient()
  const [editingId, setEditingId] = useState<number | null>(null)
  const [value, setValue] = useState('')
  const [error, setError] = useState<string | null>(null)

  const { data: partners = [], isLoading } = useQuery({
    queryKey: ['partners', 'accountant'],
    queryFn: () => partnersApi.getAll(),
    staleTime: 15_000,
  })

  const save = useMutation({
    mutationFn: ({ id, pct }: { id: number; pct: number }) => partnersApi.updateShare(id, pct),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['partners'] })
      setEditingId(null)
      setError(null)
    },
    onError: (e: unknown) => {
      setError(
        (e as { response?: { data?: { message?: string } } })?.response?.data?.message ??
          'تعذر حفظ الحصة، حاول مرة أخرى'
      )
    },
  })

  const total = partners.filter((p) => p.status === 'active').reduce((n, p) => n + (p.share_percent ?? 0), 0)
  const profit = flock?.net_profit ?? null

  const submit = (p: Partner) => {
    const pct = parseFloat(value)
    if (!Number.isFinite(pct) || pct < 0 || pct > 100) {
      setError('أدخل نسبة بين 0 و 100')
      return
    }
    save.mutate({ id: p.id, pct })
  }

  if (isLoading) {
    return <div className="flex justify-center py-16"><Loader2 className="h-6 w-6 animate-spin text-slate-400" /></div>
  }

  if (partners.length === 0) {
    return (
      <div className="flex flex-col items-center gap-2 py-16 text-slate-400">
        <Users className="h-12 w-12" />
        <p className="text-sm font-semibold">لا يوجد شركاء</p>
      </div>
    )
  }

  return (
    <div className="space-y-3" dir="rtl">
      {sharesLocked && (
        <div className="flex items-start gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-3.5 py-3 text-xs font-semibold text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
          <Lock className="mt-0.5 h-4 w-4 shrink-0" />
          نسب الشراكة مقفلة ما دام الفوج مفتوحاً. لا يعدّلها أحد إلا بعد إغلاق الفوج.
        </div>
      )}

      {/* مجموع الحصص */}
      <div className="grid grid-cols-2 gap-2.5">
        <div className="rounded-2xl border border-slate-200 bg-white p-3.5 dark:border-slate-700 dark:bg-slate-800">
          <div className="text-[11px] font-semibold text-slate-400">مجموع الحصص</div>
          <div className={cn('mt-1 text-xl font-extrabold tabular-nums', Math.round(total * 100) === 10000 ? 'text-emerald-600' : 'text-rose-600')}>
            {formatNumber(total)}%
          </div>
        </div>
        <div className="rounded-2xl border border-slate-200 bg-white p-3.5 dark:border-slate-700 dark:bg-slate-800">
          <div className="text-[11px] font-semibold text-slate-400">{flock ? `ربح ${flock.name}` : 'اختر فوجاً لرؤية الربح'}</div>
          <div className={cn('mt-1 text-xl font-extrabold tabular-nums', profit != null && profit < 0 ? 'text-rose-600' : 'text-slate-800 dark:text-slate-100')}>
            {profit != null ? formatNumber(profit) : '—'}
          </div>
        </div>
      </div>

      <ul className="space-y-3">
        {partners.map((p) => {
          const isEditing = editingId === p.id
          const amount = profit != null ? (profit * (p.share_percent ?? 0)) / 100 : null

          return (
            <li key={p.id} className={cn('rounded-2xl border bg-white p-4 shadow-sm dark:bg-slate-800', p.status === 'inactive' ? 'border-slate-200 opacity-70 dark:border-slate-700' : 'border-slate-200 dark:border-slate-700')}>
              <div className="flex items-center justify-between gap-3">
                <div className="flex min-w-0 items-center gap-2">
                  {p.is_manager && <Crown className="h-4 w-4 shrink-0 text-amber-500" />}
                  <h3 className="truncate text-[15px] font-bold text-slate-800 dark:text-slate-100">{p.name}</h3>
                </div>
                <span className="text-2xl font-extrabold tabular-nums text-primary-700 dark:text-primary-400">
                  {formatNumber(p.share_percent ?? 0)}%
                </span>
              </div>

              <div className="mt-1 flex items-center justify-between text-xs text-slate-400">
                <span>{p.is_manager ? 'مدير المزرعة — الباقي تلقائياً' : p.status === 'inactive' ? 'غير نشط' : 'شريك'}</span>
                {amount != null && (
                  <span className="font-semibold text-slate-600 dark:text-slate-300">
                    يخصه: {formatNumber(amount)}
                  </span>
                )}
              </div>

              {!p.is_manager && !sharesLocked && (
                isEditing ? (
                  <div className="mt-3 space-y-2">
                    <label className="text-xs font-semibold text-slate-500">الحصة (%)</label>
                    <div className="flex gap-2">
                      <input
                        autoFocus
                        type="number"
                        inputMode="decimal"
                        min={0}
                        max={100}
                        step="0.01"
                        value={value}
                        onChange={(e) => setValue(e.target.value)}
                        className="h-12 min-w-0 flex-1 rounded-xl border border-slate-300 px-3 text-lg font-bold text-slate-800 outline-none focus:border-primary-500"
                      />
                      <button
                        onClick={() => submit(p)}
                        disabled={save.isPending}
                        className="h-12 rounded-xl bg-primary-600 px-5 text-sm font-bold text-white active:scale-95 disabled:opacity-60"
                      >
                        {save.isPending ? '...' : 'حفظ'}
                      </button>
                      <button onClick={() => { setEditingId(null); setError(null) }} className="h-12 rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-500">
                        إلغاء
                      </button>
                    </div>
                    <p className="text-[11px] text-slate-400">تُحسب حصة المدير تلقائياً، ويُسجَّل كل تعديل باسمك.</p>
                    {error && <p className="text-xs font-semibold text-red-600">{error}</p>}
                  </div>
                ) : (
                  <button
                    onClick={() => { setEditingId(p.id); setValue(String(p.share_percent ?? 0)); setError(null) }}
                    className="mt-3 w-full rounded-xl border border-slate-200 bg-white py-2.5 text-sm font-bold text-slate-700 active:scale-[0.99] dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                  >
                    تحديد الحصة
                  </button>
                )
              )}
            </li>
          )
        })}
      </ul>
    </div>
  )
}

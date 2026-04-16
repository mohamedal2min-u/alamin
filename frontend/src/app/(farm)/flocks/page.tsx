'use client'

import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import Link from 'next/link'
import { Bird, Plus, AlertCircle, Zap } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { SkeletonCard } from '@/components/ui/Skeleton'
import { FlockCard } from '@/components/flocks/FlockCard'
import { FlockStatusBadge } from '@/components/flocks/FlockStatusBadge'
import { flocksApi } from '@/lib/api/flocks'
import { useFarmStore } from '@/stores/farm.store'
import { useCurrentRole } from '@/lib/roles'
import { formatDate, formatNumber } from '@/lib/utils'
import type { Flock } from '@/types/flock'

export default function FlocksPage() {
  const { currentFarm } = useFarmStore()
  const role = useCurrentRole()
  const canCreateFlock = role === 'farm_admin' || role === 'super_admin'
  const [activating, setActivating] = useState(false)
  const [activateError, setActivateError] = useState<string | null>(null)

  const {
    data,
    isLoading: loading,
    isError,
    refetch,
  } = useQuery({
    queryKey: ['flocks', currentFarm?.id],
    queryFn: () => flocksApi.list().then((res) => res.data),
    enabled: !!currentFarm,
    staleTime: 30_000,
  })

  const flocks: Flock[] = data ?? []
  const error = isError ? 'تعذّر تحميل قائمة الأفواج. تأكد من تشغيل الخادم.' : null

  const activeFlock = flocks.find((f) => f.status === 'active') ?? null
  const draftFlock = flocks.find((f) => f.status === 'draft') ?? null

  const handleActivate = async () => {
    if (!draftFlock) return
    setActivating(true)
    setActivateError(null)
    try {
      await flocksApi.update(draftFlock.id, { status: 'active' })
      refetch()
    } catch {
      setActivateError('تعذّر تفعيل الفوج، حاول مجدداً')
    } finally {
      setActivating(false)
    }
  }

  const activeFlocks = flocks.filter((f) => f.status === 'active' || f.status === 'draft')
  const closedFlocks = flocks.filter((f) => f.status === 'closed' || f.status === 'cancelled')

  return (
    <div className="space-y-5">
      {/* Page header — action only (title in global header) */}
      <div className="flex items-center justify-end">
        {canCreateFlock && (
          <Button asChild>
            <Link href="/flocks/new">
              <Plus className="h-4 w-4" />
              فوج جديد
            </Link>
          </Button>
        )}
      </div>

      {/* Loading skeletons */}
      {loading && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <SkeletonCard key={i} />
          ))}
        </div>
      )}

      {/* Error */}
      {!loading && error && (
        <div className="flex items-center gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 text-red-700">
          <AlertCircle className="h-5 w-5 shrink-0" />
          <p className="text-sm font-medium">{error}</p>
        </div>
      )}

      {/* Empty */}
      {!loading && !error && flocks.length === 0 && (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white py-16 text-center" style={{ boxShadow: 'var(--shadow-card)' }}>
          <Bird className="mb-4 h-12 w-12 text-slate-300" />
          <h3 className="text-base font-bold text-slate-700">لا توجد أفواج بعد</h3>
          <p className="mt-1 text-sm text-slate-500 font-medium">
            {canCreateFlock
              ? 'ابدأ بإنشاء فوجك الأول لهذه المزرعة'
              : 'لا توجد أفواج لهذه المزرعة حتى الآن'}
          </p>
          {canCreateFlock && (
            <Button asChild className="mt-5">
              <Link href="/flocks/new">
                <Plus className="h-4 w-4" />
                إنشاء فوج جديد
              </Link>
            </Button>
          )}
        </div>
      )}

      {!loading && !error && flocks.length > 0 && (
        <>
          {/* ── Draft activation banner ───────────────────────────── */}
          {canCreateFlock && draftFlock && (
            <div className="rounded-2xl bg-amber-50/80 border border-amber-100 p-5 dark:bg-amber-900/20 dark:border-amber-800/40">
              <div className="flex items-start gap-3.5">
                <div className="w-10 h-10 rounded-xl bg-white dark:bg-slate-800 flex items-center justify-center shrink-0 shadow-sm">
                  <Zap className="h-5 w-5 text-amber-500" />
                </div>
                <div className="flex-1 min-w-0">
                  <h3 className="font-extrabold text-amber-900 dark:text-amber-300 text-sm">
                    الفوج في انتظار التفعيل
                  </h3>
                  <p className="mt-1 text-[12px] font-medium text-amber-700/80 dark:text-amber-400/80 leading-relaxed">
                    فعّل الفوج للبدء في تسجيل البيانات التشغيلية.
                    {activeFlock && (
                      <span className="block mt-1 font-bold text-red-600">
                        ⚠️ لا يمكن تفعيل هذا الفوج قبل إغلاق الفوج النشط ( {activeFlock.name} )
                      </span>
                    )}
                  </p>
                  {activateError && (
                    <p className="mt-2 text-[11px] font-bold text-red-600">{activateError}</p>
                  )}
                  <button
                    onClick={handleActivate}
                    disabled={activating || !!activeFlock}
                    className="mt-3 inline-flex items-center justify-center rounded-xl bg-amber-600 px-5 py-2.5 text-xs font-bold text-white active:scale-[0.98] disabled:opacity-50 disabled:bg-slate-300 disabled:text-slate-500 disabled:shadow-none transition-all shadow-sm shadow-amber-200"
                  >
                    {activating ? 'جارٍ التفعيل...' : 'تفعيل الفوج'}
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* ── Active / Draft flocks ─────────────────────────────── */}
          {activeFlocks.length > 0 && (
            <section>
              <h2 className="mb-4 flex items-center gap-2 text-sm font-bold text-slate-800">
                <span className="h-2 w-2 rounded-full bg-green-500" />
                الأفواج النشطة
                <span className="ms-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">
                  {activeFlocks.length}
                </span>
              </h2>
              <div className="flex flex-col gap-4">
                {activeFlocks.map((flock) => (
                  <FlockCard key={flock.id} flock={flock} />
                ))}
              </div>
            </section>
          )}

          {/* ── Closed / Cancelled flocks table ──────────────────── */}
          {closedFlocks.length > 0 && (
            <section>
              <h2 className="mb-4 flex items-center gap-2 text-sm font-bold text-slate-800">
                <span className="h-2 w-2 rounded-full bg-slate-400" />
                الأفواج المغلقة
                <span className="ms-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                  {closedFlocks.length}
                </span>
              </h2>
              <div className="overflow-hidden rounded-2xl border border-slate-200/60 bg-white" style={{ boxShadow: 'var(--shadow-card)' }}>
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-slate-100 bg-slate-50 text-right text-xs font-semibold text-slate-500">
                      <th className="px-5 py-3">الفوج</th>
                      <th className="px-5 py-3">تاريخ البدء</th>
                      <th className="px-5 py-3">تاريخ الإغلاق</th>
                      <th className="px-5 py-3">العدد الأولي</th>
                      <th className="px-5 py-3">الحالة</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {closedFlocks.map((flock) => (
                      <tr key={flock.id} className="transition-colors duration-200 hover:bg-slate-50">
                        <td className="px-5 py-3">
                          <Link
                            href={`/flocks/${flock.id}`}
                            className="font-medium text-slate-800 hover:text-primary-600 transition-colors duration-200"
                          >
                            {flock.name}
                          </Link>
                        </td>
                        <td className="px-5 py-3 text-slate-500">
                          {formatDate(flock.start_date)}
                        </td>
                        <td className="px-5 py-3 text-slate-500">
                          {flock.end_date ? formatDate(flock.end_date) : '—'}
                        </td>
                        <td className="px-5 py-3 text-slate-500">
                          {formatNumber(flock.initial_count)}
                        </td>
                        <td className="px-5 py-3">
                          <FlockStatusBadge status={flock.status} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          )}
        </>
      )}
    </div>
  )
}

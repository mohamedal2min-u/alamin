'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { Bird, Plus, AlertCircle } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { SkeletonCard } from '@/components/ui/Skeleton'
import { FlockCard } from '@/components/flocks/FlockCard'
import { FlockStatusBadge } from '@/components/flocks/FlockStatusBadge'
import { flocksApi } from '@/lib/api/flocks'
import { useFarmStore } from '@/stores/farm.store'
import { formatDate, formatNumber } from '@/lib/utils'
import type { Flock } from '@/types/flock'

export default function FlocksPage() {
  const { currentFarm } = useFarmStore()
  const [flocks, setFlocks] = useState<Flock[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!currentFarm) return
    setLoading(true)
    setError(null)
    flocksApi
      .list()
      .then((res) => setFlocks(res.data))
      .catch((err: { response?: { status?: number } }) => {
        if (err?.response?.status === 404) {
          setFlocks([])
        } else {
          setError('تعذّر تحميل قائمة الأفواج. تأكد من تشغيل الخادم.')
        }
      })
      .finally(() => setLoading(false))
  }, [currentFarm])

  const activeFlocks = flocks.filter((f) => f.status === 'active' || f.status === 'draft')
  const closedFlocks = flocks.filter((f) => f.status === 'closed' || f.status === 'cancelled')

  return (
    <div className="space-y-8">
      {/* Page header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">الأفواج</h1>
          {currentFarm && (
            <p className="mt-0.5 text-sm text-slate-500">{currentFarm.name}</p>
          )}
        </div>
        <Button asChild>
          <Link href="/flocks/new">
            <Plus className="h-4 w-4" />
            فوج جديد
          </Link>
        </Button>
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
        <div className="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-5 text-red-700">
          <AlertCircle className="h-5 w-5 shrink-0" />
          <p className="text-sm">{error}</p>
        </div>
      )}

      {/* Empty */}
      {!loading && !error && flocks.length === 0 && (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white py-16 text-center">
          <Bird className="mb-4 h-12 w-12 text-slate-300" />
          <h3 className="text-lg font-semibold text-slate-700">لا توجد أفواج بعد</h3>
          <p className="mt-1 text-sm text-slate-500">ابدأ بإنشاء فوجك الأول لهذه المزرعة</p>
          <Button asChild className="mt-5">
            <Link href="/flocks/new">
              <Plus className="h-4 w-4" />
              إنشاء فوج جديد
            </Link>
          </Button>
        </div>
      )}

      {!loading && !error && flocks.length > 0 && (
        <>
          {/* ── Active / Draft flocks ─────────────────────────────── */}
          {activeFlocks.length > 0 && (
            <section>
              <h2 className="mb-4 flex items-center gap-2 text-base font-semibold text-slate-700">
                <span className="h-2.5 w-2.5 rounded-full bg-green-500" />
                الأفواج النشطة
                <span className="ms-1 rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700">
                  {activeFlocks.length}
                </span>
              </h2>
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {activeFlocks.map((flock) => (
                  <FlockCard key={flock.id} flock={flock} />
                ))}
              </div>
            </section>
          )}

          {/* ── Closed / Cancelled flocks table ──────────────────── */}
          {closedFlocks.length > 0 && (
            <section>
              <h2 className="mb-4 flex items-center gap-2 text-base font-semibold text-slate-700">
                <span className="h-2.5 w-2.5 rounded-full bg-slate-400" />
                الأفواج المغلقة
                <span className="ms-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                  {closedFlocks.length}
                </span>
              </h2>
              <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-slate-100 bg-slate-50 text-right text-xs font-medium text-slate-500">
                      <th className="px-5 py-3">الفوج</th>
                      <th className="px-5 py-3">تاريخ البدء</th>
                      <th className="px-5 py-3">تاريخ الإغلاق</th>
                      <th className="px-5 py-3">العدد الأولي</th>
                      <th className="px-5 py-3">الحالة</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {closedFlocks.map((flock) => (
                      <tr
                        key={flock.id}
                        className="transition hover:bg-slate-50"
                      >
                        <td className="px-5 py-3">
                          <Link
                            href={`/flocks/${flock.id}`}
                            className="font-medium text-slate-800 hover:text-primary-600"
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

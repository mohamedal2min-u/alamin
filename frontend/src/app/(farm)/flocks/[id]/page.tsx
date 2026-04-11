'use client'

import { use, useEffect, useState } from 'react'
import Link from 'next/link'
import { ArrowRight, AlertCircle, Bird, Calendar, Hash, Clock } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/Card'
import { Skeleton } from '@/components/ui/Skeleton'
import { FlockStatusBadge } from '@/components/flocks/FlockStatusBadge'
import { flocksApi } from '@/lib/api/flocks'
import { formatDate, formatNumber, cn } from '@/lib/utils'
import type { Flock } from '@/types/flock'

// ── Tabs ──────────────────────────────────────────────────────────────────────
type TabKey = 'mortalities' | 'feed' | 'medicine' | 'water' | 'notes' | 'sales'

const TABS: { key: TabKey; label: string; labelEn: string }[] = [
  { key: 'mortalities', label: 'النفوق',     labelEn: 'Mortalities' },
  { key: 'feed',        label: 'العلف',      labelEn: 'Feed' },
  { key: 'medicine',    label: 'الدواء',     labelEn: 'Medicine' },
  { key: 'water',       label: 'المياه',     labelEn: 'Water' },
  { key: 'notes',       label: 'الملاحظات', labelEn: 'Notes' },
  { key: 'sales',       label: 'المبيعات',  labelEn: 'Sales' },
]

// ── Loading skeleton ──────────────────────────────────────────────────────────
function FlockDetailsSkeleton() {
  return (
    <div className="space-y-6">
      <Skeleton className="h-5 w-32" />
      <div className="space-y-1">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-4 w-64" />
      </div>
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        {Array.from({ length: 4 }).map((_, i) => (
          <Card key={i}>
            <CardContent className="py-4">
              <Skeleton className="mb-2 h-3 w-20" />
              <Skeleton className="h-7 w-16" />
            </CardContent>
          </Card>
        ))}
      </div>
      <Skeleton className="h-10 w-full rounded-lg" />
      <Skeleton className="h-64 w-full rounded-xl" />
    </div>
  )
}

// ── Tab placeholder panel ─────────────────────────────────────────────────────
function TabPlaceholder({ tab }: { tab: TabKey }) {
  const labels: Record<TabKey, string> = {
    mortalities: 'سجلات النفوق',
    feed:        'سجلات العلف',
    medicine:    'سجلات الدواء',
    water:       'سجلات المياه',
    notes:       'الملاحظات',
    sales:       'المبيعات',
  }
  return (
    <div className="flex flex-col items-center justify-center py-20 text-center text-slate-400">
      <Bird className="mb-3 h-10 w-10 opacity-30" />
      <p className="text-base font-medium text-slate-600">{labels[tab]}</p>
      <p className="mt-1 text-sm">قيد الإنشاء — سيُضاف قريباً</p>
    </div>
  )
}

// ── Main component ────────────────────────────────────────────────────────────
export default function FlockDetailPage({
  params,
}: {
  params: Promise<{ id: string }>
}) {
  const { id } = use(params)
  const flockId = Number(id)

  const [flock, setFlock] = useState<Flock | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [activeTab, setActiveTab] = useState<TabKey>('mortalities')

  useEffect(() => {
    setLoading(true)
    flocksApi
      .get(flockId)
      .then((res) => setFlock(res.data))
      .catch((err: { response?: { status?: number } }) => {
        setError(err?.response?.status === 404 ? 'الفوج غير موجود' : 'تعذّر تحميل بيانات الفوج')
      })
      .finally(() => setLoading(false))
  }, [flockId])

  if (loading) return <FlockDetailsSkeleton />

  if (error) {
    return (
      <div className="space-y-4">
        <Link
          href="/flocks"
          className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900"
        >
          <ArrowRight className="h-4 w-4" />
          العودة للأفواج
        </Link>
        <div className="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-5 text-red-700">
          <AlertCircle className="h-5 w-5 shrink-0" />
          <p className="text-sm">{error}</p>
        </div>
      </div>
    )
  }

  if (!flock) return null

  return (
    <div className="space-y-6">
      {/* Breadcrumb */}
      <nav className="flex items-center gap-1.5 text-sm text-slate-500">
        <Link href="/flocks" className="hover:text-primary-600">الأفواج</Link>
        <span>/</span>
        <span className="font-medium text-slate-800">{flock.name}</span>
      </nav>

      {/* Title + status */}
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">{flock.name}</h1>
          <p className="mt-0.5 text-sm text-slate-500">
            بدأ في {formatDate(flock.start_date)}
            {flock.current_age_days !== null && ` · العمر: ${flock.current_age_days} يوم`}
          </p>
        </div>
        <FlockStatusBadge status={flock.status} />
      </div>

      {/* Stats cards */}
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <Card>
          <CardContent className="py-4">
            <div className="mb-1 flex items-center gap-1.5 text-xs text-slate-500">
              <Hash className="h-3.5 w-3.5" />
              العدد الأولي
            </div>
            <p className="text-2xl font-bold text-slate-900">
              {formatNumber(flock.initial_count)}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="py-4">
            <div className="mb-1 flex items-center gap-1.5 text-xs text-slate-500">
              <Calendar className="h-3.5 w-3.5" />
              تاريخ البدء
            </div>
            <p className="text-base font-semibold text-slate-900">
              {formatDate(flock.start_date)}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="py-4">
            <div className="mb-1 flex items-center gap-1.5 text-xs text-slate-500">
              <Clock className="h-3.5 w-3.5" />
              عمر الفوج
            </div>
            <p className="text-2xl font-bold text-slate-900">
              {flock.current_age_days !== null
                ? `${flock.current_age_days} يوم`
                : '—'}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="py-4">
            <div className="mb-1 flex items-center gap-1.5 text-xs text-slate-500">
              <Bird className="h-3.5 w-3.5" />
              الحالة
            </div>
            <div className="mt-1">
              <FlockStatusBadge status={flock.status} />
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Notes banner */}
      {flock.notes && (
        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          {flock.notes}
        </div>
      )}

      {/* Tabs */}
      <div>
        {/* Tab bar */}
        <div className="border-b border-slate-200">
          <nav className="-mb-px flex gap-0 overflow-x-auto">
            {TABS.map(({ key, label }) => (
              <button
                key={key}
                onClick={() => setActiveTab(key)}
                className={cn(
                  'whitespace-nowrap border-b-2 px-5 py-3 text-sm font-medium transition-colors',
                  activeTab === key
                    ? 'border-primary-600 text-primary-700'
                    : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800'
                )}
              >
                {label}
              </button>
            ))}
          </nav>
        </div>

        {/* Tab content */}
        <div className="mt-0 rounded-b-xl rounded-tr-xl border border-t-0 border-slate-200 bg-white">
          <TabPlaceholder tab={activeTab} />
        </div>
      </div>
    </div>
  )
}

'use client'

import { use, useState } from 'react'
import Link from 'next/link'
import { ArrowRight, AlertCircle, AlertTriangle, Bird, Calendar, Hash, Clock, Lock, Zap, Edit2 } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/Card'
import { Skeleton } from '@/components/ui/Skeleton'
import { FlockStatusBadge } from '@/components/flocks/FlockStatusBadge'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { flocksApi } from '@/lib/api/flocks'
import { accountingApi } from '@/lib/api/accounting'
import { OverviewTab } from '@/components/flocks/tabs/OverviewTab'
import { SalesTab } from '@/components/flocks/tabs/SalesTab'
import { FeedTab } from '@/components/flocks/tabs/FeedTab'
import { MedicineTab } from '@/components/flocks/tabs/MedicineTab'
import { ExpensesTab } from '@/components/flocks/tabs/ExpensesTab'
import { WaterTab } from '@/components/flocks/tabs/WaterTab'
import { NotesTab } from '@/components/flocks/tabs/NotesTab'
import { CloseFlockDialog } from '@/components/flocks/CloseFlockDialog'
import { EditFlockModal } from '@/components/flocks/EditFlockModal'
import { formatDate, formatNumber, cn } from '@/lib/utils'
import type { Flock } from '@/types/flock'

// ── Tabs ──────────────────────────────────────────────────────────────────────
type TabKey = 'overview' | 'feed' | 'medicine' | 'water' | 'expenses' | 'notes' | 'sales'

const TABS: { key: TabKey; label: string; labelEn: string }[] = [
  { key: 'overview',    label: 'نظرة عامة',  labelEn: 'Overview' },
  { key: 'feed',        label: 'العلف',      labelEn: 'Feed' },
  { key: 'medicine',    label: 'الدواء',     labelEn: 'Medicine' },
  { key: 'water',       label: 'المياه',     labelEn: 'Water' },
  { key: 'expenses',    label: 'المصروفات',  labelEn: 'Expenses' },
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

// ── Tab placeholder panel (only for tabs not yet implemented) ─────────────────
function TabPlaceholder({ tab }: { tab: TabKey }) {
  const labels: Record<TabKey, string> = {
    overview:    'النظرة العامة',
    feed:        'سجلات العلف',
    medicine:    'سجلات الدواء',
    water:       'سجلات المياه',
    expenses:    'المصروفات',
    notes:       'الملاحظات',
    sales:       'المبيعات',
  }
  return (
    <div className="flex flex-col items-center justify-center py-20 text-center text-slate-400">
      <Bird className="mb-3 h-10 w-10 opacity-30" />
      <p className="text-base font-medium text-slate-600">{labels[tab]}</p>
      <p className="mt-1 text-sm">سيُضاف قريباً</p>
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

  const queryClient = useQueryClient()
  const [activeTab, setActiveTab] = useState<TabKey>('overview')
  const [closeDialogOpen, setCloseDialogOpen] = useState(false)
  const [editModalOpen, setEditModalOpen] = useState(false)

  const {
    data: flockData,
    isLoading: loading,
    error: fetchError,
  } = useQuery({
    queryKey: ['flock', flockId],
    queryFn: () => flocksApi.get(flockId),
    staleTime: 60_000,
    gcTime: 10 * 60 * 1000,
    retry: (_, err: any) => err?.response?.status !== 404,
  })

  const flock = flockData?.data ?? null
  const error = fetchError
    ? ((fetchError as any)?.response?.status === 404 ? 'الفوج غير موجود' : 'تعذّر تحميل بيانات الفوج')
    : null

  const { data: reviewData } = useQuery({
    queryKey: ['accounting', 'review-queue', { flock_id: flockId, filter: 'blocking' }],
    queryFn: () => accountingApi.getReviewQueue({ flock_id: flockId, filter: 'blocking' }),
    enabled: flock?.status === 'active',
    staleTime: 30_000,
  })
  const blockingCount = reviewData?.summary.blocking_flock_closure_count ?? 0

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
        <div className="flex items-center gap-3">
          {(flock.status === 'active' || flock.status === 'draft') && (
            <button
              onClick={() => setEditModalOpen(true)}
              className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-colors"
            >
              <Edit2 className="h-3.5 w-3.5" />
              تعديل البيانات
            </button>
          )}
          <FlockStatusBadge status={flock.status} />
          {flock.status === 'active' && (
            <button
              onClick={() => setCloseDialogOpen(true)}
              className="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 transition-colors"
            >
              <Lock className="h-3.5 w-3.5" />
              إغلاق الفوج
            </button>
          )}
        </div>
      </div>

      {/* Stats cards */}
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
        <Card>
          <CardContent className="py-4">
            <div className="mb-1 flex items-center gap-1.5 text-xs text-slate-500">
              <Hash className="h-3.5 w-3.5" />
              العدد الأولي
            </div>
            <p className="text-xl font-bold text-slate-900">
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
            <p className="text-sm font-semibold text-slate-900 leading-tight">
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
            <p className="text-xl font-bold text-slate-900">
              {flock.current_age_days !== null
                ? `${flock.current_age_days} يوم`
                : '—'}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="py-4">
            <div className="mb-1 flex items-center gap-1.5 text-xs text-slate-500">
              <span className="text-[10px] font-bold text-primary-500">$</span>
              سعر الصوص
            </div>
            <p className="text-xl font-black text-primary-700">
              {flock.chick_unit_price ? `$${flock.chick_unit_price}` : '—'}
            </p>
          </CardContent>
        </Card>

        <Card className="bg-primary-50/30 border-primary-100">
          <CardContent className="py-4">
            <div className="mb-1 flex items-center gap-1.5 text-xs text-slate-600">
              <Zap className="h-3.5 w-3.5 text-primary-500" />
              إجمالي الاستثمار
            </div>
            <p className="text-xl font-black text-primary-700">
              {flock.total_expenses 
                ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(flock.total_expenses)
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
            <div className="mt-0.5">
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

      {/* Blocking records banner */}
      {flock.status === 'active' && blockingCount > 0 && (
        <Link
          href={`/accounting?tab=review&filter=blocking&flock_id=${flock.id}`}
          className="flex items-center gap-3 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700 hover:bg-red-100 transition-colors"
        >
          <AlertTriangle className="h-4 w-4 shrink-0" />
          <span>
            يوجد <strong>{blockingCount}</strong> سجل مالي غير مسدد يمنع إغلاق هذا الفوج
          </span>
          <span className="me-auto text-xs font-medium underline">عرض السجلات المانعة ←</span>
        </Link>
      )}

      {/* Close flock dialog */}
      {closeDialogOpen && (
        <CloseFlockDialog
          flock={flock}
          isOpen={closeDialogOpen}
          onClose={() => setCloseDialogOpen(false)}
          onSuccess={(updated) => {
            queryClient.setQueryData(['flock', flockId], { data: updated })
            setCloseDialogOpen(false)
          }}
        />
      )}

      {/* Edit flock modal */}
      {editModalOpen && (
        <EditFlockModal
          flock={flock}
          isOpen={editModalOpen}
          onClose={() => setEditModalOpen(false)}
          onSuccess={(updated) => {
            queryClient.setQueryData(['flock', flockId], { data: updated })
            setEditModalOpen(false)
          }}
        />
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
          {activeTab === 'overview' ? (
            <OverviewTab flockId={flockId} flockStatus={flock.status} flockName={flock.name} />
          ) : activeTab === 'feed' ? (
            <FeedTab flockId={flockId} flockStatus={flock.status} />
          ) : activeTab === 'medicine' ? (
            <MedicineTab flockId={flockId} flockStatus={flock.status} />
          ) : activeTab === 'water' ? (
            <WaterTab flockId={flockId} flockStatus={flock.status} />
          ) : activeTab === 'expenses' ? (
            <ExpensesTab flockId={flockId} flockStatus={flock.status} />
          ) : activeTab === 'notes' ? (
            <NotesTab flockId={flockId} flockStatus={flock.status} />
          ) : activeTab === 'sales' ? (
            <SalesTab flockId={flockId} flockStatus={flock.status} />
          ) : (
            <TabPlaceholder tab={activeTab} />
          )}
        </div>
      </div>
    </div>
  )
}

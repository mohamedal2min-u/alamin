'use client'

import { Suspense } from 'react'
import { useSearchParams, useRouter } from 'next/navigation'
import { useQuery } from '@tanstack/react-query'
import { Bird } from 'lucide-react'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/Tabs'
import { AccountingReportTab } from '@/components/reports/tabs/AccountingReportTab'
import { ReviewQueueTab } from '@/components/accounting/ReviewQueueTab'
import { Skeleton } from '@/components/ui/Skeleton'
import { reportsApi } from '@/lib/api/reports'

function AccountingPageInner() {
  const searchParams = useSearchParams()
  const router = useRouter()

  const tab     = searchParams.get('tab') ?? 'summary'
  const filter  = searchParams.get('filter') ?? undefined
  const flockId = searchParams.get('flock_id') ?? undefined

  const { data: accountingData, isLoading: isAccountingLoading } = useQuery({
    queryKey: ['reports', 'accounting', {}],
    queryFn: () => reportsApi.getAccountingSummary({}),
    staleTime: 30_000,
    enabled: tab === 'summary',
  })

  const flockInfo = accountingData?.flock ?? null

  return (
    <div className="space-y-5 pb-20 sm:pb-8" dir="rtl">
      <div>
        <h1 className="text-2xl font-bold text-slate-800">المحاسبة</h1>
        <p className="text-sm text-slate-500 mt-1">ملخص مالي وقائمة الذمم والمراجعة</p>
      </div>

      <Tabs value={tab} onValueChange={(v) => router.push(`/accounting?tab=${v}`)}>
        <TabsList>
          <TabsTrigger value="summary">ملخص المحاسبة</TabsTrigger>
          <TabsTrigger value="review">الذمم والمراجعة</TabsTrigger>
        </TabsList>

        <TabsContent value="summary">
          {!isAccountingLoading && !flockInfo ? (
            <div className="flex flex-col items-center justify-center py-20 text-center text-slate-400 gap-3">
              <Bird className="h-12 w-12 opacity-30" />
              <p className="text-sm font-medium">لا يوجد فوج بعد</p>
              <p className="text-xs">أنشئ فوجاً أولاً لعرض ملخص المحاسبة</p>
            </div>
          ) : (
            <>
              {flockInfo && (
                <div className="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-600">
                  <Bird className="h-4 w-4 text-slate-400 shrink-0" />
                  <span>البيانات تخص الفوج:</span>
                  <span className="font-semibold text-slate-800">{flockInfo.name}</span>
                  <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${flockInfo.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>
                    {flockInfo.status === 'active' ? 'نشط' : 'مغلق'}
                  </span>
                </div>
              )}
              <AccountingReportTab
                data={accountingData ?? null}
                isLoading={isAccountingLoading}
              />
            </>
          )}
        </TabsContent>

        <TabsContent value="review">
          <ReviewQueueTab initialFlockId={flockId} initialFilter={filter} />
        </TabsContent>
      </Tabs>
    </div>
  )
}

export default function AccountingPage() {
  return (
    <Suspense fallback={<Skeleton className="h-64 w-full rounded-xl" />}>
      <AccountingPageInner />
    </Suspense>
  )
}


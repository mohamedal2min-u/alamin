'use client'

import { Suspense } from 'react'
import { useSearchParams } from 'next/navigation'
import { useQuery } from '@tanstack/react-query'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/Tabs'
import { AccountingReportTab } from '@/components/reports/tabs/AccountingReportTab'
import { ReviewQueueTab } from '@/components/accounting/ReviewQueueTab'
import { Skeleton } from '@/components/ui/Skeleton'
import { reportsApi } from '@/lib/api/reports'

function AccountingPageInner() {
  const searchParams = useSearchParams()

  const tab     = searchParams.get('tab') ?? 'summary'
  const filter  = searchParams.get('filter') ?? undefined
  const flockId = searchParams.get('flock_id') ?? undefined

  const { data: accountingData, isLoading: isAccountingLoading } = useQuery({
    queryKey: ['reports', 'accounting', {}],
    queryFn: () => reportsApi.getAccountingSummary({}),
    staleTime: 30_000,
    enabled: tab === 'summary',
  })

  return (
    <div className="space-y-5 pb-20 sm:pb-8" dir="rtl">
      <div>
        <h1 className="text-2xl font-bold text-slate-800">المحاسبة</h1>
        <p className="text-sm text-slate-500 mt-1">ملخص مالي وقائمة الذمم والمراجعة</p>
      </div>

      <Tabs defaultValue={tab}>
        <TabsList>
          <TabsTrigger value="summary">ملخص المحاسبة</TabsTrigger>
          <TabsTrigger value="review">الذمم والمراجعة</TabsTrigger>
        </TabsList>

        <TabsContent value="summary">
          <AccountingReportTab
            data={accountingData ?? null}
            isLoading={isAccountingLoading}
          />
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

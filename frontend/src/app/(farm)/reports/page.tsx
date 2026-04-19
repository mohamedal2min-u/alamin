'use client'

import React, { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { reportsApi } from '@/lib/api/reports'
import { useFarmStore } from '@/stores/farm.store'
import { KpiSection } from '@/components/reports/KpiSection'
import { FiltersBar } from '@/components/reports/FiltersBar'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/Tabs'
import { FlockReportTab } from '@/components/reports/tabs/FlockReportTab'
import { AccountingReportTab } from '@/components/reports/tabs/AccountingReportTab'
import { InventoryReportTab } from '@/components/reports/tabs/InventoryReportTab'
import { PartnersReportTab } from '@/components/reports/tabs/PartnersReportTab'
import { WorkersReportTab } from '@/components/reports/tabs/WorkersReportTab'
import { DailyReportTab } from '@/components/reports/tabs/DailyReportTab'
import { Card } from '@/components/ui/Card'
import { Loader2, Package, Users, CalendarDays, UserCheck } from 'lucide-react'

export default function ReportsPage() {
  const { activeFlock } = useFarmStore()
  const [activeTab, setActiveTab] = useState('flock')
  const [filters, setFilters] = useState<any>({
    flock_id: activeFlock?.id?.toString() || '',
    start_date: '',
    end_date: '',
    query: ''
  })

  // 1. Fetch Summary KPIs (parallel with flock report)
  const { data: summaryData, isLoading: isSummaryLoading } = useQuery({
    queryKey: ['reports', 'summary-kpis'],
    queryFn: () => reportsApi.getSummaryKpis(),
    staleTime: 30_000,
  })

  // Fallback: if store had no cached flock, use summaryData once it loads
  React.useEffect(() => {
    if (summaryData?.active_flock_id && !filters.flock_id) {
      setFilters((prev: any) => ({ ...prev, flock_id: summaryData.active_flock_id!.toString() }))
    }
  }, [summaryData, filters.flock_id])

  // 2. Fetch Detailed Reports
  const { data: flockReport, isLoading: isFlockLoading } = useQuery({
    queryKey: ['reports', 'flock', filters.flock_id],
    queryFn: () => reportsApi.getFlockReport(Number(filters.flock_id)),
    enabled: !!filters.flock_id && activeTab === 'flock',
    staleTime: 30_000,
  })

  const { data: accountingData, isLoading: isAccountingLoading } = useQuery({
    queryKey: ['reports', 'accounting', filters],
    queryFn: () => reportsApi.getAccountingSummary(filters),
    enabled: activeTab === 'accounting',
    staleTime: 30_000,
  })

  const { data: inventoryData, isLoading: isInventoryLoading } = useQuery({
    queryKey: ['reports', 'inventory'],
    queryFn: () => reportsApi.getInventoryReport(),
    enabled: activeTab === 'inventory',
    staleTime: 30_000,
  })

  const { data: partnersData, isLoading: isPartnersLoading } = useQuery({
    queryKey: ['reports', 'partners'],
    queryFn: () => reportsApi.getPartnersReport(),
    enabled: activeTab === 'partners',
    staleTime: 30_000,
  })

  const { data: workersData, isLoading: isWorkersLoading } = useQuery({
    queryKey: ['reports', 'workers'],
    queryFn: () => reportsApi.getWorkersReport(),
    enabled: activeTab === 'workers',
    staleTime: 30_000,
  })

  const { data: dailyData, isLoading: isDailyLoading } = useQuery({
    queryKey: ['reports', 'daily', filters.start_date],
    queryFn: () => reportsApi.getDailyReport(filters.start_date),
    enabled: activeTab === 'daily',
    staleTime: 30_000,
  })

  return (
    <div className="space-y-5 pb-20 sm:pb-8">
      {/* KPI Section */}
      <KpiSection data={summaryData || null} isLoading={isSummaryLoading} />

      {/* Filters Bar */}
      <FiltersBar filters={filters} onFilterChange={setFilters} />

      {/* Main Reports Area */}
      <div className="reports-container">
        <Tabs defaultValue="flock" onValueChange={setActiveTab}>
          <TabsList>
            <TabsTrigger value="flock">تقرير الفوج</TabsTrigger>
            <TabsTrigger value="accounting">المحاسبة</TabsTrigger>
            <TabsTrigger value="inventory">المخزون</TabsTrigger>
            <TabsTrigger value="partners">الشركاء</TabsTrigger>
            <TabsTrigger value="workers">العمال</TabsTrigger>
            <TabsTrigger value="daily">اليومي</TabsTrigger>
          </TabsList>

          <div className="mt-5 bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/60 min-h-[400px]" style={{ boxShadow: 'var(--shadow-card)' }}>
            {/* 1. Flock Report Tab */}
            <TabsContent value="flock">
              <FlockReportTab data={flockReport || null} isLoading={isFlockLoading} />
            </TabsContent>

            {/* 2. Accounting Report Tab */}
            <TabsContent value="accounting">
              <AccountingReportTab data={accountingData || null} isLoading={isAccountingLoading} />
            </TabsContent>

            {/* 3. Inventory Report Tab */}
            <TabsContent value="inventory">
              <InventoryReportTab data={inventoryData || null} isLoading={isInventoryLoading} />
            </TabsContent>

            {/* 4. Partners Report Tab */}
            <TabsContent value="partners">
              <PartnersReportTab data={partnersData || null} isLoading={isPartnersLoading} />
            </TabsContent>

            {/* 5. Workers Report Tab */}
            <TabsContent value="workers">
                <WorkersReportTab data={workersData || null} isLoading={isWorkersLoading} />
            </TabsContent>

            {/* 6. Daily Report Tab */}
            <TabsContent value="daily">
                <DailyReportTab data={dailyData || null} isLoading={isDailyLoading} />
            </TabsContent>
          </div>
        </Tabs>
      </div>

      {/* Print Specific Footer */}
      <div className="hidden print:block mt-8 pt-4 border-t border-slate-200 text-center text-[10px] text-slate-400">
        تم استخراج هذا التقرير آلياً من نظام «YMD» بتاريخ {new Date().toLocaleDateString('ar-EG')}
      </div>
    </div>
  )
}

function PlaceholderView({ icon: Icon, title, description }: { icon: any, title: string, description: string }) {
  return (
    <div className="h-64 flex flex-col items-center justify-center text-center p-8">
      <div className="w-14 h-14 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-300 mb-4">
        <Icon className="w-7 h-7" />
      </div>
      <h3 className="text-sm font-bold text-slate-900 mb-1">{title}</h3>
      <p className="text-xs text-slate-500 max-w-[200px]">{description}</p>
    </div>
  )
}



'use client'

import React from 'react'
import { useQuery } from '@tanstack/react-query'
import { reportsApi } from '@/lib/api/reports'
import { Skeleton } from '@/components/ui/Skeleton'
import { formatDate, formatNumber } from '@/lib/utils'
import { AlertCircle, FileText, TrendingUp, Bird, Wheat, Pill, DollarSign, Printer } from 'lucide-react'

interface DailySummaryItem {
  day: number
  date: string
  mortality: number
  feed: number
  medicine: number
  expense: number
}

interface OverviewTabProps {
  flockId: number
  flockStatus: string
  flockName: string
}

export function OverviewTab({ flockId, flockName }: OverviewTabProps) {
  const [visibleCount, setVisibleCount] = React.useState(7)
  
  const { data, isLoading, error } = useQuery({
    queryKey: ['flock-daily-summary', flockId],
    queryFn: () => reportsApi.getFlockDailySummary(flockId),
  })

  const allRecords: DailySummaryItem[] = data?.data ?? []
  
  // Filter for past days or today only (safety measure)
  const todayStr = new Date().toISOString().split('T')[0]
  const filteredRecords = allRecords.filter(r => r.date <= todayStr)
  
  const visibleRecords = filteredRecords.slice(0, visibleCount)
  const hasMore = filteredRecords.length > visibleCount

  if (isLoading) {
    return (
      <div className="p-6 space-y-4">
        <Skeleton className="h-10 w-full" />
        <Skeleton className="h-64 w-full" />
      </div>
    )
  }

  if (error) {
    return (
      <div className="flex items-center gap-3 p-8 text-red-600 bg-red-50 rounded-lg">
        <AlertCircle className="h-5 w-5" />
        <p className="text-sm font-medium">تعذر تحميل خلاصة البيانات اليومية</p>
      </div>
    )
  }

  if (filteredRecords.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-20 text-slate-400">
        <FileText className="mb-3 h-12 w-12 opacity-20" />
        <p className="text-sm font-medium">لا توجد سجلات لهذا الفوج حتى الآن</p>
      </div>
    )
  }

  // Calculate Totals (on ALL filtered records, not just visible ones)
  const totals = filteredRecords.reduce(
    (acc, curr) => ({
      mortality: acc.mortality + curr.mortality,
      feed: acc.feed + curr.feed,
      medicine: acc.medicine + curr.medicine,
      expense: acc.expense + curr.expense,
    }),
    { mortality: 0, feed: 0, medicine: 0, expense: 0 }
  )

  // Helper for feed display
  const renderFeed = (val: number) => {
    if (val <= 0) return '—'
    if (val >= 50) {
      const bags = val / 50
      return `${formatNumber(bags)} كيس`
    }
    return `${formatNumber(val)} كجم`
  }

  return (
    <div className="overflow-hidden">
      {/* Print-only Header */}
      <div className="hidden print:block p-6 border-b-2 border-slate-800 mb-6">
        <div className="flex justify-between items-end">
          <div>
            <h2 className="text-2xl font-black text-slate-900">{flockName}</h2>
            <p className="text-sm text-slate-500 mt-1">تقرير النظرة العامة اليومية للأفواج</p>
          </div>
          <div className="text-left text-xs text-slate-400">
            تاريخ الطباعة: {new Date().toLocaleDateString('ar-EG')}
          </div>
        </div>
      </div>

      <div className="p-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between no-print">
        <div className="flex items-center gap-2">
          <TrendingUp className="h-4 w-4 text-primary-600" />
          <h3 className="font-bold text-slate-800 text-sm">النظرة العامة اليومية</h3>
        </div>
        <div className="flex items-center gap-3">
          <button
            onClick={() => window.print()}
            className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors shadow-sm"
          >
            <Printer className="h-3.5 w-3.5" />
            طباعة الجدول
          </button>
          <div className="text-[10px] text-slate-500 font-medium bg-white border border-slate-200 px-2 py-0.5 rounded-full">
            إجمالي {filteredRecords.length} يوم
          </div>
        </div>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full text-right text-sm">
          <thead>
            <tr className="bg-slate-50 text-slate-600 border-b border-slate-200">
              <th className="px-4 py-3 font-bold text-center w-20">العمر</th>
              <th className="px-4 py-3 font-bold text-center">التاريخ</th>
              <th className="px-4 py-3 font-bold text-center">
                <div className="flex items-center justify-center gap-1.5">
                  <Bird className="h-3.5 w-3.5 text-red-500" />
                  النفوق
                </div>
              </th>
              <th className="px-4 py-3 font-bold text-center">
                <div className="flex items-center justify-center gap-1.5">
                  <Wheat className="h-3.5 w-3.5 text-amber-500" />
                  استهلاك العلف
                </div>
              </th>
              <th className="px-4 py-3 font-bold text-center">
                <div className="flex items-center justify-center gap-1.5">
                  <Pill className="h-3.5 w-3.5 text-blue-500" />
                  استهلاك الدواء
                </div>
              </th>
              <th className="px-4 py-3 font-bold text-center">
                <div className="flex items-center justify-center gap-1.5">
                  <DollarSign className="h-3.5 w-3.5 text-emerald-600" />
                  المصروف
                </div>
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {visibleRecords.map((row) => (
              <tr key={row.date} className="hover:bg-slate-50 transition-colors">
                <td className="px-4 py-3 text-center font-bold text-slate-900 bg-slate-50/30">
                  {row.day}
                </td>
                <td className="px-4 py-3 text-center text-slate-500 whitespace-nowrap">
                  {formatDate(row.date)}
                </td>
                <td className={cn(
                  "px-4 py-3 text-center font-bold",
                  row.mortality > 0 ? "text-red-600" : "text-slate-300"
                )}>
                  {row.mortality > 0 ? formatNumber(row.mortality) : '—'}
                </td>
                <td className={cn(
                  "px-4 py-3 text-center font-bold",
                  row.feed > 0 ? "text-amber-700" : "text-slate-300"
                )}>
                  {renderFeed(row.feed)}
                </td>
                <td className={cn(
                  "px-4 py-3 text-center font-bold",
                  row.medicine > 0 ? "text-blue-700" : "text-slate-300"
                )}>
                  {row.medicine > 0 ? formatNumber(row.medicine) : '—'}
                </td>
                <td className={cn(
                  "px-4 py-3 text-center font-bold",
                  row.expense > 0 ? "text-emerald-700" : "text-slate-300"
                )}>
                  {row.expense > 0 ? `$${formatNumber(row.expense)}` : '—'}
                </td>
              </tr>
            ))}
          </tbody>
          <tfoot>
            {hasMore ? (
              <tr className="bg-white border-t border-slate-100">
                <td colSpan={6} className="px-4 py-3">
                  <button 
                    onClick={() => setVisibleCount(prev => prev + 10)}
                    className="w-full py-2 text-primary-600 font-bold hover:bg-primary-50 rounded-lg transition-colors border border-dashed border-primary-200 no-print"
                  >
                    عرض المزيد (+10 أيام)
                  </button>
                </td>
              </tr>
            ) : null}
            <tr className="bg-primary-50/50 border-t-2 border-primary-100 font-black text-slate-900">
              <td colSpan={2} className="px-4 py-4 text-center text-primary-700">المجموع الإجمالي</td>
              <td className="px-4 py-4 text-center text-red-700 border-x border-primary-100/30">{formatNumber(totals.mortality)}</td>
              <td className="px-4 py-4 text-center text-amber-800 border-x border-primary-100/30">{renderFeed(totals.feed)}</td>
              <td className="px-4 py-4 text-center text-blue-800 border-x border-primary-100/30">{formatNumber(totals.medicine)}</td>
              <td className="px-4 py-4 text-center text-emerald-700 border-x border-primary-100/30">${formatNumber(totals.expense)}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  )
}

function cn(...classes: any[]) {
  return classes.filter(Boolean).join(' ')
}


'use client'

import { Card } from "@/components/ui/Card"
import { TrendingUp, TrendingDown, Package, Activity, Layers, DollarSign } from "lucide-react"
import { type SummaryKpis } from "@/lib/api/reports"

interface KpiSectionProps {
  data: SummaryKpis | null
  isLoading: boolean
}

export const KpiSection = ({ data, isLoading }: KpiSectionProps) => {
  if (isLoading) {
    return (
      <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
        {[...Array(6)].map((_, i) => (
          <div key={i} className="h-24 animate-pulse rounded-2xl bg-slate-200/60" />
        ))}
      </div>
    )
  }

  if (!data) return null

  const items = [
    { label: 'عدد الأفواج', value: data.total_flocks_count, icon: Layers, color: 'text-blue-600', bg: 'bg-blue-50' },
    { label: 'الفوج النشط', value: data.active_flock_name || 'لا يوجد', icon: Activity, color: 'text-orange-600', bg: 'bg-orange-50' },
    { label: 'إجمالي المبيعات', value: data.total_sales, icon: TrendingUp, color: 'text-emerald-600', bg: 'bg-emerald-50', isMoney: true },
    { label: 'إجمالي المصاريف', value: data.total_expenses, icon: TrendingDown, color: 'text-rose-600', bg: 'bg-rose-50', isMoney: true },
    { label: 'صافي الربح', value: data.net_profit, icon: DollarSign, color: 'text-teal-600', bg: 'bg-teal-50', isMoney: true },
    { label: 'قيمة المخزون', value: data.inventory_value, icon: Package, color: 'text-sky-600', bg: 'bg-sky-50', isMoney: true },
  ]

  return (
    <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
      {items.map((item, idx) => (
        <Card key={idx} className="p-4 flex flex-col justify-between">
          <div className="flex items-center justify-between gap-2">
            <span className="text-[10px] sm:text-xs font-semibold text-slate-500 truncate">{item.label}</span>
            <div className={`p-1.5 rounded-lg ${item.bg} ${item.color}`}>
              <item.icon className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="mt-2">
            <span className="text-sm sm:text-lg font-bold text-slate-900 leading-none tabular-nums">
              {item.isMoney ? item.value.toLocaleString() : item.value}
            </span>
            {item.isMoney && (
              <span className="text-[10px] mr-1 text-slate-400 font-medium">USD</span>
            )}
          </div>
        </Card>
      ))}
    </div>
  )
}

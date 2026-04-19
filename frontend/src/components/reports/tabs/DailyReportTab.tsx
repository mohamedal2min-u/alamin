'use client'

import { Card } from "@/components/ui/Card"
import { Badge } from "@/components/ui/Badge"
import { CalendarDays, Skull, Utensils, DollarSign, TrendingUp, Clock } from "lucide-react"

interface DailyReportTabProps {
  data: any
  isLoading: boolean
}

export const DailyReportTab = ({ data, isLoading }: DailyReportTabProps) => {
  if (isLoading) return <div className="h-64 flex items-center justify-center text-slate-400">جاري التحميل...</div>
  if (!data) return <div className="h-64 flex items-center justify-center text-slate-400">فشل تحميل التقرير اليومي</div>

  const stats = [
    { label: 'النفوق', value: data.summary.mortality, icon: Skull, color: 'text-rose-600', bg: 'bg-rose-50' },
    { label: 'استهلاك العلف', value: data.summary.feed, unit: 'كغ', icon: Utensils, color: 'text-amber-600', bg: 'bg-amber-50' },
    { label: 'المصاريف', value: data.summary.expenses, currency: true, icon: DollarSign, color: 'text-blue-600', bg: 'bg-blue-50' },
    { label: 'المبيعات', value: data.summary.sales, currency: true, icon: TrendingUp, color: 'text-primary-600', bg: 'bg-primary-50' },
  ]

  return (
    <div className="space-y-6">
      {/* Date Header */}
      <div className="flex items-center gap-2 mb-2">
        <CalendarDays className="w-4 h-4 text-slate-400" />
        <span className="text-xs font-bold text-slate-600">تقرير تاريخ: {data.date}</span>
      </div>

      {/* Summary Grid */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
        {stats.map((stat, idx) => (
          <Card key={idx} className="p-3 border-slate-100 flex flex-col items-center text-center gap-2">
            <div className={`w-8 h-8 rounded-full ${stat.bg} flex items-center justify-center ${stat.color}`}>
              <stat.icon className="w-4 h-4" />
            </div>
            <div>
              <p className="text-[10px] text-slate-500 font-medium">{stat.label}</p>
              <h4 className="text-sm font-black text-slate-900 mt-0.5">
                {stat.currency ? Number(stat.value).toLocaleString() : stat.value}
                {stat.unit && <span className="text-[10px] font-normal mr-1">{stat.unit}</span>}
                {stat.currency && <span className="text-[10px] font-normal mr-1">{data.currency}</span>}
              </h4>
            </div>
          </Card>
        ))}
      </div>

      {/* Daily Timeline */}
      <div className="space-y-4">
        <h4 className="text-sm font-bold text-slate-800 flex items-center gap-2">
          <Clock className="w-4 h-4 text-blue-600" />
          شريط الأحداث المالي
        </h4>

        <div className="relative border-r-2 border-slate-100 pr-4 space-y-6">
          {data.timeline.map((event: any, idx: number) => (
            <div key={idx} className="relative">
              {/* Dot */}
              <div className={`absolute -right-[1.15rem] top-1.5 w-3 h-3 rounded-full border-2 border-white shadow-sm ${
                event.type === 'sale' ? 'bg-primary-500' : 'bg-blue-500'
              }`}></div>
              
              <div className="flex justify-between items-start gap-4">
                <div>
                  <div className="flex items-center gap-2">
                    <span className="text-[10px] font-black text-slate-400 font-mono">{event.time}</span>
                    <h5 className="text-xs font-bold text-slate-900">{event.title}</h5>
                    <Badge variant={event.type === 'sale' ? 'success' : 'neutral'} className="text-[8px] h-4">
                        {event.type === 'sale' ? 'بيع' : 'مصروف'}
                    </Badge>
                  </div>
                  <p className="text-[10px] text-slate-500 mt-1">{event.detail}</p>
                </div>
                <div className={`text-xs font-black ${event.type === 'sale' ? 'text-primary-600' : 'text-slate-900'}`}>
                    {event.type === 'sale' ? '+' : ''}{Number(event.amount).toLocaleString()} {data.currency}
                </div>
              </div>
            </div>
          ))}

          {data.timeline.length === 0 && (
            <div className="py-8 text-center text-[10px] text-slate-400 border border-dashed border-slate-200 rounded-xl">
                لا توجد حركات مالية مسجلة لهذا اليوم حتى الآن
            </div>
          )}
        </div>
      </div>
      
      <div className="p-4 rounded-xl bg-blue-50 border border-blue-100 flex items-start gap-3">
        <div className="mt-0.5">ℹ️</div>
        <p className="text-[10px] text-blue-800 leading-relaxed">
            هذا التقرير يعطي لقطة سريعة للنشاط اليومي. يتم تجميع البيانات من جميع السجلات المدخلة اليوم من قبل العمال والمديرين. لمراجعة الفترات الطويلة، يرجى الانتقال لتبويب "المحاسبة".
        </p>
      </div>
    </div>
  )
}


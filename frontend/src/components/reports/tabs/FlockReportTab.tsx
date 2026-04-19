'use client'

import { Card } from "@/components/ui/Card"
import { Badge } from "@/components/ui/Badge"
import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip, Legend } from 'recharts'
import { FlockReport } from "@/lib/api/reports"
import { CalendarDays, Skull, TrendingUp, DollarSign, Scale, Utensils, Info } from "lucide-react"

interface FlockReportTabProps {
  data: FlockReport | null
  isLoading: boolean
}

export const FlockReportTab = ({ data, isLoading }: FlockReportTabProps) => {
  if (isLoading) return <div className="h-64 flex items-center justify-center text-slate-400">جاري التحميل...</div>
  if (!data) return <div className="h-64 flex items-center justify-center text-slate-400">اختر فوجاً لعرض التقرير التفصيلي</div>

  const populationData = [
    { name: 'نفوق', value: data.performance.mortality_count, color: '#ef4444' },
    { name: 'مباع', value: data.sales_analytics.birds_sold, color: '#10b981' },
    { name: 'متبقي', value: data.performance.remaining_birds, color: '#3b82f6' },
  ].filter(item => item.value > 0)

  return (
    <div className="space-y-6">
      {/* 1. Header & Identity */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-2xl bg-slate-50 border border-slate-100">
        <div className="flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-400">
            <CalendarDays className="w-6 h-6" />
          </div>
          <div>
            <h3 className="text-lg font-black text-slate-900">{data.flock_info.name}</h3>
            <div className="flex items-center gap-2 mt-0.5">
              <Badge variant={data.flock_info.status === 'active' ? 'success' : 'neutral'} className="text-[10px] h-5 px-1.5 font-bold">
                {data.flock_info.status === 'active' ? 'نشط' : 'مغلق'}
              </Badge>
              <span className="text-[10px] text-slate-500 font-medium">
                عمر الفوج: <span className="text-slate-900 font-bold">{data.flock_info.age_days} يوم</span>
              </span>
            </div>
          </div>
        </div>
        <div className="flex flex-col text-left sm:text-right">
            <span className="text-[10px] text-slate-400 uppercase font-black tracking-wider">الفترة الزمنية</span>
            <span className="text-xs font-bold text-slate-700">
                {data.flock_info.start_date} <span className="mx-1 text-slate-300">←</span> {data.flock_info.close_date || 'مستمر'}
            </span>
        </div>
      </div>

      {/* 2. Three Column Statistics */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {/* Population Column */}
        <Card className="p-4 border-slate-100 shadow-sm flex flex-col h-full">
            <div className="flex items-center gap-2 mb-4">
                <Skull className="w-4 h-4 text-rose-500" />
                <h4 className="text-sm font-bold text-slate-800">إحصائيات العدد</h4>
            </div>
            
            <div className="h-40 mb-4">
                <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                        <Pie
                            data={populationData}
                            cx="50%"
                            cy="50%"
                            innerRadius={40}
                            outerRadius={60}
                            paddingAngle={5}
                            dataKey="value"
                        >
                            {populationData.map((entry, index) => (
                                <Cell key={`cell-${index}`} fill={entry.color} />
                            ))}
                        </Pie>
                        <Tooltip />
                    </PieChart>
                </ResponsiveContainer>
            </div>

            <div className="grid grid-cols-2 gap-2">
                <StatItem label="العدد الكلي" value={data.flock_info.initial_count} />
                <StatItem label="إجمالي النفوق" value={data.performance.mortality_count} sub={`%${data.performance.mortality_rate}`} color="text-rose-600" />
                <StatItem label="العدد المباع" value={data.sales_analytics.birds_sold} color="text-primary-600" />
                <StatItem label="العدد المتبقي" value={data.performance.remaining_birds} color="text-blue-600" />
            </div>
        </Card>

        {/* Sales/Production Column */}
        <Card className="p-4 border-slate-100 shadow-sm flex flex-col h-full">
            <div className="flex items-center gap-2 mb-4">
                <Scale className="w-4 h-4 text-primary-500" />
                <h4 className="text-sm font-bold text-slate-800">بيانات الأوزان والمبيعات</h4>
            </div>
            
            <div className="flex-1 space-y-4 py-2">
                <div className="flex justify-between items-center p-3 rounded-xl bg-slate-50">
                    <div className="flex items-center gap-2">
                        <TrendingUp className="w-4 h-4 text-primary-500" />
                        <span className="text-xs text-slate-600">الطيور المباعة</span>
                    </div>
                    <span className="text-sm font-black text-slate-900">{data.sales_analytics.birds_sold} طير</span>
                </div>
                
                <div className="flex justify-between items-center p-3 rounded-xl bg-slate-50">
                    <div className="flex items-center gap-2">
                        <Scale className="w-4 h-4 text-blue-500" />
                        <span className="text-xs text-slate-600">الوزن الكلي المباع</span>
                    </div>
                    <span className="text-sm font-black text-slate-900">{data.sales_analytics.total_weight_kg.toLocaleString()} كغ</span>
                </div>

                <div className="flex justify-between items-center p-4 rounded-2xl bg-primary-50 border border-primary-100">
                    <div className="flex items-center gap-2">
                        <Info className="w-4 h-4 text-primary-600" />
                        <span className="text-xs font-bold text-primary-800">متوسط وزن الطير</span>
                    </div>
                    <span className="text-lg font-black text-primary-900">{data.sales_analytics.avg_bird_weight_kg} كغ</span>
                </div>
            </div>

            <FeedConsumptionBadge kg={data.performance.total_feed_kg} bags={data.performance.total_feed_bags} />
        </Card>

        {/* Financials Column */}
        <Card className="p-4 border-slate-100 shadow-sm flex flex-col h-full">
            <div className="flex items-center gap-2 mb-4">
                <DollarSign className="w-4 h-4 text-blue-500" />
                <h4 className="text-sm font-bold text-slate-800">الملخص المالي</h4>
            </div>
            
            <div className="space-y-3">
                <div className="flex justify-between items-center text-xs">
                    <span className="text-slate-500">إجمالي المبيعات</span>
                    <span className="font-bold text-primary-600">$ {data.financial.total_sales.toLocaleString()}</span>
                </div>
                <div className="flex justify-between items-center text-xs">
                    <span className="text-slate-500">تكلفة العلف</span>
                    <span className="font-bold text-emerald-700">$ {data.performance.feed_cost.toLocaleString()}</span>
                </div>
                <div className="flex justify-between items-center text-xs">
                    <span className="text-slate-500">تكلفة الدواء</span>
                    <span className="font-bold text-slate-700">$ {data.performance.total_medicine_cost.toLocaleString()}</span>
                </div>
                <div className="flex justify-between items-center text-xs border-b border-dashed pb-2">
                    <span className="text-slate-500">مصاريف أخرى</span>
                    <span className="font-bold text-slate-700">
                      $ {Math.max(0, data.financial.total_expenses - data.performance.feed_cost - data.performance.total_medicine_cost).toLocaleString()}
                    </span>
                </div>
                <div className="flex justify-between items-center py-2">
                    <span className="text-xs font-bold text-slate-900">إجمالي المصروفات</span>
                    <span className="text-sm font-black text-rose-600">$ {data.financial.total_expenses.toLocaleString()}</span>
                </div>
            </div>

            <div className={`mt-auto p-4 rounded-2xl border-2 flex flex-col items-center gap-1 ${
                data.financial.is_profitable ? 'bg-primary-50 border-primary-100' : 'bg-rose-50 border-rose-100'
            }`}>
                <span className={`text-[10px] font-black uppercase tracking-widest ${
                    data.financial.is_profitable ? 'text-primary-600' : 'text-rose-600'
                }`}>
                    حالة الفوج: {data.financial.profit_status_label}
                </span>
                <span className={`text-2xl font-black ${
                    data.financial.is_profitable ? 'text-primary-700' : 'text-rose-700'
                }`}>
                    {Math.abs(data.financial.profit_loss).toLocaleString()} $
                </span>
            </div>
        </Card>
      </div>

      <div className="p-4 rounded-xl bg-blue-50 border border-blue-100 flex items-start gap-3">
        <Info className="w-5 h-5 text-blue-600 mt-0.5" />
        <p className="text-[10px] text-blue-800 leading-relaxed font-medium">
            يتم حساب "العدد المتبقي" بطرح (النفوق + المباع) من العدد الداخل الأول. متوسط الوزن يحسب بقسمة إجمالي الوزن المباع على عدد الطيور المباعة فقط. كافة المبالغ المالية معروضة بالدولار الأمريكي (USD).
        </p>
      </div>
    </div>
  )
}

function FeedConsumptionBadge({ kg, bags }: { kg: number; bags: number }) {
  const weightLabel = kg >= 1000
    ? `${(kg / 1000).toLocaleString(undefined, { maximumFractionDigits: 2 })} طن`
    : `${kg.toLocaleString()} كغ`

  return (
    <div className="mt-4 flex items-center gap-3 p-3 rounded-xl bg-emerald-50 border border-emerald-100">
      <Utensils className="h-4 w-4 text-emerald-600 shrink-0" />
      <div className="flex flex-col leading-tight">
        <span className="text-[10px] font-bold text-emerald-800">استهلاك العلف</span>
        {bags > 0 && (
          <span className="text-xs font-black text-emerald-900">{bags.toLocaleString()} كيس</span>
        )}
        <span className="text-[10px] text-emerald-700">{weightLabel}</span>
      </div>
    </div>
  )
}

function StatItem({ label, value, sub, color = "text-slate-900" }: any) {
    return (
        <div className="p-2 rounded-xl bg-slate-50/50 border border-slate-100 flex flex-col">
            <span className="text-[9px] text-slate-400 font-bold mb-0.5">{label}</span>
            <div className="flex items-baseline gap-1">
                <span className={`text-sm font-black ${color}`}>{value.toLocaleString()}</span>
                {sub && <span className="text-[8px] font-bold text-slate-400">{sub}</span>}
            </div>
        </div>
    )
}


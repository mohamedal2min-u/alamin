import { useState, useMemo } from 'react'
import { Card } from "@/components/ui/Card"
import { Badge } from "@/components/ui/Badge"
import { Dialog } from "@/components/ui/Dialog"
import { Button } from "@/components/ui/Button"
import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip, Legend } from 'recharts'
import { FlockReport } from "@/lib/api/reports"
import { CalendarDays, Skull, TrendingUp, DollarSign, Scale, Utensils, Info, Printer, ChevronDown, List } from "lucide-react"

interface FlockReportTabProps {
  data: FlockReport | null
  isLoading: boolean
}

export const FlockReportTab = ({ data, isLoading }: FlockReportTabProps) => {
  const [isModalOpen, setIsModalOpen] = useState(false)

  // Calculate "Other Expenses" explicitly
  const otherExpenses = useMemo(() => {
    if (!data) return 0
    return Math.max(0, 
      data.financial.total_expenses 
      - data.performance.feed_cost 
      - data.performance.total_medicine_cost 
      - data.performance.chick_cost
    )
  }, [data])

  // Sort Financial Items by Value (Highest First)
  const financialItems = useMemo(() => {
    if (!data) return []
    const items = [
      { label: 'إجمالي المبيعات', value: data.financial.total_sales, color: 'text-emerald-600' },
      { label: 'تكلفة العلف', value: data.performance.feed_cost, color: 'text-amber-700' },
      { label: 'تكلفة الدواء', value: data.performance.total_medicine_cost, color: 'text-slate-700' },
      { label: 'شراء صوص', value: data.performance.chick_cost, color: 'text-slate-700' },
      { label: 'مصاريف أخرى', value: otherExpenses, color: 'text-slate-600', isLink: true },
    ]
    return items.sort((a, b) => b.value - a.value)
  }, [data, otherExpenses])

  if (isLoading) return <div className="h-64 flex items-center justify-center text-slate-400">جاري التحميل...</div>
  if (!data) return <div className="h-64 flex items-center justify-center text-slate-400">اختر فوجاً لعرض التقرير التفصيلي</div>

  const populationData = [
    { name: 'نفوق', value: data.performance.mortality_count, color: '#ef4444' },
    { name: 'مباع', value: data.sales_analytics.birds_sold, color: '#10b981' },
    { name: 'متبقي', value: data.performance.remaining_birds, color: '#3b82f6' },
  ].filter(item => item.value > 0)

  const handlePrint = () => {
    const printContent = document.getElementById('print-area')
    if (!printContent) return

    const originalContents = document.body.innerHTML
    const printContents = printContent.innerHTML

    document.body.innerHTML = printContents
    window.print()
    document.body.innerHTML = originalContents
    window.location.reload() // Reload to restore React state
  }

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
                <StatItem label="العدد المباع" value={data.sales_analytics.birds_sold} color="text-emerald-600" />
                <StatItem label="العدد المتبقي" value={data.performance.remaining_birds} color="text-blue-600" />
            </div>
        </Card>

        {/* Sales/Production Column */}
        <Card className="p-4 border-slate-100 shadow-sm flex flex-col h-full">
            <div className="flex items-center gap-2 mb-4">
                <Scale className="w-4 h-4 text-emerald-500" />
                <h4 className="text-sm font-bold text-slate-800">بيانات الأوزان والمبيعات</h4>
            </div>
            
            <div className="flex-1 space-y-4 py-2">
                <div className="flex justify-between items-center p-3 rounded-xl bg-slate-50">
                    <div className="flex items-center gap-2">
                        <TrendingUp className="w-4 h-4 text-emerald-500" />
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

                <div className="flex justify-between items-center p-4 rounded-2xl bg-emerald-50 border border-emerald-100">
                    <div className="flex items-center gap-2">
                        <Info className="w-4 h-4 text-emerald-600" />
                        <span className="text-xs font-bold text-emerald-800">متوسط وزن الطير</span>
                    </div>
                    <span className="text-lg font-black text-emerald-900">{data.sales_analytics.avg_bird_weight_kg} كغ</span>
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
            
            <div className="space-y-3 flex-1">
                {financialItems.map((item, idx) => (
                    <div key={idx} className="flex justify-between items-center text-xs">
                        <span className="text-slate-500">{item.label}</span>
                        {item.isLink ? (
                            <button 
                                onClick={() => setIsModalOpen(true)}
                                className={`font-bold ${item.color} underline decoration-dotted hover:text-blue-600 transition-colors`}
                            >
                                $ {item.value.toLocaleString()}
                            </button>
                        ) : (
                            <span className={`font-bold ${item.color}`}>$ {item.value.toLocaleString()}</span>
                        )}
                    </div>
                ))}

                <div className="flex justify-between items-center pt-2 border-t border-dashed">
                    <span className="text-xs font-bold text-slate-900">إجمالي المصروفات</span>
                    <span className="text-sm font-black text-rose-600">$ {data.financial.total_expenses.toLocaleString()}</span>
                </div>

                {/* Last 5 Expenses Micro-List */}
                <div className="mt-4 pt-4 border-t border-slate-50">
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
                            <List className="w-3 h-3" /> آخر المصاريف
                        </span>
                        <button 
                            onClick={() => setIsModalOpen(true)}
                            className="text-[10px] text-blue-600 font-bold hover:underline"
                        >
                            توسيع الكل
                        </button>
                    </div>
                    <div className="space-y-1.5">
                        {(data.details.expense_records || []).slice(0, 5).map((exp) => (
                            <div key={exp.id} className="flex justify-between items-center text-[10px]">
                                <span className="text-slate-500 truncate max-w-[100px]">{exp.category}</span>
                                <span className="font-bold text-slate-700">${exp.amount.toLocaleString()}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            <div className={`mt-4 p-4 rounded-2xl border-2 flex flex-col items-center gap-1 ${
                data.financial.is_profitable ? 'bg-emerald-50 border-emerald-100' : 'bg-rose-50 border-rose-100'
            }`}>
                <span className={`text-[10px] font-black uppercase tracking-widest ${
                    data.financial.is_profitable ? 'text-emerald-600' : 'text-rose-600'
                }`}>
                    حالة الفوج: {data.financial.profit_status_label}
                </span>
                <span className={`text-2xl font-black ${
                    data.financial.is_profitable ? 'text-emerald-700' : 'text-rose-700'
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

      {/* Full Financial Report Modal */}
      <Dialog 
        isOpen={isModalOpen} 
        onClose={() => setIsModalOpen(false)} 
        title="التقرير المالي التفصيلي"
        className="max-w-4xl"
      >
        <div className="space-y-6">
            <div className="flex justify-end gap-2">
                <Button onClick={handlePrint} size="sm" variant="outline" className="gap-2 bg-slate-900 text-white hover:bg-slate-800">
                    <Printer className="w-4 h-4" /> طباعة التقرير
                </Button>
            </div>

            <div id="print-area" className="space-y-8 p-6 bg-white shrink-0 rtl" dir="rtl">
                {/* Print Header */}
                <div className="hidden print:block border-b-4 border-slate-900 pb-4 mb-8">
                    <div className="flex justify-between items-end">
                        <h1 className="text-2xl font-black text-slate-900">نظام إدارة مزارع الياسين</h1>
                        <span className="text-sm font-bold">{new Date().toLocaleDateString('ar-EG')}</span>
                    </div>
                    <p className="text-lg font-bold mt-2">تقرير مالي تفصيلي للفوج: {data.flock_info.name}</p>
                </div>

                {/* 1. Expenses Table */}
                <div>
                    <h2 className="text-base font-black mb-4 flex items-center gap-2 border-r-4 border-rose-500 pr-3">
                        أولاً: المصاريف والتكاليف (المبالغ الخارجة)
                    </h2>
                    <table className="w-full border-collapse border-2 border-slate-900 text-[11px]">
                        <thead>
                            <tr className="bg-slate-100 print:bg-slate-100">
                                <th className="border-2 border-slate-900 p-2 text-right w-10">#</th>
                                <th className="border-2 border-slate-900 p-2 text-right w-24">التاريخ</th>
                                <th className="border-2 border-slate-900 p-2 text-right w-24">الفئة</th>
                                <th className="border-2 border-slate-900 p-2 text-right">الوصف والتفاصيل</th>
                                <th className="border-2 border-slate-900 p-2 text-left w-24">المبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            {/* Summary Totals as first rows */}
                            <tr className="bg-slate-50 font-bold">
                                <td className="border-2 border-slate-900 p-2 text-slate-400">S1</td>
                                <td className="border-2 border-slate-900 p-2 text-slate-500 italic">فترة الفوج</td>
                                <td className="border-2 border-slate-900 p-2">علف</td>
                                <td className="border-2 border-slate-900 p-2">إجمالي تكلفة العلف المستهلك خلال الدورة</td>
                                <td className="border-2 border-slate-900 p-2 text-left font-black tracking-tight">$ {data.performance.total_feed_bags ? `${data.performance.total_feed_bags} كيس / ` : ''} {data.performance.feed_cost.toLocaleString()}</td>
                            </tr>
                            <tr className="bg-slate-50 font-bold">
                                <td className="border-2 border-slate-900 p-2 text-slate-400">S2</td>
                                <td className="border-2 border-slate-900 p-2 text-slate-500 italic">بداية الفوج</td>
                                <td className="border-2 border-slate-900 p-2">صوص</td>
                                <td className="border-2 border-slate-900 p-2">تكلفة شراء الصوص بالكامل ({data.flock_info.initial_count} صوص)</td>
                                <td className="border-2 border-slate-900 p-2 text-left font-black tracking-tight">$ {data.performance.chick_cost.toLocaleString()}</td>
                            </tr>
                            <tr className="bg-slate-50 font-bold">
                                <td className="border-2 border-slate-900 p-2 text-slate-400">S3</td>
                                <td className="border-2 border-slate-900 p-2 text-slate-500 italic">فترة الفوج</td>
                                <td className="border-2 border-slate-900 p-2">أدوية</td>
                                <td className="border-2 border-slate-900 p-2">إجمالي تكلفة الأدوية والمعقمات والتحصينات</td>
                                <td className="border-2 border-slate-900 p-2 text-left font-black tracking-tight">$ {data.performance.total_medicine_cost.toLocaleString()}</td>
                            </tr>

                            {/* Actual Transaction Records */}
                            {(data.details.expense_records || []).map((exp, i) => (
                                <tr key={exp.id}>
                                    <td className="border-2 border-slate-900 p-2 text-slate-500">{i + 1}</td>
                                    <td className="border-2 border-slate-900 p-2 whitespace-nowrap">{exp.date}</td>
                                    <td className="border-2 border-slate-900 p-2 font-bold">{exp.category}</td>
                                    <td className="border-2 border-slate-900 p-2 text-slate-600 italic">{exp.description || '-'}</td>
                                    <td className="border-2 border-slate-900 p-2 text-left font-black">$ {exp.amount.toLocaleString()}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="bg-slate-900 text-white font-black">
                                <td colSpan={4} className="p-2 text-left">إجمالي كافة المصاريف والتكاليف</td>
                                <td className="p-2 text-left">$ {data.financial.total_expenses.toLocaleString()}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {/* 2. Income Table */}
                <div>
                    <h2 className="text-base font-black mb-4 flex items-center gap-2 border-r-4 border-emerald-500 pr-3">
                        ثانياً: الإيرادات والمبيعات (المبالغ الداخلة)
                    </h2>
                    <table className="w-full border-collapse border-2 border-slate-900 text-[11px]">
                        <thead>
                            <tr className="bg-slate-100 print:bg-slate-100">
                                <th className="border-2 border-slate-900 p-2 text-right w-10">#</th>
                                <th className="border-2 border-slate-900 p-2 text-right w-24">التاريخ</th>
                                <th className="border-2 border-slate-900 p-2 text-right w-24">النوع</th>
                                <th className="border-2 border-slate-900 p-2 text-right">التفاصيل</th>
                                <th className="border-2 border-slate-900 p-2 text-left w-24">المبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(data.details.sale_records || []).length > 0 ? (
                                (data.details.sale_records || []).map((sale, i) => (
                                    <tr key={sale.id}>
                                        <td className="border-2 border-slate-900 p-2 text-slate-500">{i + 1}</td>
                                        <td className="border-2 border-slate-900 p-2 whitespace-nowrap">{sale.date}</td>
                                        <td className="border-2 border-slate-900 p-2 font-bold">مبيعات</td>
                                        <td className="border-2 border-slate-900 p-2 text-slate-600 italic">{sale.description || '-'}</td>
                                        <td className="border-2 border-slate-900 p-2 text-left font-black tracking-tight">$ {sale.amount.toLocaleString()}</td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={5} className="border-2 border-slate-900 p-4 text-center text-slate-400 font-bold italic">لا توجد سجلات مبيعات مسجلة لهذا الفوج حتى الآن</td>
                                </tr>
                            )}
                        </tbody>
                        <tfoot>
                            <tr className="bg-slate-900 text-white font-black">
                                <td colSpan={4} className="p-2 text-left">إجمالي المبيعات والإيرادات</td>
                                <td className="p-2 text-left">$ {data.financial.total_sales.toLocaleString()}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {/* 3. Final Result */}
                <div className={`p-4 border-4 flex justify-between items-center ${data.financial.is_profitable ? 'border-emerald-600 bg-emerald-50' : 'border-rose-600 bg-rose-50'}`}>
                    <span className="text-xl font-black uppercase">النتيجة النهائية للميزانية</span>
                    <div className="text-left">
                        <span className="text-xs font-bold block mb-1">{data.financial.is_profitable ? 'إجمالي الأرباح المستحق' : 'إجمالي الخسائر المحققة'}</span>
                        <span className={`text-3xl font-black ${data.financial.is_profitable ? 'text-emerald-700' : 'text-rose-700'}`}>
                            $ {Math.abs(data.financial.profit_loss).toLocaleString()}
                        </span>
                    </div>
                </div>

                <div className="hidden print:block mt-8 pt-4 border-t-2 border-slate-400">
                    <div className="flex justify-between text-[10px] text-slate-500 font-bold">
                        <span>نظام ألمين (Alamin) لإدارة مزارع الدواجن</span>
                        <span>توقيع المدير: __________________</span>
                    </div>
                </div>
            </div>
        </div>
      </Dialog>
    </div>
  )
}



function FeedConsumptionBadge({ kg, bags }: { kg: number; bags: number }) {
  const weightLabel = kg >= 1000
    ? `${(kg / 1000).toLocaleString(undefined, { maximumFractionDigits: 2 })} طن`
    : `${kg.toLocaleString()} كغ`

  return (
    <div className="mt-4 flex items-center gap-3 p-3 rounded-xl bg-amber-50 border border-amber-100">
      <Utensils className="w-4 h-4 text-amber-600 shrink-0" />
      <div className="flex flex-col leading-tight">
        <span className="text-[10px] font-bold text-amber-800">استهلاك العلف</span>
        {bags > 0 && (
          <span className="text-xs font-black text-amber-900">{bags.toLocaleString()} كيس</span>
        )}
        <span className="text-[10px] text-amber-700">{weightLabel}</span>
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

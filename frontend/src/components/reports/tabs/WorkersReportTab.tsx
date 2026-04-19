'use client'

import { Card } from "@/components/ui/Card"
import { Badge } from "@/components/ui/Badge"
import { UserCheck, MessageSquare, DollarSign, Calendar } from "lucide-react"

interface WorkersReportTabProps {
  data: any
  isLoading: boolean
}

export const WorkersReportTab = ({ data, isLoading }: WorkersReportTabProps) => {
  if (isLoading) return <div className="h-64 flex items-center justify-center text-slate-400">جاري التحميل...</div>
  if (!data) return <div className="h-64 flex items-center justify-center text-slate-400">فشل تحميل بيانات العمال</div>

  return (
    <div className="space-y-6">
      {/* Workers List Section */}
      <h4 className="text-sm font-bold text-slate-800 flex items-center gap-2">
        <UserCheck className="w-4 h-4 text-emerald-500" />
        قائمة العمال والرواتب
      </h4>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {data.workers.map((worker: any) => (
          <Card key={worker.id} className="p-4 border-slate-100 shadow-sm flex flex-col gap-4">
            <div className="flex justify-between items-start">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 font-bold uppercase">
                  {worker.name.substring(0, 2)}
                </div>
                <div>
                  <h4 className="text-sm font-bold text-slate-900">{worker.name}</h4>
                  <p className="text-[10px] text-slate-500 flex items-center gap-1">
                    <MessageSquare className="w-2.5 h-2.5" /> {worker.whatsapp || 'لا يتوفر رقم'}
                  </p>
                </div>
              </div>
              <Badge variant="neutral" className="text-[9px] font-medium border-slate-200">
                عامل مدجنة
              </Badge>
            </div>

            <div className="grid grid-cols-2 gap-2 mt-2">
              <div className="bg-slate-50 p-2 rounded-lg">
                <span className="text-[9px] text-slate-400 block">تاريخ الانضمام</span>
                <span className="text-[10px] font-bold text-slate-700 flex items-center gap-1 mt-0.5">
                    <Calendar className="w-2.5 h-2.5" /> {worker.joined_at?.split('T')[0] || '---'}
                </span>
              </div>
              <div className="bg-emerald-50 p-2 rounded-lg">
                <span className="text-[9px] text-emerald-400 block">إجمالي المقبوضات</span>
                <span className="text-xs font-black text-emerald-700 mt-0.5">
                    {Number(worker.total_payments).toLocaleString()} {data.currency}
                </span>
              </div>
            </div>
          </Card>
        ))}
        {data.workers.length === 0 && (
          <div className="col-span-full py-12 text-center text-slate-400 text-xs italic">
            لا يوجد عمال مسجلين في هذه المدجنة حالياً
          </div>
        )}
      </div>

      <div className="p-4 rounded-xl bg-slate-50/50 border border-dashed border-slate-200">
        <p className="text-[10px] text-slate-500 leading-relaxed italic text-center">
            * يتم احتساب "إجمالي المقبوضات" بناءً على جميع سجلات المصاريف التي تم ربطها بهذا العامل في قسم المصاريف.
        </p>
      </div>
    </div>
  )
}


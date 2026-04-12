'use client'

import { Card } from "@/components/ui/Card"
import { Badge } from "@/components/ui/Badge"
import { Package, ArrowUpRight, ArrowDownLeft } from "lucide-react"

interface InventoryReportTabProps {
  data: any
  isLoading: boolean
}

export const InventoryReportTab = ({ data, isLoading }: InventoryReportTabProps) => {
  if (isLoading) return <div className="h-64 flex items-center justify-center text-slate-400">جاري التحميل...</div>
  if (!data) return <div className="h-64 flex items-center justify-center text-slate-400">فشل تحميل بيانات المخزون</div>

  return (
    <div className="space-y-6">
      {/* Stock Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {data.stock_levels.map((stock: any, idx: number) => (
          <Card key={idx} className="p-4 border-slate-100 shadow-sm flex items-center gap-4">
            <div className="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
              <Package className="w-6 h-6" />
            </div>
            <div>
              <span className="text-[10px] text-slate-500 font-medium block leading-none">{stock.type}</span>
              <h4 className="text-sm font-bold text-slate-900 mt-1">{stock.name}</h4>
              <div className="mt-1 flex items-baseline gap-1">
                <span className="text-lg font-black text-slate-900">{Number(stock.current_quantity).toLocaleString()}</span>
                <span className="text-[10px] text-slate-500 font-normal">{stock.content_unit}</span>
              </div>
            </div>
          </Card>
        ))}
      </div>

      {/* Latest Movements Table */}
      <Card className="p-4 border-slate-100 shadow-sm">
        <h4 className="text-sm font-bold text-slate-700 mb-4 border-b pb-2">آخر 10 حركات مخزنية</h4>
        <div className="overflow-x-auto">
          <table className="w-full text-right">
            <thead>
              <tr className="border-b border-slate-100">
                <th className="py-2 text-[10px] font-bold text-slate-400 uppercase">التاريخ</th>
                <th className="py-2 text-[10px] font-bold text-slate-400 uppercase">الصنف</th>
                <th className="py-2 text-[10px] font-bold text-slate-400 uppercase text-center">النوع</th>
                <th className="py-2 text-[10px] font-bold text-slate-400 uppercase text-left">الكمية</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-50">
              {data.latest_movements.map((move: any) => (
                <tr key={move.id} className="hover:bg-slate-50/50 transition-colors">
                  <td className="py-3 text-xs text-slate-600">{move.transaction_date}</td>
                  <td className="py-3 text-xs font-semibold text-slate-900">{move.item_name}</td>
                  <td className="py-3 text-center">
                    <Badge variant={move.direction === 'in' ? 'success' : 'neutral'} className="text-[10px] px-2 py-0.5">
                      {move.direction === 'in' ? (
                        <span className="flex items-center gap-1"><ArrowUpRight className="w-2.5 h-2.5" /> دخول</span>
                      ) : (
                        <span className="flex items-center gap-1"><ArrowDownLeft className="w-2.5 h-2.5" /> خروج</span>
                      )}
                    </Badge>
                  </td>
                  <td className="py-3 text-left text-xs font-bold text-slate-900">
                    {Number(move.quantity).toLocaleString()} {move.content_unit}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>

      <div className="p-4 rounded-xl bg-blue-50/50 border border-blue-100">
        <p className="text-[10px] text-blue-700 leading-relaxed">
          <strong>ملاحظة:</strong> يتم حساب "الرصيد الحالي" بناءً على جميع حركات الإدخال والصرف المسجلة في النظام. يتم تحديث الجدول تلقائياً عند تنفيذ أي عملية صرف علف أو دواء.
        </p>
      </div>
    </div>
  )
}

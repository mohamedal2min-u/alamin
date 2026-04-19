'use client'

import { Card } from "@/components/ui/Card"
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Cell } from 'recharts'
import { AccountingSummary } from "@/lib/api/reports"

interface AccountingReportTabProps {
  data: AccountingSummary | null
  isLoading: boolean
}

export const AccountingReportTab = ({ data, isLoading }: AccountingReportTabProps) => {
  if (isLoading) return <div className="h-64 flex items-center justify-center text-slate-400">جاري التحميل...</div>
  if (!data) return <div className="h-64 flex items-center justify-center text-slate-400">فشل تحميل البيانات المحاسبية</div>

  const COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899']

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card className="p-4 border-slate-100 shadow-sm border-r-4 border-r-primary-500">
          <span className="text-xs text-slate-500">التدفق النقدي (المحصل)</span>
          <div className="text-xl font-black text-primary-600 mt-1">
            {data.cash_flow.total_received.toLocaleString()} <small className="text-[10px] font-normal">USD</small>
          </div>
        </Card>
        <Card className="p-4 border-slate-100 shadow-sm border-r-4 border-r-rose-500">
          <span className="text-xs text-slate-500">التدفق النقدي (المدفوع)</span>
          <div className="text-xl font-black text-rose-600 mt-1">
            {data.cash_flow.total_paid.toLocaleString()} <small className="text-[10px] font-normal">USD</small>
          </div>
        </Card>
        <Card className="p-4 border-slate-100 shadow-sm border-r-4 border-r-blue-500">
          <span className="text-xs text-slate-500">الرصيد النقدي الحالي</span>
          <div className={`text-xl font-black mt-1 ${data.cash_flow.balance >= 0 ? 'text-blue-600' : 'text-rose-600'}`}>
            {data.cash_flow.balance.toLocaleString()} <small className="text-[10px] font-normal">USD</small>
          </div>
        </Card>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* Expense Breakdown */}
        <Card className="p-4 border-slate-100 shadow-sm">
          <h4 className="text-sm font-bold text-slate-700 mb-4 border-b pb-2">توزيع المصاريف حسب الفئة</h4>
          {data.expense_breakdown.length === 0 ? (
            <div className="h-64 flex items-center justify-center text-slate-400 text-sm">لا توجد مصاريف مسجلة</div>
          ) : (
            <div style={{ height: Math.max(160, data.expense_breakdown.length * 40) }}>
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={data.expense_breakdown} layout="vertical" margin={{ right: 16 }}>
                  <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                  <XAxis type="number" tickFormatter={(v) => v >= 1000 ? `${(v / 1000).toFixed(0)}k` : String(v)} fontSize={10} />
                  <YAxis dataKey="category" type="category" width={110} fontSize={10} tick={{ fill: '#64748b' }} />
                  <Tooltip formatter={(v: any) => [`${Number(v || 0).toLocaleString()} USD`, 'المبلغ']} />
                  <Bar dataKey="amount" radius={[0, 4, 4, 0]}>
                    {data.expense_breakdown.map((entry: any, index: number) => (
                      <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            </div>
          )}
        </Card>

        {/* Debts and Receivables */}
        <Card className="p-4 border-slate-100 shadow-sm">
          <h4 className="text-sm font-bold text-slate-700 mb-4 border-b pb-2">المديونيات والتحصيلات</h4>
          <div className="space-y-6 mt-4">
            {(() => {
              const maxVal = Math.max(data.debts.receivables, data.debts.payables, 1)
              const recPct = Math.min(100, (data.debts.receivables / maxVal) * 100)
              const payPct = Math.min(100, (data.debts.payables / maxVal) * 100)
              return (
                <>
                  <div>
                    <div className="flex justify-between text-xs mb-1">
                      <span className="font-medium text-slate-500">مستحقات لنا (ديون المبيعات)</span>
                      <span className="font-bold text-primary-600">{data.debts.receivables.toLocaleString()} USD</span>
                    </div>
                    <div className="w-full bg-slate-100 rounded-full h-2">
                      <div className="bg-primary-500 h-2 rounded-full transition-all duration-500" style={{ width: `${recPct}%` }} />
                    </div>
                  </div>
                  <div>
                    <div className="flex justify-between text-xs mb-1">
                      <span className="font-medium text-slate-500">مستحقات علينا (ديون الموردين)</span>
                      <span className="font-bold text-rose-600">{data.debts.payables.toLocaleString()} USD</span>
                    </div>
                    <div className="w-full bg-slate-100 rounded-full h-2">
                      <div className="bg-rose-500 h-2 rounded-full transition-all duration-500" style={{ width: `${payPct}%` }} />
                    </div>
                  </div>
                </>
              )
            })()}

            <div className="pt-4 border-t border-dashed mt-6">
              <div className="p-3 rounded-lg bg-primary-50 text-[10px] text-primary-700 leading-relaxed">
                <strong>ملاحظة:</strong> تمثل "مستحقات لنا" المبالغ التي لم يتم تحصيلها بعد من المبيعات، بينما تمثل "مستحقات علينا" المبالغ الآجلة التي يجب دفعها للموردين.
              </div>
            </div>
          </div>
        </Card>
      </div>
    </div>
  )
}


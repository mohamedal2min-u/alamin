'use client'

import { Card } from "@/components/ui/Card"
import { Badge } from "@/components/ui/Badge"
import { Users, Wallet, CreditCard } from "lucide-react"

interface PartnersReportTabProps {
  data: any
  isLoading: boolean
}

export const PartnersReportTab = ({ data, isLoading }: PartnersReportTabProps) => {
  if (isLoading) return <div className="h-64 flex items-center justify-center text-slate-400">جاري التحميل...</div>
  if (!data) return <div className="h-64 flex items-center justify-center text-slate-400">فشل تحميل بيانات الشركاء</div>

  return (
    <div className="space-y-6">
      {/* Shares Breakdown */}
      <h4 className="text-sm font-bold text-slate-800 flex items-center gap-2">
        <Users className="w-4 h-4 text-blue-600" />
        توزيع حصص الشركاء
      </h4>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {data.partners.map((partner: any, idx: number) => (
          <Card key={idx} className="p-4 border-slate-100 shadow-sm relative overflow-hidden group">
            <div className="absolute top-0 right-0 w-1 pt-1 bg-blue-500 h-full"></div>
            <div className="flex justify-between items-start">
              <div>
                <p className="text-[10px] text-slate-500 font-medium">الشريك</p>
                <h4 className="text-sm font-bold text-slate-900 mt-0.5">{partner.name}</h4>
              </div>
              <Badge variant="info" className="text-[10px] font-black border-blue-100 bg-blue-50 text-blue-700">
                {partner.share_percent}%
              </Badge>
            </div>
            <div className="mt-4 h-1.5 w-full bg-slate-50 rounded-full overflow-hidden">
                <div 
                    className="h-full bg-blue-500 rounded-full" 
                    style={{ width: `${partner.share_percent}%` }}
                ></div>
            </div>
          </Card>
        ))}
      </div>

      {/* Partners Financial Ledger */}
      <Card className="p-4 border-slate-100 shadow-sm">
        <div className="flex items-center justify-between mb-4 border-b pb-2">
            <h4 className="text-sm font-bold text-slate-800 flex items-center gap-2">
                <Wallet className="w-4 h-4 text-primary-600" />
                آخر الحركات المالية للشركاء
            </h4>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-right border-collapse">
            <thead>
              <tr className="border-b border-slate-100">
                <th className="py-2 text-[10px] font-bold text-slate-400 uppercase">التاريخ</th>
                <th className="py-2 text-[10px] font-bold text-slate-400 uppercase">الشريك</th>
                <th className="py-2 text-[10px] font-bold text-slate-400 uppercase text-center">النوع</th>
                <th className="py-2 text-[10px] font-bold text-slate-400 uppercase text-left">المبلغ</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-50">
              {data.transactions.map((tx: any) => (
                <tr key={tx.id} className="hover:bg-slate-50/50 transition-colors">
                  <td className="py-3 text-xs text-slate-600 font-mono italic">{tx.transaction_date}</td>
                  <td className="py-3 text-xs font-semibold text-slate-900">{tx.partner?.name || '---'}</td>
                  <td className="py-3 text-center">
                    <Badge variant={tx.transaction_type === 'deposit' ? 'success' : 'danger'} className="text-[9px] h-5 px-2">
                      {tx.transaction_type === 'deposit' ? 'إيداع' : 'سحب'}
                    </Badge>
                  </td>
                  <td className={`py-3 text-left text-xs font-bold ${tx.transaction_type === 'deposit' ? 'text-primary-600' : 'text-rose-600'}`}>
                    {tx.transaction_type === 'deposit' ? '+' : '-'}{Number(tx.amount).toLocaleString()} {data.currency}
                  </td>
                </tr>
              ))}
              {data.transactions.length === 0 && (
                <tr>
                  <td colSpan={4} className="py-8 text-center text-xs text-slate-400">لا توجد حركات مالية مسجلة للشركاء حالياً</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </Card>
      
      <div className="flex items-center gap-3 p-4 rounded-xl bg-slate-50 border border-slate-200">
        <div className="w-8 h-8 rounded-full bg-white flex items-center justify-center shadow-sm">
            <CreditCard className="w-4 h-4 text-slate-500" />
        </div>
        <p className="text-[10px] text-slate-600 leading-relaxed">
            توضح الحصص المئوية أعلاه صافي الربح المستحق لكل شريك عند تصفية الفوج المعني. يتم تسجيل عمليات السحب والإيداع يدوياً من قبل المدير المسؤول.
        </p>
      </div>
    </div>
  )
}


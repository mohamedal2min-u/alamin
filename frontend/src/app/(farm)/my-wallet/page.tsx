'use client'

import { useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useFarmStore } from '@/stores/farm.store'
import { useLayoutStore } from '@/stores/layout.store'
import { partnerTransactionsApi, PartnerTransaction } from '@/lib/api/partner-transactions'
import { partnersApi } from '@/lib/api/partners'
import { Spinner } from '@/components/ui/Spinner'
import {
  Wallet, FileText, ArrowDownLeft, ArrowUpRight,
  Scale, TrendingUp, TrendingDown, Printer
} from 'lucide-react'
import { format } from 'date-fns'
import { ar } from 'date-fns/locale'

const TRANSACTION_CONFIG: Record<string, { label: string; color: string; bgColor: string; icon: any; sign: '+' | '-' | '' }> = {
  deposit:    { label: 'إيداع نقدي',  color: 'text-primary-600', bgColor: 'bg-primary-50',  icon: ArrowDownLeft,  sign: '+' },
  withdraw:   { label: 'سحب رصيد',    color: 'text-rose-600',    bgColor: 'bg-rose-50',     icon: ArrowUpRight,   sign: '-' },
  profit:     { label: 'أرباح فوج',   color: 'text-sky-600',     bgColor: 'bg-sky-50',      icon: TrendingUp,     sign: '+' },
  loss:       { label: 'خسائر فوج',   color: 'text-orange-600',  bgColor: 'bg-orange-50',   icon: TrendingDown,   sign: '-' },
  settlement: { label: 'تسوية',       color: 'text-slate-600',   bgColor: 'bg-slate-50',    icon: Scale,          sign: '' },
  adjustment: { label: 'تعديل',       color: 'text-indigo-600',  bgColor: 'bg-indigo-50',   icon: Scale,          sign: '' },
}

export default function MyWalletPage() {
  const { currentFarm } = useFarmStore()
  const { setPageTitle, setPageSubtitle } = useLayoutStore()

  useEffect(() => {
    setPageTitle('محفظتي')
    setPageSubtitle(currentFarm?.name || null)
  }, [currentFarm, setPageTitle, setPageSubtitle])

  // partner_id is in the farm store after login; fallback: fetch from /partners/my-info
  const storedPartnerId = currentFarm?.partner_id

  const { data: myInfo } = useQuery({
    queryKey: ['partner-my-info', currentFarm?.id],
    queryFn: () => partnersApi.getMyInfo(),
    enabled: !!currentFarm && !storedPartnerId,
    staleTime: 10 * 60 * 1000,
  })

  const partnerId = storedPartnerId ?? myInfo?.id

  const { data: transactions = [], isLoading } = useQuery({
    queryKey: ['partner_transactions', partnerId],
    queryFn: () => partnerTransactionsApi.list(partnerId!),
    enabled: !!partnerId,
    staleTime: 2 * 60 * 1000,
  })

  const balance = transactions.reduce((acc: number, t: PartnerTransaction) => {
    const amount = Number(t.amount)
    if (['deposit', 'profit'].includes(t.transaction_type)) return acc + amount
    if (['withdraw', 'loss'].includes(t.transaction_type)) return acc - amount
    return acc
  }, 0)

  const totalDeposits = transactions
    .filter((t: PartnerTransaction) => ['deposit', 'profit'].includes(t.transaction_type))
    .reduce((s: number, t: PartnerTransaction) => s + Number(t.amount), 0)

  const totalWithdrawals = transactions
    .filter((t: PartnerTransaction) => ['withdraw', 'loss'].includes(t.transaction_type))
    .reduce((s: number, t: PartnerTransaction) => s + Number(t.amount), 0)

  return (
    <div className="space-y-5 px-3 pt-3 pb-8" dir="rtl">

      {/* Header */}
      <div className="flex items-center justify-between no-print">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-slate-900 flex items-center justify-center">
            <Wallet className="w-5 h-5 text-white" />
          </div>
          <div>
            <h1 className="text-lg font-black text-slate-900">محفظتي</h1>
            <p className="text-[11px] text-slate-400 font-medium">كشف حسابي المالي</p>
          </div>
        </div>
        <button
          onClick={() => window.print()}
          className="h-9 w-9 flex items-center justify-center bg-slate-100 border border-slate-200/60 rounded-xl text-slate-500 hover:bg-slate-200 transition-colors"
          title="طباعة"
        >
          <Printer className="w-4 h-4" />
        </button>
      </div>

      {/* Balance Cards */}
      {!isLoading && (
        <div className="grid grid-cols-3 gap-3 no-print">
          <div className="bg-slate-900 rounded-2xl p-4 text-white col-span-3 sm:col-span-1">
            <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">الرصيد الصافي</p>
            <p className={`text-2xl font-black font-mono ${balance >= 0 ? 'text-primary-400' : 'text-rose-400'}`}>
              ${balance.toLocaleString('en-US', { minimumFractionDigits: 2 })}
            </p>
          </div>
          <div className="bg-white border border-slate-100 rounded-2xl p-4 col-span-3 sm:col-span-1" style={{ boxShadow: 'var(--shadow-card)' }}>
            <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">إجمالي الوارد</p>
            <p className="text-xl font-black text-primary-600 font-mono">
              ${totalDeposits.toLocaleString('en-US', { minimumFractionDigits: 2 })}
            </p>
          </div>
          <div className="bg-white border border-slate-100 rounded-2xl p-4 col-span-3 sm:col-span-1" style={{ boxShadow: 'var(--shadow-card)' }}>
            <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">إجمالي الصادر</p>
            <p className="text-xl font-black text-rose-600 font-mono">
              ${totalWithdrawals.toLocaleString('en-US', { minimumFractionDigits: 2 })}
            </p>
          </div>
        </div>
      )}

      {/* Transactions */}
      <div className="bg-white border border-slate-200/60 rounded-2xl overflow-hidden" style={{ boxShadow: 'var(--shadow-card)' }}>
        <div className="flex items-center gap-2 px-5 py-4 border-b border-slate-100">
          <FileText className="w-4 h-4 text-slate-400" />
          <h2 className="text-sm font-bold text-slate-700">السجل المالي</h2>
          {transactions.length > 0 && (
            <span className="mr-auto text-[10px] font-bold text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full border border-slate-100">
              {transactions.length} عملية
            </span>
          )}
        </div>

        {isLoading ? (
          <div className="flex flex-col items-center justify-center py-16 gap-3 text-slate-400">
            <Spinner size="lg" />
            <p className="text-xs font-bold">جاري التحميل...</p>
          </div>
        ) : !partnerId ? (
          <div className="flex flex-col items-center justify-center py-16 text-slate-300">
            <Wallet className="w-10 h-10 mb-3 opacity-30" />
            <p className="text-sm font-bold text-slate-400">لا توجد محفظة مرتبطة بحسابك</p>
          </div>
        ) : transactions.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 text-slate-300">
            <FileText className="w-10 h-10 mb-3 opacity-20" />
            <p className="text-sm font-bold text-slate-400">لا توجد سجلات مالية بعد</p>
          </div>
        ) : (
          <>
            {/* Mobile cards */}
            <div className="divide-y divide-slate-50 sm:hidden">
              {transactions.map((t: PartnerTransaction) => {
                const config = TRANSACTION_CONFIG[t.transaction_type] ?? TRANSACTION_CONFIG.settlement
                const Icon = config.icon
                return (
                  <div key={t.id} className="flex items-center gap-3 px-4 py-3.5">
                    <div className={`w-9 h-9 rounded-xl ${config.bgColor} flex items-center justify-center shrink-0`}>
                      <Icon className={`w-4 h-4 ${config.color}`} />
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className={`text-xs font-bold ${config.color}`}>{config.label}</p>
                      <p className="text-[11px] text-slate-400 truncate">{t.description || t.transaction_date}</p>
                    </div>
                    <p className={`font-mono font-bold text-sm shrink-0 ${['deposit', 'profit'].includes(t.transaction_type) ? 'text-primary-600' : 'text-rose-600'}`}>
                      {config.sign}{Number(t.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                    </p>
                  </div>
                )
              })}
            </div>

            {/* Desktop table */}
            <div className="hidden sm:block overflow-x-auto">
              <table className="w-full text-right text-sm">
                <thead>
                  <tr className="bg-slate-50 border-b border-slate-100">
                    <th className="px-5 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider">التاريخ</th>
                    <th className="px-5 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider">النوع</th>
                    <th className="px-5 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider">البيان</th>
                    <th className="px-5 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider text-left">المبلغ ($)</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-50">
                  {transactions.map((t: PartnerTransaction) => {
                    const config = TRANSACTION_CONFIG[t.transaction_type] ?? TRANSACTION_CONFIG.settlement
                    const Icon = config.icon
                    return (
                      <tr key={t.id} className="hover:bg-slate-50/50 transition-colors">
                        <td className="px-5 py-3 font-mono text-xs text-slate-500">{t.transaction_date}</td>
                        <td className="px-5 py-3">
                          <span className={`inline-flex items-center gap-1.5 ${config.color} font-bold text-xs`}>
                            <Icon className="w-3.5 h-3.5" />
                            {config.label}
                          </span>
                        </td>
                        <td className="px-5 py-3 text-slate-600 text-xs max-w-[200px] truncate">{t.description || '-'}</td>
                        <td className={`px-5 py-3 font-mono font-bold text-left ${['deposit', 'profit'].includes(t.transaction_type) ? 'text-primary-600' : 'text-rose-600'}`}>
                          {config.sign}{Number(t.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>

            {/* Print header */}
            <div className="hidden print:block px-5 py-4 border-t border-slate-200 mt-4">
              <p className="text-xs text-slate-500">تاريخ الاستخراج: {format(new Date(), 'dd MMMM yyyy', { locale: ar })}</p>
              <p className="text-sm font-bold mt-1">الرصيد الصافي: ${balance.toLocaleString('en-US', { minimumFractionDigits: 2 })}</p>
            </div>
          </>
        )}
      </div>
    </div>
  )
}

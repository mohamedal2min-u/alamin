'use client'

import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Partner } from '@/lib/api/partners'
import { partnerTransactionsApi, PartnerTransaction } from '@/lib/api/partner-transactions'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Spinner } from '@/components/ui/Spinner'
import { 
  X, Wallet, Plus, Printer, FileText, Info,
  ArrowDownLeft, ArrowUpRight, Scale, TrendingUp, TrendingDown, CheckCircle
} from 'lucide-react'
import { format } from 'date-fns'
import { ar } from 'date-fns/locale'

interface PartnerLedgerModalProps {
  isOpen: boolean
  onClose: () => void
  partner: Partner | null
}

const TRANSACTION_CONFIG: Record<string, { label: string; color: string; bgColor: string; icon: any; sign: '+' | '-' | '' }> = {
  deposit:    { label: 'إيداع نقدي',   color: 'text-emerald-600', bgColor: 'bg-emerald-50',  icon: ArrowDownLeft,  sign: '+' },
  withdraw:   { label: 'سحب رصيد',     color: 'text-rose-600',    bgColor: 'bg-rose-50',     icon: ArrowUpRight,   sign: '-' },
  profit:     { label: 'أرباح فوج',    color: 'text-sky-600',     bgColor: 'bg-sky-50',      icon: TrendingUp,     sign: '+' },
  loss:       { label: 'خسائر فوج',    color: 'text-orange-600',  bgColor: 'bg-orange-50',   icon: TrendingDown,   sign: '-' },
  settlement: { label: 'تسوية',        color: 'text-slate-600',   bgColor: 'bg-slate-50',    icon: Scale,          sign: '' },
  adjustment: { label: 'تعديل',        color: 'text-indigo-600',  bgColor: 'bg-indigo-50',   icon: Scale,          sign: '' },
}

export const PartnerLedgerModal = ({ isOpen, onClose, partner }: PartnerLedgerModalProps) => {
  const queryClient = useQueryClient()
  const [activeTab, setActiveTab] = useState<'ledger' | 'new_transaction'>('ledger')
  const [successMsg, setSuccessMsg] = useState('')
  
  const [formData, setFormData] = useState({
    transaction_type: 'deposit' as 'deposit' | 'withdraw' | 'settlement',
    amount: '',
    description: '',
    transaction_date: new Date().toISOString().split('T')[0]
  })
  
  const [isSubmitting, setIsSubmitting] = useState(false)

  const { data: transactions = [], isLoading } = useQuery({
    queryKey: ['partner_transactions', partner?.id],
    queryFn: () => partnerTransactionsApi.list(partner!.id),
    enabled: !!partner && isOpen
  })

  const createMutation = useMutation({
    mutationFn: (data: any) => partnerTransactionsApi.create(partner!.id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['partner_transactions', partner?.id] })
      setActiveTab('ledger')
      setFormData({
        transaction_type: 'deposit',
        amount: '',
        description: '',
        transaction_date: new Date().toISOString().split('T')[0]
      })
      setSuccessMsg('تم تسجيل العملية المالية بنجاح ✓')
      setTimeout(() => setSuccessMsg(''), 4000)
    }
  })

  if (!isOpen || !partner) return null

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!formData.amount || Number(formData.amount) <= 0) {
      alert('يرجى إدخال مبلغ صحيح أكبر من صفر.')
      return
    }
    setIsSubmitting(true)
    try {
      await createMutation.mutateAsync({
        ...formData,
        amount: Number(formData.amount)
      })
    } catch (error: any) {
      console.error('Failed to create transaction:', error)
      const msg = error?.response?.data?.message || 'حدث خطأ أثناء إضافة الحركة المالية.'
      alert(msg)
    } finally {
      setIsSubmitting(false)
    }
  }

  // Calculate balances
  const balance = transactions.reduce((acc: number, t: PartnerTransaction) => {
    const amount = Number(t.amount)
    if (['deposit', 'profit'].includes(t.transaction_type)) return acc + amount
    if (['withdraw', 'loss'].includes(t.transaction_type)) return acc - amount
    return acc
  }, 0)

  const totalDeposits = transactions
    .filter((t: PartnerTransaction) => t.transaction_type === 'deposit')
    .reduce((s: number, t: PartnerTransaction) => s + Number(t.amount), 0)

  const totalWithdrawals = transactions
    .filter((t: PartnerTransaction) => t.transaction_type === 'withdraw')
    .reduce((s: number, t: PartnerTransaction) => s + Number(t.amount), 0)

  return (
    <div 
      className="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-4 bg-black/50 backdrop-blur-sm"
      onClick={(e) => { if (e.target === e.currentTarget) onClose() }}
      dir="rtl"
    >
      <div className="w-full max-w-2xl bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
        
        {/* ─── Header ─── */}
        <div className="flex items-center justify-between px-6 py-5 border-b border-slate-100 bg-white no-print shrink-0">
          <div className="flex items-center gap-4 min-w-0">
            <div className="w-11 h-11 rounded-xl bg-slate-900 flex items-center justify-center text-white shrink-0">
              <Wallet className="w-5 h-5" />
            </div>
            <div className="min-w-0">
              <h3 className="text-lg font-black text-slate-900 truncate">كشف حساب الشريك</h3>
              <p className="text-xs font-medium text-slate-400 truncate">{partner.name}</p>
            </div>
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <button 
              onClick={() => window.print()} 
              className="p-2.5 hover:bg-slate-100 rounded-xl transition-colors text-slate-400 hover:text-slate-700"
              title="طباعة"
            >
              <Printer className="w-4.5 h-4.5" />
            </button>
            <button 
              onClick={onClose} 
              className="p-2.5 hover:bg-slate-100 rounded-xl transition-colors text-slate-400 hover:text-slate-700"
            >
              <X className="w-4.5 h-4.5" />
            </button>
          </div>
        </div>

        {/* ─── Tabs ─── */}
        <div className="flex border-b border-slate-100 bg-white no-print shrink-0">
          <button
            onClick={() => setActiveTab('ledger')}
            className={`flex-1 py-3.5 text-sm font-bold transition-all relative ${
              activeTab === 'ledger' ? 'text-slate-900' : 'text-slate-400 hover:text-slate-600'
            }`}
          >
            <span className="flex items-center justify-center gap-2">
              <FileText className="w-4 h-4" />
              السجل المالي
            </span>
            {activeTab === 'ledger' && (
              <span className="absolute bottom-0 inset-x-4 h-0.5 bg-slate-900 rounded-full" />
            )}
          </button>
          <button
            onClick={() => setActiveTab('new_transaction')}
            className={`flex-1 py-3.5 text-sm font-bold transition-all relative ${
              activeTab === 'new_transaction' ? 'text-slate-900' : 'text-slate-400 hover:text-slate-600'
            }`}
          >
            <span className="flex items-center justify-center gap-2">
              <Plus className="w-4 h-4" />
              إضافة عملية
            </span>
            {activeTab === 'new_transaction' && (
              <span className="absolute bottom-0 inset-x-4 h-0.5 bg-slate-900 rounded-full" />
            )}
          </button>
        </div>

        {/* ─── Success Toast ─── */}
        {successMsg && (
          <div className="mx-6 mt-4 flex items-center gap-3 bg-emerald-50 border border-emerald-100 text-emerald-700 px-4 py-3 rounded-xl text-sm font-bold no-print">
            <CheckCircle className="w-4 h-4 shrink-0" />
            {successMsg}
          </div>
        )}

        {/* ─── Content ─── */}
        <div className="flex-1 overflow-y-auto p-6">
          
          {activeTab === 'ledger' && (
            <div className="space-y-5">

              {/* Balance Cards */}
              <div className="grid grid-cols-3 gap-3 no-print">
                <div className="bg-slate-900 rounded-xl p-4 text-white">
                  <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">الرصيد الصافي</p>
                  <p className={`text-xl sm:text-2xl font-black font-mono ${balance >= 0 ? 'text-emerald-400' : 'text-rose-400'}`}>
                    ${balance.toLocaleString('en-US', { minimumFractionDigits: 2 })}
                  </p>
                </div>
                <div className="bg-white border border-slate-100 rounded-xl p-4">
                  <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">إجمالي الإيداع</p>
                  <p className="text-xl font-black text-emerald-600 font-mono">
                    ${totalDeposits.toLocaleString('en-US', { minimumFractionDigits: 2 })}
                  </p>
                </div>
                <div className="bg-white border border-slate-100 rounded-xl p-4">
                  <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">إجمالي السحب</p>
                  <p className="text-xl font-black text-rose-600 font-mono">
                    ${totalWithdrawals.toLocaleString('en-US', { minimumFractionDigits: 2 })}
                  </p>
                </div>
              </div>

              {/* Print-only header */}
              <div className="hidden print:block mb-6 border-b-2 border-slate-900 pb-3">
                <h2 className="text-xl font-black">كشف حساب مالي</h2>
                <p className="text-sm font-bold mt-1">الشريك: {partner.name}</p>
                <p className="text-xs text-slate-500 mt-1">تاريخ الاستخراج: {format(new Date(), 'dd MMMM yyyy', { locale: ar })}</p>
                <p className="text-sm font-bold mt-2">الرصيد الصافي: ${balance.toLocaleString('en-US', { minimumFractionDigits: 2 })}</p>
              </div>

              {/* Transactions */}
              {isLoading ? (
                <div className="flex flex-col items-center justify-center py-16 text-slate-400 gap-3">
                  <Spinner size="lg" />
                  <p className="text-xs font-bold">جاري التحميل...</p>
                </div>
              ) : transactions.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-16 text-slate-300">
                  <FileText className="w-10 h-10 mb-3 opacity-20" />
                  <p className="text-sm font-bold text-slate-400">لا توجد سجلات مالية بعد</p>
                  <p className="text-xs text-slate-300 mt-1">أضف أول عملية من تبويب "إضافة عملية"</p>
                </div>
              ) : (
                <div className="space-y-2">
                  <div className="flex items-center justify-between mb-3 no-print">
                    <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider">العمليات ({transactions.length})</h4>
                  </div>

                  {/* Transaction Cards (mobile-friendly) */}
                  <div className="space-y-2 sm:hidden no-print">
                    {transactions.map((t: PartnerTransaction) => {
                      const config = TRANSACTION_CONFIG[t.transaction_type] || TRANSACTION_CONFIG.settlement
                      const IconComponent = config.icon
                      return (
                        <div key={t.id} className="flex items-center gap-3 p-3 bg-white border border-slate-100 rounded-xl">
                          <div className={`w-9 h-9 rounded-lg ${config.bgColor} flex items-center justify-center shrink-0`}>
                            <IconComponent className={`w-4 h-4 ${config.color}`} />
                          </div>
                          <div className="flex-1 min-w-0">
                            <p className={`text-xs font-bold ${config.color}`}>{config.label}</p>
                            <p className="text-[11px] text-slate-400 truncate">{t.description || t.transaction_date}</p>
                          </div>
                          <p className={`font-mono font-bold text-sm shrink-0 ${
                            ['deposit', 'profit'].includes(t.transaction_type) ? 'text-emerald-600' : 'text-rose-600'
                          }`}>
                            {config.sign}{Number(t.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                          </p>
                        </div>
                      )
                    })}
                  </div>

                  {/* Transaction Table (desktop) */}
                  <div className="hidden sm:block border border-slate-100 rounded-xl overflow-hidden">
                    <table className="w-full text-right text-sm">
                      <thead>
                        <tr className="bg-slate-50 border-b border-slate-100 no-print">
                          <th className="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider">التاريخ</th>
                          <th className="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider">النوع</th>
                          <th className="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider">البيان</th>
                          <th className="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider text-left">المبلغ ($)</th>
                        </tr>
                        {/* Print header */}
                        <tr className="hidden print:table-row bg-slate-100 border-b border-slate-300">
                          <th className="px-3 py-2 text-right text-xs">التاريخ</th>
                          <th className="px-3 py-2 text-right text-xs">النوع</th>
                          <th className="px-3 py-2 text-right text-xs">البيان</th>
                          <th className="px-3 py-2 text-left text-xs">المبلغ</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-50">
                        {transactions.map((t: PartnerTransaction) => {
                          const config = TRANSACTION_CONFIG[t.transaction_type] || TRANSACTION_CONFIG.settlement
                          const IconComponent = config.icon
                          return (
                            <tr key={t.id} className="hover:bg-slate-50/50 transition-colors">
                              <td className="px-4 py-3 font-mono text-xs text-slate-500">{t.transaction_date}</td>
                              <td className="px-4 py-3">
                                <span className={`inline-flex items-center gap-1.5 ${config.color} font-bold text-xs`}>
                                  <IconComponent className="w-3.5 h-3.5" />
                                  {config.label}
                                </span>
                              </td>
                              <td className="px-4 py-3 text-slate-600 text-xs max-w-[200px] truncate">{t.description || '-'}</td>
                              <td className={`px-4 py-3 font-mono font-bold text-left ${
                                ['deposit', 'profit'].includes(t.transaction_type) ? 'text-emerald-600' : 'text-rose-600'
                              }`}>
                                {config.sign}{Number(t.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                              </td>
                            </tr>
                          )
                        })}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}
            </div>
          )}

          {activeTab === 'new_transaction' && (
            <form onSubmit={handleSubmit} className="space-y-5">
              {/* Type Selector */}
              <div className="space-y-2">
                <label className="text-xs font-bold text-slate-500 mr-1">نوع العملية</label>
                <div className="grid grid-cols-3 gap-2">
                  {[
                    { value: 'deposit', label: 'إيداع', icon: ArrowDownLeft, desc: 'إضافة رصيد', color: 'emerald' },
                    { value: 'withdraw', label: 'سحب', icon: ArrowUpRight, desc: 'خصم رصيد', color: 'rose' },
                    { value: 'settlement', label: 'تسوية', icon: Scale, desc: 'تعديل محاسبي', color: 'slate' },
                  ].map(opt => {
                    const isSelected = formData.transaction_type === opt.value
                    const Icon = opt.icon
                    return (
                      <button
                        key={opt.value}
                        type="button"
                        onClick={() => setFormData({ ...formData, transaction_type: opt.value as any })}
                        className={`flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all ${
                          isSelected 
                            ? opt.color === 'emerald' ? 'border-emerald-500 bg-emerald-50'
                            : opt.color === 'rose' ? 'border-rose-500 bg-rose-50'
                            : 'border-slate-500 bg-slate-50'
                            : 'border-slate-100 bg-white hover:border-slate-200'
                        }`}
                      >
                        <Icon className={`w-5 h-5 ${
                          isSelected
                            ? opt.color === 'emerald' ? 'text-emerald-600'
                            : opt.color === 'rose' ? 'text-rose-600'
                            : 'text-slate-600'
                            : 'text-slate-300'
                        }`} />
                        <span className={`text-xs font-bold ${isSelected ? 'text-slate-900' : 'text-slate-400'}`}>{opt.label}</span>
                      </button>
                    )
                  })}
                </div>
              </div>

              {/* Amount */}
              <div className="space-y-2">
                <label className="text-xs font-bold text-slate-500 mr-1">المبلغ ($)</label>
                <div className="relative">
                  <span className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 font-bold text-lg pointer-events-none">$</span>
                  <Input 
                    type="number"
                    step="0.01"
                    min="0.01"
                    required
                    placeholder="0.00"
                    className="font-mono text-2xl h-16 pl-10 pr-5 rounded-xl border-slate-200 focus:ring-2 focus:ring-slate-900/10 bg-slate-50/50 text-left"
                    dir="ltr"
                    value={formData.amount}
                    onChange={e => setFormData({...formData, amount: e.target.value})}
                  />
                </div>
              </div>

              {/* Date + Description */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <label className="text-xs font-bold text-slate-500 mr-1">التاريخ</label>
                  <Input 
                    type="date"
                    required
                    className="h-12 rounded-xl border-slate-200 font-medium"
                    value={formData.transaction_date}
                    onChange={e => setFormData({...formData, transaction_date: e.target.value})}
                  />
                </div>
                <div className="space-y-2">
                  <label className="text-xs font-bold text-slate-500 mr-1">البيان</label>
                  <Input 
                    required
                    placeholder="وصف العملية..."
                    className="h-12 rounded-xl border-slate-200 font-medium"
                    value={formData.description}
                    onChange={e => setFormData({...formData, description: e.target.value})}
                  />
                </div>
              </div>

              {/* Info Note */}
              <div className="flex items-start gap-3 p-3.5 bg-sky-50 border border-sky-100 rounded-xl text-xs text-sky-700 no-print">
                <Info className="w-4 h-4 shrink-0 mt-0.5" />
                <p className="font-medium leading-relaxed">
                  سيتم تحديث الرصيد فوراً وستظهر العملية في السجل المالي.
                </p>
              </div>

              {/* Submit */}
              <Button 
                type="submit" 
                className="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold h-14 rounded-xl transition-all active:scale-[0.98] flex items-center justify-center gap-2"
                disabled={isSubmitting}
              >
                {isSubmitting ? (
                  <>
                    <Spinner size="sm" className="border-white/20 border-t-white" />
                    <span>جاري الحفظ...</span>
                  </>
                ) : (
                  <>
                    <Wallet className="w-5 h-5" />
                    <span>تأكيد العملية</span>
                  </>
                )}
              </Button>
            </form>
          )}
        </div>
      </div>
    </div>
  )
}

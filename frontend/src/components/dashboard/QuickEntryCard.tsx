// frontend/src/components/dashboard/QuickEntryCard.tsx
'use client'

import { useState, useEffect } from 'react'
import { 
  CheckCircle2, AlertCircle, HeartPulse, 
  Utensils, Pill, Receipt, Zap, Sparkles
} from 'lucide-react'
import { inventoryApi } from '@/lib/api/inventory'
import { quickEntryApi } from '@/lib/api/quick-entry'
import { mortalitiesApi } from '@/lib/api/mortalities'
import { cn, formatNumber } from '@/lib/utils'
import { Dialog } from '@/components/ui/Dialog'
import type { InventoryItem } from '@/types/dashboard'

type Tab = 'mortality' | 'feed' | 'medicine' | 'expense'

const TABS: { key: Tab; label: string; icon: any; color: string; bgColor: string; borderColor: string; shadowColor: string }[] = [
  { key: 'mortality', label: 'نفوق',   icon: HeartPulse, color: 'text-rose-600',    bgColor: 'bg-rose-50',    borderColor: 'border-rose-100', shadowColor: 'shadow-rose-100' },
  { key: 'feed',      label: 'علف',    icon: Utensils,   color: 'text-blue-600',    bgColor: 'bg-blue-50',    borderColor: 'border-blue-100', shadowColor: 'shadow-blue-100' },
  { key: 'medicine',  label: 'دواء',   icon: Pill,       color: 'text-emerald-600', bgColor: 'bg-emerald-50', borderColor: 'border-emerald-100', shadowColor: 'shadow-emerald-100' },
  { key: 'expense',   label: 'مصروف',  icon: Receipt,    color: 'text-amber-600',   bgColor: 'bg-amber-50',   borderColor: 'border-amber-100', shadowColor: 'shadow-amber-100' },
]

const EXPENSE_TYPES = [
  { value: 'water',   label: 'مياه' },
  { value: 'bedding', label: 'فرشة (نشارة)' },
  { value: 'other',   label: 'أخرى' },
]

interface Props {
  flockId: number
  onSuccess?: () => void
}

export function QuickEntryCard({ flockId, onSuccess }: Props) {
  const [activeTab, setActiveTab] = useState<Tab | null>(null)
  const [loading, setLoading]     = useState(false)
  const [success, setSuccess]     = useState(false)
  const [error, setError]         = useState<string | null>(null)

  // Mortality
  const [mQty, setMQty]         = useState('')
  const [mReason, setMReason]   = useState('')

  // Feed
  const [feedItems, setFeedItems]   = useState<InventoryItem[]>([])
  const [feedItemId, setFeedItemId] = useState('')
  const [feedQty, setFeedQty]       = useState('')

  // Medicine
  const [medItems, setMedItems]   = useState<InventoryItem[]>([])
  const [medItemId, setMedItemId] = useState('')
  const [medQty, setMedQty]       = useState('')

  // Expense
  const [expType, setExpType]     = useState('water')
  const [expQty, setExpQty]       = useState('')
  const [expPrice, setExpPrice]   = useState('')
  const [expDescription, setExpDescription] = useState('')
  const [expNotes, setExpNotes]   = useState('')

  const calculatedTotal = (activeTab === 'expense') 
    ? (Number(expQty) * Number(expPrice)) 
    : 0

  useEffect(() => {
    if (activeTab === 'feed') inventoryApi.items('feed').then((res) => setFeedItems(res.data))
    if (activeTab === 'medicine') inventoryApi.items('medicine').then((res) => setMedItems(res.data))
  }, [activeTab])

  const resetFields = () => {
    setMQty(''); setMReason('')
    setFeedItemId(''); setFeedQty('')
    setMedItemId(''); setMedQty('')
    setExpType('water'); setExpQty(''); setExpPrice(''); setExpDescription(''); setExpNotes('')
    setError(null)
  }

  const handleCardClick = (key: Tab) => {
    setActiveTab(key)
    setError(null)
    setSuccess(false)
  }

  const handleSubmit = async () => {
    if (!activeTab) return
    setError(null)
    setLoading(true)
    try {
      const date = new Date().toISOString().slice(0, 10)
      if (activeTab === 'mortality') {
        if (!mQty || Number(mQty) < 1) { setError('أدخل كمية صحيحة'); setLoading(false); return }
        await mortalitiesApi.create(flockId, { quantity: Number(mQty), reason: mReason || undefined, entry_date: date })
      } else if (activeTab === 'feed') {
        if (!feedItemId) { setError('اختر صنف العلف'); setLoading(false); return }
        if (!feedQty || Number(feedQty) <= 0) { setError('أدخل كمية صحيحة'); setLoading(false); return }
        await quickEntryApi.logFeed(flockId, { item_id: Number(feedItemId), quantity: Number(feedQty), entry_date: date })
      } else if (activeTab === 'medicine') {
        if (!medItemId) { setError('اختر صنف الدواء'); setLoading(false); return }
        if (!medQty || Number(medQty) <= 0) { setError('أدخل كمية صحيحة'); setLoading(false); return }
        await quickEntryApi.logMedicine(flockId, { item_id: Number(medItemId), quantity: Number(medQty), entry_date: date })
      } else {
        const total = calculatedTotal
        if (total <= 0 && expType !== 'water') { setError('أدخل مبلغاً صحيحاً'); setLoading(false); return }
        await quickEntryApi.logExpense(flockId, { 
          expense_type: expType, total_amount: total || Number(expPrice), quantity: Number(expQty) || undefined,
          unit_price: Number(expPrice) || undefined, description: expDescription || undefined, notes: expNotes || undefined, entry_date: date 
        })
      }

      setSuccess(true)
      resetFields()
      onSuccess?.()
      setTimeout(() => {
        setSuccess(false)
        setActiveTab(null)
      }, 1000)
    } catch (err: any) {
      setError(err.response?.data?.message || 'حدث خطأ، حاول مجدداً')
    } finally {
      setLoading(false)
    }
  }

  const activeTabData = TABS.find(t => t.key === activeTab)

  return (
    <div className="relative rounded-3xl bg-slate-50/50 p-5 border border-slate-100 shadow-sm">
      {/* ── Header ── */}
      <div className="mb-5 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 shadow-sm">
            <Zap className="h-4 w-4" />
          </div>
          <div>
            <h3 className="text-sm font-bold text-slate-800">الإدخال السريع</h3>
            <p className="text-[10px] font-medium text-slate-400">سجل بياناتك بلمسة واحدة</p>
          </div>
        </div>
        <Sparkles className="h-4 w-4 text-slate-200" />
      </div>

      {/* ── Buttons Grid ── */}
      <div className="grid grid-cols-4 gap-3">
        {TABS.map((t) => (
          <button
            key={t.key}
            onClick={() => handleCardClick(t.key)}
            className={cn(
              "group flex flex-col items-center justify-center rounded-2xl border border-slate-100 bg-white p-3.5 transition-all active:scale-95",
              "shadow-sm hover:shadow-md hover:-translate-y-0.5 hover:border-emerald-200",
              t.shadowColor ? `hover:${t.shadowColor}/50` : ""
            )}
          >
            <div className={cn(
              "mb-2.5 flex h-10 w-10 items-center justify-center rounded-xl transition-all group-hover:scale-110", 
              t.bgColor, t.color,
              "ring-1 ring-inset ring-white/20"
            )}>
              <t.icon className="h-5 w-5" />
            </div>
            <span className="text-[10px] font-bold text-slate-500 group-hover:text-slate-900">{t.label}</span>
          </button>
        ))}
      </div>

      <Dialog 
        isOpen={activeTab !== null} 
        onClose={() => setActiveTab(null)}
        title={activeTab ? `تسجيل جديد - ${activeTabData?.label}` : ''}
        className="max-w-[400px]"
      >
        <div className="space-y-4 pt-2">
          {/* Status Context Indicator */}
          <div className={cn("inline-flex items-center gap-2 px-3 py-1 rounded-full border text-[10px] font-bold", activeTabData?.bgColor, activeTabData?.color, activeTabData?.borderColor)}>
            {activeTabData && <activeTabData.icon className="w-3 h-3" />}
            {activeTabData?.label}
          </div>

          {/* Forms */}
          <div className="space-y-4">
            {activeTab === 'mortality' && (
              <>
                <FormField label="عدد الطيور النافقة" required>
                  <NumericInput value={mQty} onChange={setMQty} placeholder="0" />
                </FormField>
                <FormField label="السبب">
                  <input type="text" value={mReason} onChange={(e) => setMReason(e.target.value)} placeholder="اختياري..." className={inputClass} />
                </FormField>
              </>
            )}

            {activeTab === 'feed' && (
              <>
                <FormField label="الصنف" required>
                  <SelectInput value={feedItemId} onChange={setFeedItemId} options={feedItems.map(i => ({ value: String(i.id), label: i.name }))} placeholder="اختر النوع..." emptyMessage="لا يوجد مخزون" />
                </FormField>
                <FormField label="الكمية (كيلو/كيس)" required>
                  <NumericInput value={feedQty} onChange={setFeedQty} placeholder="0.00" />
                </FormField>
              </>
            )}

            {activeTab === 'medicine' && (
              <>
                <FormField label="الدواء" required>
                  <SelectInput value={medItemId} onChange={setMedItemId} options={medItems.map(i => ({ value: String(i.id), label: i.name }))} placeholder="اختر الصنف..." emptyMessage="لا يوجد مخزون" />
                </FormField>
                <FormField label="الكمية" required>
                  <NumericInput value={medQty} onChange={setMedQty} placeholder="0.00" />
                </FormField>
              </>
            )}

            {activeTab === 'expense' && (
              <div className="space-y-4">
                <FormField label="التصنيف" required>
                  <select value={expType} onChange={(e) => setExpType(e.target.value)} className={inputClass}>
                    {EXPENSE_TYPES.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                  </select>
                </FormField>
                <div className="grid grid-cols-2 gap-3">
                  <FormField label={expType === 'water' ? 'العدد' : 'الكمية'} required>
                    <NumericInput value={expQty} onChange={setExpQty} placeholder="0" />
                  </FormField>
                  <FormField label="السعر/المبلغ" required>
                    <NumericInput value={expPrice} onChange={setExpPrice} placeholder="0.00" />
                  </FormField>
                </div>
                {expType === 'other' && (
                  <FormField label="وصف المصروف" required>
                    <input type="text" value={expDescription} onChange={(e) => setExpDescription(e.target.value)} placeholder="اكتب وصفاً..." className={inputClass} />
                  </FormField>
                )}
              </div>
            )}
          </div>

          {/* Feedback & Submit */}
          <div className="space-y-3 pt-2">
            {error && (
              <div className="flex items-center gap-2 rounded-xl border border-rose-100 bg-rose-50 p-3 text-rose-700 text-[11px] font-bold">
                <AlertCircle className="h-4 w-4" /> {error}
              </div>
            )}
            {success && (
              <div className="flex items-center justify-center gap-2 rounded-xl bg-emerald-500 p-3 text-white text-xs font-bold animate-in zoom-in">
                <CheckCircle2 className="h-4 w-4" /> تم الحفظ بنجاح
              </div>
            )}
            {!success && (
              <button
                onClick={handleSubmit}
                disabled={loading}
                className={cn(
                  "w-full rounded-xl py-3.5 text-sm font-bold text-white transition-all active:scale-95",
                  activeTabData?.key === 'mortality' ? "bg-rose-600 shadow-rose-100 shadow-lg" :
                  activeTabData?.key === 'feed' ? "bg-blue-600 shadow-blue-100 shadow-lg" :
                  activeTabData?.key === 'medicine' ? "bg-emerald-600 shadow-emerald-100 shadow-lg" :
                  "bg-amber-600 shadow-amber-100 shadow-lg",
                  loading && "opacity-50"
                )}
              >
                {loading ? 'جارٍ الحفظ...' : 'تأكيد الحفظ'}
              </button>
            )}
          </div>
        </div>
      </Dialog>
    </div>
  )
}

const inputClass = 'w-full rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900 focus:border-slate-300 focus:bg-white focus:outline-none transition-all'

function FormField({ label, children, required }: any) {
  return (
    <div className="flex flex-col gap-1.5">
      <label className="text-[11px] font-black text-slate-400 px-1">{label} {required && <span className="text-rose-400">*</span>}</label>
      {children}
    </div>
  )
}

function NumericInput({ value, onChange, placeholder }: any) {
  return <input type="number" inputMode="decimal" value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} className={inputClass} dir="ltr" />
}

function SelectInput({ value, onChange, options, placeholder, emptyMessage }: any) {
  if (options.length === 0) return <div className="rounded-xl border border-slate-50 bg-slate-50/50 px-4 py-3 text-[10px] font-bold text-slate-400 italic">{emptyMessage}</div>
  return (
    <select value={value} onChange={(e) => onChange(e.target.value)} className={inputClass}>
      <option value="">{placeholder}</option>
      {options.map((o: any) => <option key={o.value} value={o.value}>{o.label}</option>)}
    </select>
  )
}

'use client'

import React, { useState, useEffect } from 'react'
import { 
  AlertCircle, Skull, 
  Wheat, Syringe, ThermometerSun, Receipt
} from 'lucide-react'
import { inventoryApi } from '@/lib/api/inventory'
import { quickEntryApi } from '@/lib/api/quick-entry'
import { mortalitiesApi } from '@/lib/api/mortalities'
import { workerApi } from '@/lib/api/worker'
import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}
import { Dialog } from '@/components/ui/Dialog'
import type { InventoryItem } from '@/types/dashboard'
import { toast } from 'sonner'

type Tab = 'mortality' | 'feed' | 'medicine' | 'temp' | 'expense'

interface Props {
  flockId: number
  activeTab: Tab | null
  initialExtra?: Record<string, unknown> | null
  entryDate?: string
  onClose: () => void
  onSuccess?: () => void
}

const TABS: Record<Tab, { label: string; icon: React.ElementType }> = {
  mortality: { label: 'نفوق',   icon: Skull },
  feed:      { label: 'علف',    icon: Wheat },
  medicine:  { label: 'دواء',   icon: Syringe },
  temp:      { label: 'حرارة',  icon: ThermometerSun },
  expense:   { label: 'مصروف', icon: Receipt },
}

export function WorkerEntryDialog({ flockId, activeTab, initialExtra, entryDate, onClose, onSuccess }: Props) {
  const [loading, setLoading]     = useState(false)
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

  // Temp
  const [tempTime, setTempTime]   = useState<'morning' | 'afternoon' | 'evening'>('morning')
  const [tempVal, setTempVal]     = useState('')

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
    if (activeTab === 'temp' && initialExtra?.time) setTempTime(initialExtra.time as 'morning' | 'afternoon' | 'evening')
  }, [activeTab, initialExtra])

  const resetFields = () => {
    setMQty(''); setMReason('')
    setFeedItemId(''); setFeedQty('')
    setMedItemId(''); setMedQty('')
    setTempVal('')
    setExpType('water'); setExpQty(''); setExpPrice(''); setExpDescription(''); setExpNotes('')
    setError(null)
  }

  const handleSubmit = async () => {
    if (!activeTab) return
    setError(null)
    setLoading(true)
    try {
      // Use provided entryDate or fallback to today YYYY-MM-DD
      let date = entryDate
      if (!date) {
        const now = new Date()
        date = now.getFullYear() + '-' + 
               String(now.getMonth() + 1).padStart(2, '0') + '-' + 
               String(now.getDate()).padStart(2, '0')
      }

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
      } else if (activeTab === 'temp') {
        if (!tempVal || isNaN(Number(tempVal))) { setError('أدخل قيمة حرارة صحيحة'); setLoading(false); return }
        await workerApi.logTemperature(flockId, { log_date: date, time_of_day: tempTime, temperature: Number(tempVal) })
      } else if (activeTab === 'expense') {
        const total = calculatedTotal
        if (total <= 0 && expType !== 'water') { setError('أدخل مبلغاً صحيحاً'); setLoading(false); return }
        await quickEntryApi.logExpense(flockId, { 
          expense_type: expType, total_amount: total || Number(expPrice), quantity: Number(expQty) || undefined,
          unit_price: Number(expPrice) || undefined, description: expDescription || undefined, notes: expNotes || undefined, entry_date: date 
        })
      }

      resetFields()
      toast.success('تم الحفظ بنجاح')
      onSuccess?.()
      onClose()
    } catch (err: any) {
      const msg = err.response?.data?.message || 'حدث خطأ، حاول مجدداً'
      setError(typeof msg === 'string' ? msg : 'خطأ في البيانات')
    } finally {
      setLoading(false)
    }
  }

  const themes = {
    mortality: { 
      text: 'text-rose-600', 
      bg: 'bg-rose-600', 
      border: 'focus:border-rose-500', 
      ring: 'focus:ring-rose-500/10',
      shadow: 'shadow-rose-500/20'
    },
    feed: { 
      text: 'text-amber-600', 
      bg: 'bg-amber-600', 
      border: 'focus:border-amber-500', 
      ring: 'focus:ring-amber-500/10',
      shadow: 'shadow-amber-500/20'
    },
    medicine: { 
      text: 'text-emerald-600', 
      bg: 'bg-emerald-600', 
      border: 'focus:border-emerald-500', 
      ring: 'focus:ring-emerald-500/10',
      shadow: 'shadow-emerald-500/20'
    },
    temp: { 
      text: 'text-indigo-600', 
      bg: 'bg-indigo-600', 
      border: 'focus:border-indigo-500', 
      ring: 'focus:ring-indigo-500/10',
      shadow: 'shadow-indigo-500/20'
    },
    expense: { 
      text: 'text-amber-600', 
      bg: 'bg-amber-600', 
      border: 'focus:border-amber-500', 
      ring: 'focus:ring-amber-500/10',
      shadow: 'shadow-amber-500/20'
    },
  }

  const currentTheme = activeTab ? themes[activeTab] : themes.medicine
  const dynamicInputClass = cn(
    'w-full rounded-[1.25rem] border border-emerald-100 bg-white px-4 py-3.5 text-sm font-bold text-emerald-950',
    'transition-all duration-300 placeholder:text-emerald-300',
    'focus:outline-none focus:ring-4 shadow-sm',
    currentTheme.border,
    currentTheme.ring
  )

  return (
    <Dialog 
      isOpen={activeTab !== null} 
      onClose={onClose}
      title={activeTab ? `تسجيل ${TABS[activeTab].label} جديد` : ''}
    >
      <div className="space-y-6 pt-2">
        {activeTab === 'mortality' && (
          <div className="space-y-5">
            <FormField label="عدد الطيور النافقة" required>
              <NumericInput value={mQty} onChange={setMQty} placeholder="0" min={1} className={dynamicInputClass} />
            </FormField>
            <FormField label="سبب أو ملاحظات">
              <input type="text" value={mReason} onChange={(e) => setMReason(e.target.value)} placeholder="مثال: إجهاد حراري..." className={dynamicInputClass} />
            </FormField>
          </div>
        )}

        {activeTab === 'feed' && (
          <div className="space-y-5">
            <FormField label="نوع العلف" required>
              <SelectInput value={feedItemId} onChange={setFeedItemId} options={feedItems.map((i) => ({ value: String(i.id), label: i.name }))} placeholder="اختر النوع من المخزن" emptyMessage="لا يوجد مخزون علف" className={dynamicInputClass} />
            </FormField>
            <FormField label="الكمية (بالكيلو/كيس)" required>
              <NumericInput value={feedQty} onChange={setFeedQty} placeholder="0.00" min={0.001} step={0.5} className={dynamicInputClass} />
            </FormField>
          </div>
        )}

        {activeTab === 'medicine' && (
          <div className="space-y-5">
            <FormField label="الدواء المختص" required>
              <SelectInput value={medItemId} onChange={setMedItemId} options={medItems.map((i) => ({ value: String(i.id), label: i.name }))} placeholder="اختر الصنف" emptyMessage="لا يوجد مخزون أدوية" className={dynamicInputClass} />
            </FormField>
            <FormField label="الكمية المستخدمة" required>
              <NumericInput value={medQty} onChange={setMedQty} placeholder="0.00" min={0.001} step={0.1} className={dynamicInputClass} />
            </FormField>
          </div>
        )}

        {activeTab === 'temp' && (
          <div className="space-y-5">
            <FormField label="وقت القراءة" required>
              <div className="grid grid-cols-3 gap-2">
                {[{ id: 'morning', label: 'صباحاً' }, { id: 'afternoon', label: 'ظهراً' }, { id: 'evening', label: 'مساءً' }].map((t) => (
                  <button 
                    key={t.id} 
                    onClick={() => setTempTime(t.id as any)} 
                    className={cn(
                      "py-3 rounded-[1.25rem] text-xs font-bold border transition-all duration-300", 
                      tempTime === t.id 
                        ? cn(currentTheme.bg, "border-transparent text-white shadow-lg", currentTheme.shadow) 
                        : "bg-white border-emerald-50 text-emerald-600/70 hover:border-emerald-100"
                    )}
                  >
                    {t.label}
                  </button>
                ))}
              </div>
            </FormField>
            <FormField label="درجة الحرارة (°C)" required>
              <NumericInput value={tempVal} onChange={setTempVal} placeholder="0.0" step={0.1} className={dynamicInputClass} />
            </FormField>
          </div>
        )}

        {activeTab === 'expense' && (
          <div className="space-y-5">
            <FormField label="التصنيف" required>
              <SelectInput value={expType} onChange={setExpType} options={[
                { value: 'water', label: 'مياه' },
                { value: 'bedding', label: 'فرشة (نشارة)' },
                { value: 'vaccine', label: 'تحصينات/لقاحات' },
                { value: 'other', label: 'أخرى' },
              ]} placeholder="اختر التصنيف..." emptyMessage="" className={dynamicInputClass} />
            </FormField>
            <div className="grid grid-cols-2 gap-3">
              <FormField label={expType === 'water' ? 'العدد (اختياري)' : 'الكمية (اختياري)'}>
                <NumericInput value={expQty} onChange={setExpQty} placeholder="0" className={dynamicInputClass} />
              </FormField>
              <FormField label="إجمالي المبلغ" required>
                <NumericInput value={expPrice} onChange={setExpPrice} placeholder="0.00" className={dynamicInputClass} />
              </FormField>
            </div>
            {expType === 'other' && (
              <FormField label="وصف المصروف" required>
                <input type="text" value={expDescription} onChange={(e) => setExpDescription(e.target.value)} placeholder="اكتب وصفاً..." className={dynamicInputClass} />
              </FormField>
            )}
          </div>
        )}

        {error && (
          <div className="flex items-center gap-2 rounded-[1.25rem] border border-rose-100 bg-rose-50/50 p-4 text-rose-700 animate-in fade-in slide-in-from-top-1 text-[11px] font-bold shadow-inner">
            <AlertCircle className="h-4 w-4" />
            {error}
          </div>
        )}

        <button 
          onClick={handleSubmit} 
          disabled={loading} 
          className={cn(
            "w-full rounded-[1.25rem] py-4 text-sm font-bold text-white transition-all duration-300 active:scale-[0.98] disabled:opacity-50 shadow-xl",
            currentTheme.bg,
            currentTheme.shadow
          )}
        >
          {loading ? 'جارٍ الحفظ...' : 'تأكيد الحفظ'}
        </button>
      </div>
    </Dialog>
  )
}

function FormField({ label, children, required }: any) {
  return (
    <div className="flex flex-col gap-2">
      <label className="text-[11px] font-extrabold text-emerald-700/70 uppercase tracking-wider px-1">{label} {required && <span className="text-rose-400">*</span>}</label>
      {children}
    </div>
  )
}

function NumericInput({ value, onChange, placeholder, min, step, className }: any) {
  return <input type="number" inputMode="decimal" value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} min={min} step={step} className={className} dir="ltr" />
}

function SelectInput({ value, onChange, options, placeholder, emptyMessage, className }: any) {
  if (options.length === 0) return <div className="rounded-[1.25rem] border border-dashed border-emerald-200 bg-emerald-50/50 px-4 py-4 text-[10px] font-bold text-emerald-500 italic text-center uppercase tracking-tight">{emptyMessage}</div>
  return (
    <select value={value} onChange={(e) => onChange(e.target.value)} className={className}>
      <option value="">{placeholder}</option>
      {options.map((o: any) => <option key={o.value} value={o.value}>{o.label}</option>)}
    </select>
  )
}

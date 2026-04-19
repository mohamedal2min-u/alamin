'use client'

import React, { useState, useEffect } from 'react'
import Link from 'next/link'
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
  const [feedBags, setFeedBags]     = useState('')
  const [feedExtraKg, setFeedExtraKg] = useState('')

  // Medicine
  const [medItems, setMedItems]   = useState<InventoryItem[]>([])
  const [medItemId, setMedItemId] = useState('')
  const [medQty, setMedQty]       = useState('')

  // Temp
  const [tempTime, setTempTime]   = useState<'morning' | 'afternoon' | 'evening'>('morning')
  const [tempVal, setTempVal]     = useState('')

  // Expense
  const [expType, setExpType]     = useState('bedding')
  const [expQty, setExpQty]       = useState('')
  const [expPrice, setExpPrice]   = useState('')
  const [expDescription, setExpDescription] = useState('')
  const [expNotes, setExpNotes]   = useState('')
  const [expUnitHint, setExpUnitHint] = useState('كيس')

  const calculatedTotal = (activeTab === 'expense') 
    ? (Number(expQty) * Number(expPrice)) 
    : 0

  useEffect(() => {
    setError(null)
    if (activeTab === 'feed') inventoryApi.items('feed').then((res) => setFeedItems(res.data))
    if (activeTab === 'medicine') inventoryApi.items('medicine').then((res) => setMedItems(res.data))
    if (activeTab === 'temp' && initialExtra?.time) setTempTime(initialExtra.time as 'morning' | 'afternoon' | 'evening')
  }, [activeTab, initialExtra])

  const resetFields = () => {
    setMQty(''); setMReason('')
    setFeedItemId(''); setFeedQty(''); setFeedBags(''); setFeedExtraKg('')
    setMedItemId(''); setMedQty('')
    setTempVal('')
    setExpType('bedding'); setExpQty(''); setExpPrice(''); setExpDescription(''); setExpNotes(''); setExpUnitHint('كيس')
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
        const item = feedItems.find(i => String(i.id) === feedItemId)
        let finalQty = Number(feedQty)
        if (item && item.unit_value > 1) {
          finalQty = Number(feedBags) + (Number(feedExtraKg) / item.unit_value)
        }
        if (!finalQty || finalQty <= 0) { setError('أدخل كمية صحيحة'); setLoading(false); return }
        await quickEntryApi.logFeed(flockId, { item_id: Number(feedItemId), quantity: finalQty, entry_date: date })
      } else if (activeTab === 'medicine') {
        if (!medItemId) { setError('اختر صنف الدواء'); setLoading(false); return }
        if (!medQty || Number(medQty) <= 0) { setError('أدخل كمية صحيحة'); setLoading(false); return }
        await quickEntryApi.logMedicine(flockId, { item_id: Number(medItemId), quantity: Number(medQty), entry_date: date })
      } else if (activeTab === 'temp') {
        if (!tempVal || isNaN(Number(tempVal))) { setError('أدخل قيمة حرارة صحيحة'); setLoading(false); return }
        await workerApi.logTemperature(flockId, { log_date: date, time_of_day: tempTime, temperature: Number(tempVal) })
      } else if (activeTab === 'expense') {
        if (!expQty || Number(expQty) <= 0) { setError('العدد مطلوب'); setLoading(false); return }
        if (expType === 'other' && !expDescription.trim()) { setError('وصف المصروف مطلوب'); setLoading(false); return }
        const qty = Number(expQty)
        const price = Number(expPrice)
        const hasPrice = !isNaN(price) && price > 0
        await quickEntryApi.logExpense(flockId, { 
          expense_type: expType,
          quantity: qty,
          unit_price: hasPrice ? price : undefined,
          total_amount: hasPrice ? qty * price : 0,
          description: expDescription || undefined,
          notes: expNotes || undefined,
          entry_date: date,
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
      text: 'text-primary-600', 
      bg: 'bg-primary-600', 
      border: 'focus:border-primary-500', 
      ring: 'focus:ring-primary-500/10',
      shadow: 'shadow-primary-500/20'
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
    'w-full rounded-[1.25rem] border border-primary-100 bg-white px-4 py-3.5 text-sm font-bold text-primary-950',
    'transition-all duration-300 placeholder:text-primary-300',
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
              <SelectInput value={feedItemId} onChange={(val: string) => { setFeedItemId(val); setFeedBags(''); setFeedExtraKg(''); setFeedQty(''); }} options={feedItems.map((i) => ({ value: String(i.id), label: i.name }))} placeholder="اختر النوع من المخزن" emptyMessage="لا يوجد مخزون علف" className={dynamicInputClass} />
            </FormField>
            
            {(() => {
              const item = feedItems.find(i => String(i.id) === feedItemId)
              if (item && item.unit_value > 1) {
                return (
                  <div className="space-y-4">
                    <div className="grid grid-cols-2 gap-3">
                      <FormField label={`عدد الأكياس (${item.input_unit})`} required>
                        <NumericInput value={feedBags} onChange={setFeedBags} placeholder="0" min={0} className={dynamicInputClass} />
                      </FormField>
                      <FormField label="وزن إضافي (كيلو)">
                        <NumericInput value={feedExtraKg} onChange={setFeedExtraKg} placeholder="0.00" min={0} step={0.01} className={dynamicInputClass} />
                      </FormField>
                    </div>
                    {(Number(feedBags) > 0 || Number(feedExtraKg) > 0) && (
                      <div className="flex items-center gap-2 rounded-2xl bg-primary-50/50 px-4 py-3 text-[11px] font-bold text-primary-700 border border-primary-100/50">
                        <div className="w-1.5 h-1.5 rounded-full bg-primary-500 animate-pulse" />
                        الإجمالي: {(Number(feedBags) * item.unit_value + Number(feedExtraKg)).toFixed(2)} كجم
                      </div>
                    )}
                  </div>
                )
              }
              return (
                <FormField label="الكمية (بالكيلو)" required>
                  <NumericInput value={feedQty} onChange={setFeedQty} placeholder="0.00" min={0.001} step={0.5} className={dynamicInputClass} />
                </FormField>
              )
            })()}
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
                        : "bg-white border-primary-50 text-primary-600/70 hover:border-primary-100"
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
            {/* Quick-Entry Preset Buttons */}
            <FormField label="نوع المصروف" required>
              <select
                value={expType}
                onChange={(e) => {
                  const val = e.target.value;
                  setExpType(val);
                  const preset = [
                    { code: 'bedding',      unitHint: 'كيس' },
                    { code: 'farm_wash',    unitHint: 'مرة' },
                    { code: 'disinfectant', unitHint: 'قطعة' },
                    { code: 'other',        unitHint: 'وحدة' },
                  ].find(p => p.code === val);
                  if (preset) setExpUnitHint(preset.unitHint);
                }}
                className={dynamicInputClass}
              >
                <option value="bedding">نشارة</option>
                <option value="farm_wash">غسيل مدجنة</option>
                <option value="disinfectant">معقم</option>
                <option value="other">مصروف مخصص</option>
              </select>
            </FormField>

            {/* Custom description (only for 'other') */}
            {expType === 'other' && (
              <FormField label="وصف المصروف" required>
                <input type="text" value={expDescription} onChange={(e) => setExpDescription(e.target.value)} placeholder="مثال: إصلاح سقف..." className={dynamicInputClass} />
              </FormField>
            )}

            {/* Quantity + Unit Price */}
            <div className="grid grid-cols-2 gap-3">
              <FormField label={`العدد (${expUnitHint})`} required>
                <NumericInput value={expQty} onChange={setExpQty} placeholder="0" min={1} step={1} className={dynamicInputClass} />
              </FormField>
              <FormField label="سعر الوحدة ($)">
                <NumericInput value={expPrice} onChange={setExpPrice} placeholder="0.00" min={0} step={0.01} className={dynamicInputClass} />
              </FormField>
            </div>

            {/* Calculated total or debt warning */}
            {(Number(expQty) > 0) && (
              <div className={cn(
                'flex items-center gap-3 rounded-[1.25rem] px-4 py-3 text-[11px] font-bold border',
                Number(expPrice) > 0
                  ? 'bg-primary-50/50 border-primary-100/50 text-primary-700'
                  : 'bg-amber-50/50 border-amber-100/50 text-amber-700',
              )}>
                <div className={cn('w-1.5 h-1.5 rounded-full animate-pulse', Number(expPrice) > 0 ? 'bg-primary-500' : 'bg-amber-500')} />
                {Number(expPrice) > 0
                  ? `الإجمالي: ${(Number(expQty) * Number(expPrice)).toFixed(2)} $`
                  : <>بدون سعر — سيُضاف إلى{' '}<Link href="/accounting?tab=review" className="underline hover:opacity-80">الذمم والمراجعة</Link></>}
              </div>
            )}

            {/* Notes */}
            <FormField label="ملاحظات">
              <input type="text" value={expNotes} onChange={(e) => setExpNotes(e.target.value)} placeholder="اختياري..." className={dynamicInputClass} />
            </FormField>
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
      <label className="text-[11px] font-extrabold text-primary-700/70 uppercase tracking-wider px-1">{label} {required && <span className="text-rose-400">*</span>}</label>
      {children}
    </div>
  )
}

function NumericInput({ value, onChange, placeholder, min, step, className }: any) {
  return <input type="number" inputMode="decimal" value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} min={min} step={step} className={className} dir="ltr" />
}

function SelectInput({ value, onChange, options, placeholder, emptyMessage, className }: any) {
  if (options.length === 0) return <div className="rounded-[1.25rem] border border-dashed border-primary-200 bg-primary-50/50 px-4 py-4 text-[10px] font-bold text-primary-500 italic text-center uppercase tracking-tight">{emptyMessage}</div>
  return (
    <select value={value} onChange={(e) => onChange(e.target.value)} className={className}>
      <option value="">{placeholder}</option>
      {options.map((o: any) => <option key={o.value} value={o.value}>{o.label}</option>)}
    </select>
  )
}


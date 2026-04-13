'use client'

import { useState, useEffect } from 'react'
import { 
  AlertCircle, Skull, 
  Wheat, Syringe, ThermometerSun
} from 'lucide-react'
import { inventoryApi } from '@/lib/api/inventory'
import { quickEntryApi } from '@/lib/api/quick-entry'
import { mortalitiesApi } from '@/lib/api/mortalities'
import { workerApi } from '@/lib/api/worker'
import { cn } from '@/lib/utils'
import { Dialog } from '@/components/ui/Dialog'
import type { InventoryItem } from '@/types/dashboard'
import { toast } from 'sonner'

type Tab = 'mortality' | 'feed' | 'medicine' | 'temp'

interface Props {
  flockId: number
  activeTab: Tab | null
  initialExtra?: any
  onClose: () => void
  onSuccess?: () => void
}

const TABS: Record<Tab, { label: string; icon: any }> = {
  mortality: { label: 'نفوق',   icon: Skull },
  feed:      { label: 'علف',    icon: Wheat },
  medicine:  { label: 'دواء',   icon: Syringe },
  temp:      { label: 'حرارة',  icon: ThermometerSun },
}

export function WorkerEntryDialog({ flockId, activeTab, initialExtra, onClose, onSuccess }: Props) {
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

  useEffect(() => {
    if (activeTab === 'feed') inventoryApi.items('feed').then((res) => setFeedItems(res.data))
    if (activeTab === 'medicine') inventoryApi.items('medicine').then((res) => setMedItems(res.data))
    if (activeTab === 'temp' && initialExtra?.time) setTempTime(initialExtra.time)
  }, [activeTab, initialExtra])

  const resetFields = () => {
    setMQty(''); setMReason('')
    setFeedItemId(''); setFeedQty('')
    setMedItemId(''); setMedQty('')
    setTempVal('')
    setError(null)
  }

  const handleSubmit = async () => {
    if (!activeTab) return
    setError(null)
    setLoading(true)
    try {
      // Get local date string YYYY-MM-DD
      const now = new Date()
      const date = now.getFullYear() + '-' + 
                   String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                   String(now.getDate()).padStart(2, '0')
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

  return (
    <Dialog 
      isOpen={activeTab !== null} 
      onClose={onClose}
      title={activeTab ? `تسجيل ${TABS[activeTab].label} جديد` : ''}
    >
      <div className="space-y-5">
        {activeTab === 'mortality' && (
          <div className="space-y-4">
            <FormField label="عدد الطيور النافقة" required>
              <NumericInput value={mQty} onChange={setMQty} placeholder="0" min={1} />
            </FormField>
            <FormField label="سبب أو ملاحظات">
              <input type="text" value={mReason} onChange={(e) => setMReason(e.target.value)} placeholder="مثال: إجهاد حراري..." className={inputClass} />
            </FormField>
          </div>
        )}

        {activeTab === 'feed' && (
          <div className="space-y-4">
            <FormField label="نوع العلف" required>
              <SelectInput value={feedItemId} onChange={setFeedItemId} options={feedItems.map((i) => ({ value: String(i.id), label: i.name }))} placeholder="اختر النوع من المخزن" emptyMessage="لا يوجد مخزون علف" />
            </FormField>
            <FormField label="الكمية (بالكيلو/كيس)" required>
              <NumericInput value={feedQty} onChange={setFeedQty} placeholder="0.00" min={0.001} step={0.5} />
            </FormField>
          </div>
        )}

        {activeTab === 'medicine' && (
          <div className="space-y-4">
            <FormField label="الدواء المختص" required>
              <SelectInput value={medItemId} onChange={setMedItemId} options={medItems.map((i) => ({ value: String(i.id), label: i.name }))} placeholder="اختر الصنف" emptyMessage="لا يوجد مخزون أدوية" />
            </FormField>
            <FormField label="الكمية المستخدمة" required>
              <NumericInput value={medQty} onChange={setMedQty} placeholder="0.00" min={0.001} step={0.1} />
            </FormField>
          </div>
        )}

        {activeTab === 'temp' && (
          <div className="space-y-4">
            <FormField label="وقت القراءة" required>
              <div className="grid grid-cols-3 gap-2">
                {[{ id: 'morning', label: 'صباحاً' }, { id: 'afternoon', label: 'ظهراً' }, { id: 'evening', label: 'مساءً' }].map((t) => (
                  <button key={t.id} onClick={() => setTempTime(t.id as any)} className={cn("py-2 rounded-lg text-xs font-bold border", tempTime === t.id ? "bg-amber-600 border-amber-600 text-white" : "bg-white border-slate-200 text-slate-600")}>
                    {t.label}
                  </button>
                ))}
              </div>
            </FormField>
            <FormField label="درجة الحرارة (°C)" required>
              <NumericInput value={tempVal} onChange={setTempVal} placeholder="0.0" step={0.1} />
            </FormField>
          </div>
        )}

        {error && (
          <div className="flex items-center gap-2 rounded-xl border border-rose-100 bg-rose-50 p-3 text-rose-700 animate-in fade-in slide-in-from-top-1 text-[11px] font-bold">
            <AlertCircle className="h-4 w-4" />
            {error}
          </div>
        )}

        <button onClick={handleSubmit} disabled={loading} className="w-full rounded-xl bg-emerald-600 py-3.5 text-sm font-bold text-white shadow-lg shadow-emerald-200 hover:bg-emerald-700 active:scale-95 disabled:opacity-50">
          {loading ? 'جارٍ الحفظ...' : 'تأكيد الحفظ'}
        </button>
      </div>
    </Dialog>
  )
}

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-emerald-500 focus:outline-none'

function FormField({ label, children, required }: any) {
  return (
    <div className="flex flex-col gap-1.5">
      <label className="text-[11px] font-bold text-slate-500">{label} {required && <span className="text-rose-400">*</span>}</label>
      {children}
    </div>
  )
}

function NumericInput({ value, onChange, placeholder, min, step }: any) {
  return <input type="number" inputMode="decimal" value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} min={min} step={step} className={inputClass} dir="ltr" />
}

function SelectInput({ value, onChange, options, placeholder, emptyMessage }: any) {
  if (options.length === 0) return <div className="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-[10px] font-bold text-slate-400 italic">{emptyMessage}</div>
  return (
    <select value={value} onChange={(e) => onChange(e.target.value)} className={inputClass}>
      <option value="">{placeholder}</option>
      {options.map((o: any) => <option key={o.value} value={o.value}>{o.label}</option>)}
    </select>
  )
}

// frontend/src/components/worker/WorkerQuickEntryCard.tsx
'use client'

import { useState, useEffect } from 'react'
import { 
  AlertCircle, Skull, 
  Wheat, Syringe, ThermometerSun, ClipboardEdit, Sparkles
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

function formatNumber(num: number) {
  return new Intl.NumberFormat('en-US').format(num)
}
import { Dialog } from '@/components/ui/Dialog'
import type { InventoryItem } from '@/types/dashboard'
import { toast } from 'sonner'

type Tab = 'mortality' | 'feed' | 'medicine' | 'temp'

const TABS: { key: Tab; label: string; desc: string; icon: any; color: string; gradient: string; lightBg: string; shadow: string }[] = [
  { 
    key: 'mortality', 
    label: 'نفوق',   
    desc: 'سجل الخسائر',
    icon: Skull, 
    color: 'text-rose-600',    
    gradient: 'from-rose-500 to-rose-600',    
    lightBg: 'bg-rose-50/50',
    shadow: 'shadow-rose-100'
  },
  { 
    key: 'feed',      
    label: 'علف',    
    desc: 'توزيع الأعلاف',
    icon: Wheat,   
    color: 'text-blue-600',    
    gradient: 'from-blue-500 to-indigo-500',  
    lightBg: 'bg-blue-50/50',
    shadow: 'shadow-blue-100'
  },
  { 
    key: 'medicine',  
    label: 'دواء',   
    desc: 'لقاحات وعلاج',
    icon: Syringe,       
    color: 'text-emerald-600', 
    gradient: 'from-emerald-500 to-teal-500', 
    lightBg: 'bg-emerald-50/50',
    shadow: 'shadow-emerald-100'
  },
  { 
    key: 'temp',      
    label: 'حرارة',  
    desc: 'قياس العنبر',
    icon: ThermometerSun, 
    color: 'text-amber-600',   
    gradient: 'from-amber-500 to-orange-500', 
    lightBg: 'bg-amber-50/50',
    shadow: 'shadow-amber-100'
  },
]

const today = () => new Date().toISOString().slice(0, 10)

interface Props {
  flockId: number
  onSuccess?: () => void
}

export function WorkerQuickEntryCard({ flockId, onSuccess }: Props) {
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

  // Temp
  const [tempTime, setTempTime]   = useState<'morning' | 'afternoon' | 'evening'>('morning')
  const [tempVal, setTempVal]     = useState('')

  useEffect(() => {
    inventoryApi.items('feed').then((res) => setFeedItems(res.data)).catch(() => {})
    inventoryApi.items('medicine').then((res) => setMedItems(res.data)).catch(() => {})
  }, [flockId])

  const resetFields = () => {
    setMQty(''); setMReason('')
    setFeedItemId(''); setFeedQty('')
    setMedItemId(''); setMedQty('')
    setTempVal('')
    setError(null)
  }

  const handleCardClick = (key: Tab) => {
    if (activeTab === key) {
      setActiveTab(null)
    } else {
      setActiveTab(key)
      setError(null)
      setSuccess(false)
    }
  }

  const handleSubmit = async () => {
    if (!activeTab) return
    setError(null)
    setLoading(true)
    try {
      const date = today()
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

      setSuccess(true)
      resetFields()
      onSuccess?.()
      toast.success('تم الحفظ بنجاح')
      setTimeout(() => {
        setSuccess(false)
        setActiveTab(null)
      }, 1500)
    } catch (err: any) {
      const msg = err.response?.data?.message || 'حدث خطأ، حاول مجدداً'
      setError(typeof msg === 'string' ? msg : 'خطأ في البيانات')
    } finally {
      setLoading(false)
    }
  }



  return (
    <div className="relative overflow-hidden rounded-3xl bg-slate-50/50 p-5 border border-slate-100 shadow-sm">
      {/* ── Background Decoration ── */}
      <div className="absolute -left-10 -top-10 h-32 w-32 rounded-full bg-emerald-500/5 blur-3xl" />
      <div className="absolute -right-10 -bottom-10 h-32 w-32 rounded-full bg-amber-500/5 blur-3xl" />

      {/* ── Section Label ── */}
      <div className="relative mb-5 flex items-center justify-between px-1">
        <div className="flex items-center gap-3">
          <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 shadow-sm">
            <ClipboardEdit className="h-4 w-4" />
          </div>
          <div>
            <h3 className="text-sm font-bold text-slate-800 tracking-tight">التسجيل المباشر</h3>
            <p className="text-[10px] font-medium text-slate-400">سجل بياناتك بلمسة واحدة</p>
          </div>
        </div>
        <div className="flex h-7 w-7 items-center justify-center rounded-full bg-white text-slate-200 shadow-sm">
          <Sparkles className="h-3.5 w-3.5" />
        </div>
      </div>

      {/* ── 2x2 Premium Interaction Grid ── */}
      <div className="relative grid grid-cols-2 gap-4 mb-6">
        {TABS.map((t) => {
          const isActive = activeTab === t.key
          return (
            <button
              key={t.key}
              onClick={() => handleCardClick(t.key)}
              className={cn(
                "group relative flex flex-row-reverse items-center gap-3 rounded-3xl border p-3.5 transition-all duration-300 active:scale-[0.97]",
                isActive 
                  ? "bg-white border-white shadow-2xl shadow-emerald-500/20 ring-1 ring-emerald-500/20" 
                  : "bg-white/40 backdrop-blur-md border-white/60 shadow-sm hover:bg-white hover:border-white hover:shadow-xl hover:shadow-slate-200/50"
              )}
            >
              {/* Icon Container (Squircle) */}
              <div className={cn(
                "flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl transition-all duration-500",
                isActive 
                  ? cn("bg-gradient-to-br ring-4 ring-white shadow-lg", t.gradient, "text-white") 
                  : cn(t.lightBg, t.color, "group-hover:scale-110 group-hover:rotate-3 shadow-inner")
              )}>
                <t.icon className={cn("h-6 w-6", isActive ? "animate-pulse" : "")} />
              </div>

              {/* Text Labels */}
              <div className="flex flex-col text-right">
                <span className={cn(
                  "text-[13px] font-extrabold tracking-tight transition-colors",
                  isActive ? "text-slate-900" : "text-slate-700 group-hover:text-slate-900"
                )}>
                  {t.label}
                </span>
                <span className={cn(
                  "text-[9px] font-bold opacity-60 transition-colors",
                  isActive ? "text-emerald-600" : "text-slate-400 group-hover:text-slate-500"
                )}>
                  {t.desc}
                </span>
              </div>

              {/* Active Indicator Blob */}
              {isActive && (
                <div className="absolute -left-1 top-1/2 -translate-y-1/2 h-8 w-1 rounded-full bg-emerald-500" />
              )}
            </button>
          )
        })}
      </div>

      {/* ── End Day Button ── */}


      {/* ── Modal Entry Form ── */}
      <Dialog 
        isOpen={activeTab !== null} 
        onClose={() => setActiveTab(null)}
        title={activeTab ? `تسجيل ${TABS.find(t => t.key === activeTab)?.label} جديد` : ''}
      >
        <div className="space-y-5">
          <div className="space-y-5">
            {/* ── Mortality ── */}
            {activeTab === 'mortality' && (
              <div className="space-y-4">
                <FormField label="عدد الطيور النافقة" required>
                  <NumericInput value={mQty} onChange={setMQty} placeholder="0" min={1} />
                </FormField>
                <FormField label="سبب أو ملاحظات">
                  <input
                    type="text"
                    value={mReason}
                    onChange={(e) => setMReason(e.target.value)}
                    placeholder="مثال: إجهاد حراري..."
                    className={inputClass}
                  />
                </FormField>
              </div>
            )}

            {/* ── Feed ── */}
            {activeTab === 'feed' && (
              <div className="space-y-4">
                <FormField label="نوع العلف" required>
                  <SelectInput
                    value={feedItemId}
                    onChange={setFeedItemId}
                    options={feedItems.map((i) => ({ value: String(i.id), label: i.name }))}
                    placeholder="اختر النوع من المخزن"
                    emptyMessage="لا يوجد مخزون علف"
                  />
                </FormField>
                <FormField label="الكمية (بالكيلو/كيس)" required>
                  <NumericInput value={feedQty} onChange={setFeedQty} placeholder="0.00" min={0.001} step={0.5} />
                </FormField>
              </div>
            )}

            {/* ── Medicine ── */}
            {activeTab === 'medicine' && (
              <div className="space-y-4">
                <FormField label="الدواء المختص" required>
                  <SelectInput
                    value={medItemId}
                    onChange={setMedItemId}
                    options={medItems.map((i) => ({ value: String(i.id), label: i.name }))}
                    placeholder="اختر الصنف"
                    emptyMessage="لا يوجد مخزون أدوية"
                  />
                </FormField>
                <FormField label="الكمية المستخدمة" required>
                  <NumericInput value={medQty} onChange={setMedQty} placeholder="0.00" min={0.001} step={0.1} />
                </FormField>
              </div>
            )}

            {/* ── Temperature ── */}
            {activeTab === 'temp' && (
              <div className="space-y-4">
                <FormField label="وقت القراءة" required>
                  <div className="grid grid-cols-3 gap-2">
                    {[
                      { id: 'morning', label: 'صباحاً' },
                      { id: 'afternoon', label: 'ظهراً' },
                      { id: 'evening', label: 'مساءً' },
                    ].map((t) => (
                      <button
                        key={t.id}
                        onClick={() => setTempTime(t.id as any)}
                        className={cn(
                          "py-2 rounded-lg text-xs font-bold transition-all border",
                          tempTime === t.id 
                            ? "bg-amber-600 border-amber-600 text-white" 
                            : "bg-white border-slate-200 text-slate-600 hover:border-amber-300"
                        )}
                      >
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

            {/* Feedback Messages */}
            <div className="min-h-[40px]">
              {error && (
                <div className="flex items-center gap-2 rounded-xl border border-rose-100 bg-rose-50 p-3 text-rose-700 animate-in fade-in slide-in-from-top-1">
                  <AlertCircle className="h-4 w-4" />
                  <p className="text-[11px] font-bold">{error}</p>
                </div>
              )}
            </div>

            {/* Submit Button */}
            <button
              onClick={handleSubmit}
              disabled={loading || success}
              className={cn(
                "group relative w-full overflow-hidden rounded-xl bg-emerald-600 py-3.5 text-center shadow-lg shadow-emerald-200 transition-all duration-200 hover:bg-emerald-700 active:scale-95 disabled:opacity-50",
                loading && "cursor-not-allowed"
              )}
            >
              <span className="text-sm font-bold text-white">
                {loading ? 'جارٍ الحفظ...' : 'تأكيد الحفظ'}
              </span>
            </button>
          </div>
        </div>
      </Dialog>
    </div>
  )
}

const inputClass =
  'w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 placeholder-slate-300 focus:border-emerald-500 focus:bg-white focus:outline-none transition-all shadow-sm'

function FormField({ label, children, required }: { label: string; children: React.ReactNode; required?: boolean }) {
  return (
    <div className="flex flex-col gap-1.5">
      <label className="flex items-center gap-1 text-[11px] font-bold text-slate-500">
        {label}
        {required && <span className="text-rose-400">*</span>}
      </label>
      {children}
    </div>
  )
}

function NumericInput({ value, onChange, placeholder, min, step }: {
  value: string; onChange: (v: string) => void; placeholder: string; min?: number; step?: number
}) {
  return (
    <input
      type="number"
      inputMode="decimal"
      value={value}
      onChange={(e) => onChange(e.target.value)}
      placeholder={placeholder}
      min={min}
      step={step}
      className={inputClass}
      dir="ltr"
    />
  )
}

function SelectInput({ value, onChange, options, placeholder, emptyMessage }: {
  value: string
  onChange: (v: string) => void
  options: { value: string; label: string }[]
  placeholder: string
  emptyMessage: string
}) {
  if (options.length === 0) {
    return (
      <div className="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-[10px] font-bold text-slate-400 italic">
        {emptyMessage}
      </div>
    )
  }
  return (
    <select value={value} onChange={(e) => onChange(e.target.value)} className={inputClass}>
      <option value="">{placeholder}</option>
      {options.map((o) => (
        <option key={o.value} value={o.value}>{o.label}</option>
      ))}
    </select>
  )
}

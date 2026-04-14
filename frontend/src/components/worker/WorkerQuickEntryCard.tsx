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

const TABS: { key: Tab; label: string; icon: any; color: string; gradient: string; lightBg: string }[] = [
  { key: 'mortality', label: 'نفوق',   icon: Skull, color: 'text-rose-600',    gradient: 'from-rose-500 to-rose-600',    lightBg: 'bg-rose-50/50' },
  { key: 'feed',      label: 'علف',    icon: Wheat,   color: 'text-blue-600',    gradient: 'from-blue-500 to-indigo-500',  lightBg: 'bg-blue-50/50' },
  { key: 'medicine',  label: 'دواء',   icon: Syringe,       color: 'text-emerald-600', gradient: 'from-emerald-500 to-teal-500', lightBg: 'bg-emerald-50/50' },
  { key: 'temp',      label: 'حرارة',  icon: ThermometerSun, color: 'text-amber-600',   gradient: 'from-amber-500 to-orange-500', lightBg: 'bg-amber-50/50' },
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


  const themes = {
    mortality: { 
      text: 'text-rose-600', 
      bg: 'bg-rose-600', 
      border: 'focus:border-rose-500', 
      ring: 'focus:ring-rose-500/10', 
      shadow: 'shadow-rose-500/20',
      activeRing: 'ring-rose-500/10',
      iconShadow: 'shadow-rose-500/30'
    },
    feed: { 
      text: 'text-amber-600', 
      bg: 'bg-amber-600', 
      border: 'focus:border-amber-500', 
      ring: 'focus:ring-amber-500/10', 
      shadow: 'shadow-amber-500/20',
      activeRing: 'ring-amber-500/10',
      iconShadow: 'shadow-amber-500/30'
    },
    medicine: { 
      text: 'text-emerald-600', 
      bg: 'bg-emerald-600', 
      border: 'focus:border-emerald-500', 
      ring: 'focus:ring-emerald-500/10', 
      shadow: 'shadow-emerald-500/20',
      activeRing: 'ring-emerald-500/10',
      iconShadow: 'shadow-emerald-500/30'
    },
    temp: { 
      text: 'text-indigo-600', 
      bg: 'bg-indigo-600', 
      border: 'focus:border-indigo-500', 
      ring: 'focus:ring-indigo-500/10', 
      shadow: 'shadow-indigo-500/20',
      activeRing: 'ring-indigo-500/10',
      iconShadow: 'shadow-indigo-500/30'
    },
  }

  const currentTheme = activeTab ? themes[activeTab] : themes.medicine
  const dynamicInputClass = cn(
    'w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-bold text-slate-900',
    'transition-all duration-300 placeholder:text-slate-300',
    'focus:outline-none focus:ring-4 shadow-sm',
    currentTheme.border,
    currentTheme.ring
  )

  return (
    <div className="relative overflow-hidden rounded-[2.5rem] bg-white p-6 shadow-xl shadow-slate-200/60 border border-slate-50">
      {/* ── Background Decoration ── */}
      <div className="absolute -left-10 -top-10 h-32 w-32 rounded-full bg-emerald-500/5 blur-3xl opacity-50" />
      <div className="absolute -right-10 -bottom-10 h-32 w-32 rounded-full bg-amber-500/5 blur-3xl opacity-50" />

      {/* ── Section Label ── */}
      <div className="relative mb-6 flex items-center justify-between px-1">
        <div className="flex items-center gap-3">
          <div className="flex h-10 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 px-3 font-bold text-xs gap-2">
            <ClipboardEdit className="h-4 w-4" />
            <span>تسجيل جديد</span>
          </div>
          <div>
            <h3 className="text-base font-extrabold text-slate-800">التسجيل السريع</h3>
            <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Quick Operations</p>
          </div>
        </div>
        <div className="h-2.5 w-2.5 animate-pulse rounded-full bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.5)]" />
      </div>

      {/* ── 4-Card Interaction Grid ── */}
      <div className="relative grid grid-cols-4 gap-3 mb-6">
        {TABS.map((t) => {
          const isActive = activeTab === t.key
          const theme = themes[t.key as Tab]
          return (
            <button
              key={t.key}
              onClick={() => handleCardClick(t.key)}
              className={cn(
                "group relative flex flex-col items-center justify-center rounded-2xl border transition-all duration-500 active:scale-90",
                isActive 
                  ? cn("border-transparent bg-white shadow-2xl ring-4", theme.activeRing) 
                  : "border-slate-100 bg-white shadow-sm hover:border-slate-200 hover:shadow-md"
              )}
            >
              {/* Icon Container */}
              <div className={cn(
                "mb-2.5 flex h-11 w-11 items-center justify-center rounded-2xl transition-all duration-500",
                isActive 
                  ? cn(theme.bg, "text-white shadow-lg", theme.iconShadow) 
                  : cn("bg-slate-50 text-slate-400 group-hover:scale-110")
              )}>
                <t.icon className={cn("h-5 w-5", isActive ? "animate-pulse" : "")} />
              </div>

              <span className={cn(
                "text-[10px] font-extrabold tracking-tight transition-colors",
                isActive ? theme.text : "text-slate-400 group-hover:text-slate-600"
              )}>
                {t.label}
              </span>
              
              {isActive && (
                <div className={cn("absolute -bottom-1 h-1.5 w-6 rounded-full", theme.bg)} />
              )}
            </button>
          )
        })}
      </div>

      {/* ── Modal Entry Form ── */}
      <Dialog 
        isOpen={activeTab !== null} 
        onClose={() => setActiveTab(null)}
        title={activeTab ? `تسجيل ${TABS.find(t => t.key === activeTab)?.label} جديد` : ''}
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
                        "py-3 rounded-xl text-xs font-bold border transition-all duration-300", 
                        tempTime === t.id 
                          ? cn(currentTheme.bg, "border-transparent text-white shadow-lg", currentTheme.shadow) 
                          : "bg-white border-slate-100 text-slate-400 hover:border-slate-300"
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

          {error && (
            <div className="flex items-center gap-2 rounded-2xl border border-rose-100 bg-rose-50/50 p-4 text-rose-700 animate-in fade-in slide-in-from-top-1 text-[11px] font-bold shadow-inner">
              <AlertCircle className="h-4 w-4" />
              {error}
            </div>
          )}

          <button 
            onClick={handleSubmit} 
            disabled={loading} 
            className={cn(
              "w-full rounded-2xl py-4 text-sm font-bold text-white transition-all duration-500 active:scale-[0.98] disabled:opacity-50",
              currentTheme.bg,
              `shadow-xl ${currentTheme.shadow}`
            )}
          >
            {loading ? 'جارٍ الحفظ...' : 'تأكيد الحفظ'}
          </button>
        </div>
      </Dialog>
    </div>
  )
}

function FormField({ label, children, required }: any) {
  return (
    <div className="flex flex-col gap-2">
      <label className="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider px-1">{label} {required && <span className="text-rose-400">*</span>}</label>
      {children}
    </div>
  )
}

function NumericInput({ value, onChange, placeholder, min, step, className }: any) {
  return <input type="number" inputMode="decimal" value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} min={min} step={step} className={className} dir="ltr" />
}

function SelectInput({ value, onChange, options, placeholder, emptyMessage, className }: any) {
  if (options.length === 0) return <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 px-4 py-4 text-[10px] font-bold text-slate-400 italic text-center uppercase tracking-tight">{emptyMessage}</div>
  return (
    <select value={value} onChange={(e) => onChange(e.target.value)} className={className}>
      <option value="">{placeholder}</option>
      {options.map((o: any) => <option key={o.value} value={o.value}>{o.label}</option>)}
    </select>
  )
}

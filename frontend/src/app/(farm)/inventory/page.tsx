'use client'

import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  Package,
  AlertTriangle,
  AlertCircle,
  Truck,
  DollarSign,
  Layers,
  ArrowDownCircle,
  ArrowUpCircle,
  Plus,
  CheckCircle,
  Eye,
  ListChecks,
  Bell,
  BarChart3,
  ChevronLeft,
  Edit3,
  X,
  Droplets,
} from 'lucide-react'
import {
  inventoryApi,
  type StockItem,
  type InventorySummary,
  type InventoryTransaction,
  type Warehouse,
  type ItemType,
  type AddShipmentPayload,
  type CreateItemPayload,
} from '@/lib/api/inventory'
import { flocksApi } from '@/lib/api/flocks'
import { useFarmStore } from '@/stores/farm.store'
import { useLayoutStore } from '@/stores/layout.store'
import { useIsReadOnly } from '@/lib/roles'
import { formatNumber, formatDate, cn, toEnglishDigits, formatCurrency } from '@/lib/utils'

// ── Constants ────────────────────────────────────────────────────────────────

const TYPE_LABEL: Record<string, string> = {
  feed:     'علف',
  medicine: 'دواء',
  charcoal: 'فحم',
  water:    'ماء',
  other:    'أخرى',
}

const TARGET_TYPE_CODES = ['feed', 'medicine', 'charcoal', 'water']

const DIRECTION_CONFIG: Record<string, { label: string; icon: typeof ArrowDownCircle; color: string; badgeCls: string; amountCls: string }> = {
  in:  { label: 'وارد',  icon: ArrowDownCircle, color: 'text-emerald-600', badgeCls: 'bg-emerald-50 text-emerald-700', amountCls: 'text-emerald-700' },
  out: { label: 'صادر', icon: ArrowUpCircle,   color: 'text-red-600',    badgeCls: 'bg-red-50 text-red-600',        amountCls: 'text-red-600'     },
}

const PAYMENT_STATUS_LABEL: Record<string, { label: string; color: string }> = {
  paid:    { label: 'مدفوع',     color: 'bg-emerald-100 text-emerald-700' },
  partial: { label: 'جزئي',      color: 'bg-emerald-100 text-emerald-700' },
  unpaid:  { label: 'غير مدفوع', color: 'bg-red-100 text-red-700'    },
}

const TX_TYPE_LABEL: Record<string, string> = {
  purchase:    'شراء',
  consumption: 'استهلاك',
  adjustment:  'تسوية',
  transfer:    'تحويل',
  return:      'مرتجع',
}

type Tab = 'overview' | 'items' | 'add-item' | 'add-shipment' | 'movements' | 'alerts'

const today = new Date().toISOString().slice(0, 10)

// ── Weight formatter: switches to tons above 1000 kg ───────────────────────────
function formatWeight(qty: number, unit: string): string {
  if (qty >= 1000 && unit?.trim() === 'كيلو') {
    return `${(qty / 1000).toFixed(2).replace('.00', '')} طن`
  }
  return `${formatNumber(qty)} ${unit}`
}

// ── Reusable field wrapper ────────────────────────────────────────────────────

function Field({ label, required, hint, children }: { label: string; required?: boolean; hint?: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="mb-1.5 flex items-center gap-1 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
        {label} {required && <span className="text-red-400">*</span>}
      </label>
      {children}
      {hint && <p className="mt-1 text-[10px] text-slate-400">{hint}</p>}
    </div>
  )
}

const inputCls = 'w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-800 placeholder:text-slate-300 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 transition-all duration-200 shadow-sm'

// ── KPI card ─────────────────────────────────────────────────────────────────

function KpiCard({ label, value, sub, icon: Icon, color }: {
  label: string; value: string; sub?: string
  icon: typeof Package; color: string
}) {
  const bg = color
    .replace('text-', 'bg-')
    .replace(/-700$/, '-50')
    .replace(/-600$/, '-50')
  return (
    <div className="group relative overflow-hidden rounded-2xl border border-slate-200/60 bg-gradient-to-br from-white to-slate-50/50 p-4 pb-5 transition-all duration-300 hover:shadow-lg hover:-translate-y-1 min-h-[105px]" style={{ boxShadow: 'var(--shadow-card)' }}>
      <div className={cn("absolute -right-6 -top-6 h-24 w-24 rounded-full opacity-20 blur-2xl transition-opacity group-hover:opacity-40", bg)} />
      <div className="relative z-10 w-full pr-1">
        <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider truncate">{label}</p>
        <p className={`mt-1 text-2xl font-black tabular-nums leading-none tracking-tight ${color}`}>{value}</p>
        {sub && <p className="mt-1 text-[10px] font-semibold text-slate-400 pl-8">{sub}</p>}
      </div>
      <div className={cn("absolute bottom-3 left-3 rounded-lg p-1.5 transition-transform duration-300 group-hover:scale-110 shadow-sm z-10", bg)}>
        <Icon className={`h-4 w-4 ${color}`} />
      </div>
    </div>
  )
}

// ── Stock status badge ────────────────────────────────────────────────────────

function StockStatusBadge({ item }: { item: StockItem }) {
  if (item.minimum_stock <= 0) return <span className="text-slate-300 text-[10px] font-medium">—</span>
  if (item.total_quantity <= 0)
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700">
        <span className="h-1.5 w-1.5 rounded-full bg-red-500 animate-pulse" />
        نفذ
      </span>
    )
  if (item.total_quantity <= item.minimum_stock)
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-[10px] font-bold text-emerald-600">
        <span className="h-1.5 w-1.5 rounded-full bg-emerald-400" />
        منخفض
      </span>
    )
  return (
    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
      <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
      كافٍ
    </span>
  )
}

// ── Material summary card ─────────────────────────────────────────────────────

function MaterialCard({ title, items, color, icon: CardIcon, onOpen }: { title: string; items: StockItem[]; color: string; icon: typeof Package; onOpen: () => void }) {
  if (items.length === 0) return null
  const total    = items.reduce((s, i) => s + i.total_quantity, 0)
  const unit     = items[0]?.content_unit ?? ''
  const lowCount = items.filter(i => i.minimum_stock > 0 && i.total_quantity <= i.minimum_stock).length
  const iconBg = color.replace('text-', 'bg-').replace(/-700$/, '-50').replace(/-600$/, '-50')

  const hasBags = items.some(i => i.unit_value > 1 && i.input_unit)
  const firstInputUnit = items.find(i => i.unit_value > 1 && i.input_unit)?.input_unit || 'كيس'
  const totalBags = items.reduce((sum, i) => sum + (i.unit_value > 1 ? i.total_quantity / i.unit_value : 0), 0)
  const weightUnit = total >= 1000 && unit?.trim() === 'كيلو' ? 'طن' : unit
  const displayWeight = total >= 1000 && unit?.trim() === 'كيلو' ? (total / 1000).toFixed(2).replace('.00', '') : formatNumber(total)

  return (
    <button
      type="button"
      onClick={onOpen}
      className="group w-full text-right rounded-2xl border border-slate-200/60 bg-white p-5 transition-all duration-300 hover:shadow-md hover:border-primary-200 active:scale-[0.99]"
      style={{ boxShadow: 'var(--shadow-card)' }}
    >
      {/* Card Header */}
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-3">
          <div className={cn("rounded-xl p-2", iconBg)}>
            <CardIcon className={cn("h-4 w-4", color)} />
          </div>
          <div>
            <h3 className={cn("text-sm font-bold", color)}>{title}</h3>
            <p className="text-[10px] text-slate-400 font-medium">{items.length} صنف</p>
          </div>
        </div>
        <span className="rounded-full bg-slate-50 border border-slate-100 px-2.5 py-1 text-[9px] font-bold text-slate-400 uppercase tracking-wide">
          ملخص
        </span>
      </div>

      {/* Total + Details link */}
      <div className="flex items-end justify-between">
        <div>
          {hasBags ? (
            <>
              <p className={cn("text-3xl font-black tabular-nums leading-none tracking-tight", color)}>
                {formatNumber(totalBags)}
              </p>
              <div className="flex items-center gap-2 mt-1">
                <p className="text-[11px] font-semibold text-slate-400">{firstInputUnit}</p>
                <span className={cn("text-[10px] font-bold bg-slate-50 border border-slate-100 rounded px-1.5 py-0.5", color)}>
                  {displayWeight} {weightUnit}
                </span>
              </div>
            </>
          ) : (
            <>
              <p className={cn("text-3xl font-black tabular-nums leading-none tracking-tight", color)}>{displayWeight}</p>
              <p className="text-[11px] font-semibold text-slate-400 mt-0.5">{weightUnit}</p>
            </>
          )}
        </div>

        <div className="flex items-center gap-2">
          {lowCount > 0 && (
            <span className="flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-600 border border-emerald-100">
              <AlertTriangle className="h-3 w-3" />{lowCount} منخفض
            </span>
          )}
          <span className="flex items-center justify-center h-7 w-7 rounded-full bg-slate-50 text-slate-400 group-hover:bg-primary-50 group-hover:text-primary-600 transition-colors">
            <ChevronLeft className="h-4 w-4" />
          </span>
        </div>
      </div>
    </button>
  )
}

// ── Items table ───────────────────────────────────────────────────────────────

function ItemsTable({ items, onEdit, categoryFilter = 'all', onCategoryFilterChange }: {
  items: StockItem[]
  onEdit?: (item: StockItem) => void
  categoryFilter?: string
  onCategoryFilterChange?: (value: string) => void
}) {
  const Section = ({ title, rows, color, icon: SectionIcon }: { title: string; rows: StockItem[]; color: string; icon: typeof Package }) => {
    if (rows.length === 0) return null
    const iconBg = color.replace('text-', 'bg-').replace(/-700$/, '-50')

    const formatAmount = (qty: number, item: StockItem) => {
      if (item.unit_value > 1) return `${formatNumber(qty / item.unit_value)} ${item.input_unit || 'كيس'}`
      if (item.content_unit?.trim() === 'كيلو' && qty >= 1000) return `${(qty / 1000).toFixed(2).replace('.00', '')} طن`
      return `${formatNumber(qty)} ${item.content_unit}`
    }

    return (
      <section>
        <div className="mb-3 flex items-center gap-2">
          <div className={cn("rounded-lg p-1.5", iconBg)}>
            <SectionIcon className={cn("h-3.5 w-3.5", color)} />
          </div>
          <h3 className={cn("text-xs font-bold uppercase tracking-wider", color)}>{title}</h3>
          <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">{rows.length}</span>
        </div>

        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {rows.map(item => {
            const isLow = item.minimum_stock > 0 && item.total_quantity <= item.minimum_stock
            const pct   = item.minimum_stock > 0 ? Math.min((item.total_quantity / item.minimum_stock) * 100, 100) : 100
            return (
              <div key={item.id} className="rounded-2xl border border-slate-200/60 bg-white p-4" style={{ boxShadow: 'var(--shadow-card)' }}>
                {/* Header: name + status + edit */}
                <div className="flex items-start justify-between gap-2 mb-3">
                  <div className="min-w-0 flex-1">
                    <span className="text-sm font-bold text-slate-800 block truncate">{item.name}</span>
                    <div className="mt-1.5 flex items-center gap-2">
                      <StockStatusBadge item={item} />
                      {item.minimum_stock > 0 && (
                        <span className="text-[9px] font-semibold text-slate-400">
                          الحد الأدنى: {formatNumber(item.minimum_stock)} {item.content_unit}
                        </span>
                      )}
                    </div>
                    {item.minimum_stock > 0 && (
                      <div className="mt-2 h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                        <div
                          className={cn("h-full rounded-full transition-all duration-500", isLow ? "bg-red-400" : "bg-emerald-500")}
                          style={{ width: `${pct}%` }}
                        />
                      </div>
                    )}
                  </div>
                  {onEdit && (
                    <button
                      onClick={() => onEdit(item)}
                      className="rounded-lg bg-slate-100 p-1.5 text-slate-500 hover:bg-primary-50 hover:text-primary-600 transition-colors shrink-0"
                      title="تعديل"
                    >
                      <Edit3 className="h-3.5 w-3.5" />
                    </button>
                  )}
                </div>

                {/* Remaining / Total split */}
                <div className="flex w-full rounded-xl border border-slate-200/80 overflow-hidden shadow-sm">
                  <div className={cn("flex-1 p-2.5 text-center flex flex-col justify-center", isLow ? "bg-red-50/80" : "bg-white")}>
                    <span className={cn("text-[9px] font-extrabold mb-1", isLow ? "text-red-500" : "text-slate-400")}>المتبقي</span>
                    <span className={cn("text-[13px] font-black tabular-nums leading-none", isLow ? "text-red-600" : "text-emerald-600")}>
                      {formatAmount(item.total_quantity, item)}
                    </span>
                  </div>
                  <div className="w-[1px] bg-slate-100" />
                  <div className="flex-1 p-2.5 text-center bg-slate-50/80 flex flex-col justify-center">
                    <span className="text-[9px] font-extrabold text-slate-400 mb-1">الإجمالي</span>
                    <span className="text-[13px] font-black tabular-nums leading-none text-slate-700">
                      {formatAmount(item.total_received || 0, item)}
                    </span>
                  </div>
                </div>
              </div>
            )
          })}
        </div>
      </section>
    )
  }
  const feedItems  = items.filter(i => i.type_code === 'feed')
  const medItems   = items.filter(i => i.type_code === 'medicine')
  const otherItems = items.filter(i => i.type_code !== 'feed' && i.type_code !== 'medicine')

  const chipCls = (active: boolean) =>
    `rounded-full px-3 py-1.5 text-xs font-bold border transition-colors ${
      active
        ? 'bg-primary-600 text-white border-primary-600'
        : 'bg-white text-slate-600 border-slate-200 hover:border-primary-400'
    }`

  const categories: { key: string; label: string; rows: StockItem[] }[] = [
    { key: 'feed',     label: 'العلف',  rows: feedItems },
    { key: 'medicine', label: 'الدواء', rows: medItems },
    { key: 'other',    label: 'أخرى',   rows: otherItems },
  ]

  return (
    <div className="space-y-6">
      {onCategoryFilterChange && (
        <div className="flex flex-wrap gap-2">
          <button onClick={() => onCategoryFilterChange('all')} className={chipCls(categoryFilter === 'all')}>الكل</button>
          {categories.filter(c => c.rows.length > 0).map(c => (
            <button key={c.key} onClick={() => onCategoryFilterChange(c.key)} className={chipCls(categoryFilter === c.key)}>
              {c.label}
            </button>
          ))}
        </div>
      )}

      {(categoryFilter === 'all' || categoryFilter === 'feed')     && <Section title="العلف"  rows={feedItems}  color="text-emerald-700" icon={Package} />}
      {(categoryFilter === 'all' || categoryFilter === 'medicine') && <Section title="الدواء" rows={medItems}   color="text-blue-700"  icon={Package} />}
      {(categoryFilter === 'all' || categoryFilter === 'other')    && <Section title="أخرى"  rows={otherItems} color="text-slate-600" icon={Package} />}
    </div>
  )
}

// ── Add Item form ─────────────────────────────────────────────────────────────

function AddItemForm({
  itemTypes,
  onSuccess,
}: {
  itemTypes: ItemType[]
  onSuccess: () => void
}) {
  const [form, setForm] = useState<{
    item_type_id: string
    name: string
    input_unit: string
    unit_value: string
    content_unit: string
    minimum_stock: string
    notes: string
  }>({
    item_type_id: '',
    name: '',
    input_unit: '',
    unit_value: '1',
    content_unit: '',
    minimum_stock: '',
    notes: '',
  })
  const [saving,   setSaving]   = useState(false)
  const [error,    setError]    = useState<string | null>(null)
  const [success,  setSuccess]  = useState(false)

  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
    setForm(prev => ({ ...prev, [k]: toEnglishDigits(e.target.value) }))

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.item_type_id || !form.name || !form.input_unit || !form.content_unit || !form.unit_value) {
      setError('يرجى ملء جميع الحقول المطلوبة')
      return
    }
    setSaving(true)
    setError(null)
    try {
      const payload: CreateItemPayload = {
        item_type_id:  Number(form.item_type_id),
        name:          form.name,
        input_unit:    form.input_unit,
        unit_value:    Number(form.unit_value),
        content_unit:  form.content_unit,
        minimum_stock: form.minimum_stock ? Number(form.minimum_stock) : null,
        notes:         form.notes || null,
      }
      await inventoryApi.createItem(payload)
      setSuccess(true)
      setForm({ item_type_id: '', name: '', input_unit: '', unit_value: '1', content_unit: '', minimum_stock: '', notes: '' })
      setTimeout(() => { setSuccess(false); onSuccess() }, 1500)
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      setError(msg ?? 'حدث خطأ أثناء الحفظ')
    } finally {
      setSaving(false)
    }
  }

  if (success) {
    return (
      <div className="flex flex-col items-center justify-center rounded-2xl border border-emerald-200 bg-gradient-to-b from-emerald-50 to-white py-16 text-center" style={{ boxShadow: 'var(--shadow-card)' }}>
        <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100">
          <CheckCircle className="h-8 w-8 text-emerald-500" />
        </div>
        <p className="text-lg font-bold text-emerald-700">تمت إضافة الصنف بنجاح</p>
        <p className="mt-1 text-xs text-emerald-500">سيتم الانتقال لصفحة الأصناف تلقائياً...</p>
      </div>
    )
  }

  return (
    <div className="rounded-2xl border border-slate-200/60 bg-white overflow-hidden" style={{ boxShadow: 'var(--shadow-card)' }}>
      {/* Form Header */}
      <div className="border-b border-slate-100 bg-slate-50/50 px-6 py-4">
        <h2 className="flex items-center gap-2.5 text-sm font-bold text-slate-800">
          <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-100">
            <Plus className="h-3.5 w-3.5 text-primary-600" />
          </div>
          إضافة صنف جديد
        </h2>
        <p className="mt-1 text-[10px] text-slate-400 mr-[38px]">أضف صنفاً جديداً لمتابعة مخزونه وحركته تلقائياً</p>
      </div>

      <div className="p-6">
        {error && (
          <div className="mb-5 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-bold text-red-700">
            <AlertCircle className="h-4 w-4 shrink-0" />{error}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-5">
          {/* Section: Basic Info */}
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="نوع الصنف" required>
              <select value={form.item_type_id} onChange={(e) => {
                const val = e.target.value;
                const typeCode = itemTypes.find(t => String(t.id) === val)?.code;
                if (typeCode === 'feed') {
                  setForm(prev => ({ ...prev, item_type_id: val, input_unit: 'كيس', content_unit: 'كيلو', unit_value: '50' }));
                } else {
                  setForm(prev => ({ ...prev, item_type_id: val }));
                }
              }} className={inputCls}>
                <option value="">-- اختر النوع --</option>
                {itemTypes
                  .filter(t => t.code && TARGET_TYPE_CODES.includes(t.code))
                  .map(t => (
                    <option key={t.id} value={t.id}>{t.name}</option>
                  ))}
              </select>
            </Field>

            <Field label="اسم الصنف" required>
              <input value={form.name} onChange={set('name')} placeholder="مثال: كسبة الصويا" className={inputCls} />
            </Field>
          </div>

          {/* Section: Units */}
          <div className="rounded-xl border border-slate-100 bg-slate-50/30 p-4">
            <p className="mb-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider">إعداد الوحدات</p>
            <div className="grid gap-4 sm:grid-cols-3">
              <Field label="وحدة الإدخال" required hint="مثال: كيس، كرتون">
                <input value={form.input_unit} onChange={set('input_unit')} placeholder="مثل: كيس" className={inputCls} />
              </Field>

              <Field label="قيمة الوحدة" required hint="كم وحدة محتوى بكل وحدة إدخال">
                <input type="number" min="0.001" step="0.001" value={form.unit_value} onChange={set('unit_value')} className={inputCls} />
              </Field>

              <Field label="وحدة المحتوى" required hint="مثال: كيلو، صهريج">
                <input value={form.content_unit} onChange={set('content_unit')} placeholder="مثل: كيلو" className={inputCls} />
              </Field>
            </div>
          </div>

          {/* Section: Optional */}
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="الحد الأدنى للمخزون" hint="سيتم تنبيهك عند بلوغه">
              <input type="number" min="0" step="0.01" value={form.minimum_stock} onChange={set('minimum_stock')} placeholder="0" className={inputCls} />
            </Field>

            <Field label="ملاحظات">
              <input value={form.notes} onChange={set('notes')} placeholder="اختياري" className={inputCls} />
            </Field>
          </div>

          <div className="flex justify-end border-t border-slate-100 pt-5">
            <button
              type="submit"
              disabled={saving}
              className="group flex items-center gap-2 rounded-xl bg-primary-600 px-6 py-3 text-sm font-bold text-white transition-all duration-200 hover:bg-primary-700 active:scale-[0.97] disabled:opacity-50 shadow-md shadow-primary-200"
            >
              <Plus className="h-4 w-4 transition-transform group-hover:rotate-90 duration-300" />
              {saving ? 'جارٍ الحفظ...' : 'إضافة الصنف'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// ── Edit Item form Modal ─────────────────────────────────────────────────────────

function EditItemModal({
  item,
  onClose,
  onSuccess,
}: {
  item: StockItem
  onClose: () => void
  onSuccess: () => void
}) {
  const [form, setForm] = useState({
    name:          item.name,
    input_unit:    item.input_unit || '',
    unit_value:    item.unit_value.toString(),
    content_unit:  item.content_unit,
    minimum_stock: item.minimum_stock.toString(),
    notes:         item.notes || '',
  })
  const [saving,  setSaving]  = useState(false)
  const [error,   setError]   = useState<string | null>(null)

  const set = (k: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) => {
    setForm(prev => ({ ...prev, [k]: toEnglishDigits(e.target.value) }))
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.name || !form.input_unit || !form.unit_value || !form.content_unit) return setError('الرجاء إكمال جميع الحقول الإلزامية')
    if (isNaN(parseFloat(form.unit_value)) || parseFloat(form.unit_value) <= 0) return setError('قيمة الوحدة يجب أن تكون رقماً أكبر من صفر')

    setSaving(true)
    setError(null)
    try {
      await inventoryApi.updateItem(item.id, {
        name:          form.name,
        input_unit:    form.input_unit,
        unit_value:    parseFloat(form.unit_value),
        content_unit:  form.content_unit,
        minimum_stock: parseFloat(form.minimum_stock || '0'),
        notes:         form.notes,
      })
      onSuccess()
      onClose()
    } catch (err: any) {
      setError(err.response?.data?.message || 'تعذّر تحديث الصنف')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm transition-all">
      <div className="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/10">
        <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <h3 className="text-base font-bold text-slate-800">تعديل الصنف: {item.name}</h3>
          <button onClick={onClose} className="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors">
            <X className="h-5 w-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-5">
          {error && (
            <div className="mb-5 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-bold text-red-700">
              <AlertCircle className="h-4 w-4 shrink-0" />{error}
            </div>
          )}

          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="اسم الصنف" required>
              <input value={form.name} onChange={set('name')} className={inputCls} />
            </Field>
          </div>

          <div className="rounded-xl border border-slate-100 bg-slate-50/30 p-4">
            <p className="mb-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider">إعداد الوحدات</p>
            <div className="grid gap-4 sm:grid-cols-3">
              <Field label="وحدة الإدخال" required>
                <input value={form.input_unit} onChange={set('input_unit')} className={inputCls} />
              </Field>
              <Field label="قيمة الوحدة" required>
                <input type="number" min="0.001" step="0.001" value={form.unit_value} onChange={set('unit_value')} className={inputCls} />
              </Field>
              <Field label="وحدة المحتوى" required>
                <input value={form.content_unit} onChange={set('content_unit')} className={inputCls} />
              </Field>
            </div>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="الحد الأدنى للمخزون">
              <input type="number" min="0" step="0.01" value={form.minimum_stock} onChange={set('minimum_stock')} className={inputCls} />
            </Field>
            <Field label="ملاحظات">
              <input value={form.notes} onChange={set('notes')} className={inputCls} />
            </Field>
          </div>

          <div className="flex justify-end gap-3 border-t border-slate-100 pt-5 mt-6">
            <button
              type="button"
              onClick={onClose}
              disabled={saving}
              className="rounded-xl px-5 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-100 transition-colors"
            >
              إلغاء
            </button>
            <button
              type="submit"
              disabled={saving}
              className="rounded-xl bg-primary-600 px-6 py-2.5 text-sm font-bold text-white transition-all duration-200 hover:bg-primary-700 shadow-md shadow-primary-200 disabled:opacity-50"
            >
              {saving ? 'جارٍ الحفظ...' : 'حفظ التعديلات'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// ── Add Shipment form ─────────────────────────────────────────────────────────

function AddShipmentForm({
  stockItems,
  warehouses,
  activeFlockId,
  onSuccess,
}: {
  stockItems: StockItem[]
  warehouses: Warehouse[]
  activeFlockId?: number
  onSuccess: () => void
}) {
  const [form, setForm] = useState({
    item_id:           '',
    warehouse_id:      '',
    transaction_date:  today,
    original_quantity: '',
    unit_price:        '',
    total_amount:      '',
    paid_amount:       '',
    payment_status:    'unpaid',
    supplier_name:     '',
    invoice_no:        '',
    notes:             '',
    attachment:        null as File | null,
  })
  const [bags, setBags] = useState('')
  const [extraKg, setExtraKg] = useState('')
  const [lastEdited, setLastEdited] = useState<'price' | 'total'>('price')
  const [saving,  setSaving]  = useState(false)
  const [error,   setError]   = useState<string | null>(null)
  const [success, setSuccess] = useState(false)

  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const val = toEnglishDigits(e.target.value)
    setForm(prev => {
      const next = { ...prev, [k]: val }

      // Bidirectional Calculation Logic
      const qty = parseFloat(next.original_quantity)
      if (!isNaN(qty) && qty > 0) {
        if (k === 'unit_price' || (k === 'original_quantity' && lastEdited === 'price')) {
          const up = parseFloat(val || (k === 'unit_price' ? '0' : next.unit_price))
          if (!isNaN(up)) {
            next.total_amount = (qty * up).toFixed(2)
            setLastEdited('price')
          }
        } else if (k === 'total_amount' || (k === 'original_quantity' && lastEdited === 'total')) {
          const ta = parseFloat(val || (k === 'total_amount' ? '0' : next.total_amount))
          if (!isNaN(ta)) {
            next.unit_price = (ta / qty).toFixed(2)
            setLastEdited('total')
          }
        }
      }

      return next
    })
  }

  // Auto-set single warehouse
  useEffect(() => {
    if (warehouses.length === 1 && !form.warehouse_id) {
      setForm(prev => ({ ...prev, warehouse_id: String(warehouses[0].id) }))
    }
  }, [warehouses])

  const selectedItem = stockItems.find(i => String(i.id) === form.item_id)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.item_id || !form.warehouse_id || !form.transaction_date || !form.original_quantity) {
      setError('يرجى ملء جميع الحقول المطلوبة')
      return
    }
    const qty = parseFloat(form.original_quantity)
    if (qty <= 0) {
      setError('يجب أن تكون الكمية أكبر من صفر')
      return
    }
    setSaving(true)
    setError(null)
    try {
      const totalAmt  = form.total_amount ? Number(form.total_amount) : null
      const paidAmt   = form.payment_status === 'unpaid' ? 0 : (form.paid_amount ? Number(form.paid_amount) : 0)

      const payload: AddShipmentPayload = {
        item_id:           Number(form.item_id),
        warehouse_id:      Number(form.warehouse_id),
        transaction_date:  form.transaction_date,
        original_quantity: Number(form.original_quantity),
        unit_price:        form.unit_price ? Number(form.unit_price) : null,
        total_amount:      totalAmt,
        paid_amount:       paidAmt,
        payment_status:    form.payment_status as 'paid' | 'unpaid',
        supplier_name:     form.supplier_name || null,
        invoice_no:        form.invoice_no    || null,
        notes:             form.notes         || null,
        attachment:        form.attachment,
        flock_id:          activeFlockId,
      }
      await inventoryApi.addShipment(payload)
      setSuccess(true)
      setBags('')
      setExtraKg('')
      setForm({
        item_id: '',
        warehouse_id: warehouses.length === 1 ? String(warehouses[0].id) : '',
        transaction_date: today,
        original_quantity: '',
        unit_price: '',
        total_amount: '',
        paid_amount: '',
        payment_status: 'unpaid',
        supplier_name: '',
        invoice_no: '',
        notes: '',
        attachment: null
      })
      setTimeout(() => { setSuccess(false); onSuccess() }, 1500)
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      setError(msg ?? 'حدث خطأ أثناء الحفظ')
    } finally {
      setSaving(false)
    }
  }

  if (success) {
    return (
      <div className="flex flex-col items-center justify-center rounded-2xl border border-emerald-200 bg-gradient-to-b from-emerald-50 to-white py-16 text-center" style={{ boxShadow: 'var(--shadow-card)' }}>
        <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100">
          <CheckCircle className="h-8 w-8 text-emerald-500" />
        </div>
        <p className="text-lg font-bold text-emerald-700">تمت إضافة الحمولة بنجاح</p>
        <p className="mt-1 text-xs text-emerald-500">سيتم الانتقال لصفحة الحركات تلقائياً...</p>
      </div>
    )
  }

  return (
    <div className="rounded-2xl border border-slate-200/60 bg-white overflow-hidden" style={{ boxShadow: 'var(--shadow-card)' }}>
      {/* Form Header */}
      <div className="border-b border-slate-100 bg-slate-50/50 px-6 py-4">
        <h2 className="flex items-center gap-2.5 text-sm font-bold text-slate-800">
          <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-100">
            <Truck className="h-3.5 w-3.5 text-primary-600" />
          </div>
          إضافة حمولة / وارد
        </h2>
        <p className="mt-1 text-[10px] text-slate-400 mr-[38px]">سجّل حمولة صادرة أو واردة لتحديث أرصدة المخزون</p>
      </div>

      <div className="p-6">
        {error && (
          <div className="mb-5 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-bold text-red-700">
            <AlertCircle className="h-4 w-4 shrink-0" />{error}
          </div>
        )}

        {warehouses.length === 0 && (
          <div className="mb-5 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-700">
            <AlertTriangle className="h-4 w-4 shrink-0" />
            لا يوجد مستودع نشط. أضف مستودعاً من إعدادات المزرعة أولاً.
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-5">
          {/* Section: Source */}
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="الصنف" required>
              <select
                value={form.item_id}
                onChange={(e) => {
                  const val = e.target.value
                  setBags('')
                  setExtraKg('')
                  setForm(prev => ({ ...prev, item_id: val, original_quantity: '', total_amount: '', unit_price: '' }))
                }}
                className={inputCls}
              >
                <option value="">-- اختر الصنف --</option>
                {stockItems.map(i => (
                  <option key={i.id} value={i.id}>{i.name}</option>
                ))}
              </select>
            </Field>

            <Field label="المستودع" required>
              {warehouses.length === 1 ? (
                <div className={cn(inputCls, "bg-slate-50 border-slate-100 flex items-center text-slate-500 cursor-not-allowed")}>
                  {warehouses[0].name}
                </div>
              ) : (
                <select value={form.warehouse_id} onChange={set('warehouse_id')} className={inputCls}>
                  <option value="">-- اختر المستودع --</option>
                  {warehouses.map(w => (
                    <option key={w.id} value={w.id}>{w.name}{w.location ? ` — ${w.location}` : ''}</option>
                  ))}
                </select>
              )}
            </Field>
          </div>

          {/* Section: Quantity */}
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="التاريخ" required>
              <input type="date" value={form.transaction_date} onChange={set('transaction_date')} className={inputCls} />
            </Field>

            {selectedItem && selectedItem.unit_value > 1 ? (
              <div className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                  <Field label={`عدد الأكياس (${selectedItem.input_unit})`} required>
                    <input
                      type="number" min="0" step="1"
                      value={bags}
                      onChange={(e) => {
                        const v = toEnglishDigits(e.target.value)
                        setBags(v)
                        const totalQty = (parseFloat(v || '0') + parseFloat(extraKg || '0') / selectedItem.unit_value).toFixed(3)
                        setForm(prev => {
                          const next = { ...prev, original_quantity: totalQty }
                          if (next.unit_price && lastEdited === 'price') {
                            next.total_amount = (parseFloat(totalQty) * parseFloat(next.unit_price)).toFixed(2)
                          } else if (next.total_amount && lastEdited === 'total') {
                            next.unit_price = (parseFloat(next.total_amount) / parseFloat(totalQty)).toFixed(2)
                          }
                          return next
                        })
                      }}
                      placeholder="0"
                      className={inputCls}
                    />
                  </Field>
                  <Field label="وزن إضافي (كيلو)">
                    <input
                      type="number" min="0" step="0.01"
                      value={extraKg}
                      onChange={(e) => {
                        const v = toEnglishDigits(e.target.value)
                        setExtraKg(v)
                        const totalQty = (parseFloat(bags || '0') + parseFloat(v || '0') / selectedItem.unit_value).toFixed(3)
                        setForm(prev => {
                          const next = { ...prev, original_quantity: totalQty }
                          if (next.unit_price && lastEdited === 'price') {
                            next.total_amount = (parseFloat(totalQty) * parseFloat(next.unit_price)).toFixed(2)
                          } else if (next.total_amount && lastEdited === 'total') {
                            next.unit_price = (parseFloat(next.total_amount) / parseFloat(totalQty)).toFixed(2)
                          }
                          return next
                        })
                      }}
                      placeholder="0.00"
                      className={inputCls}
                    />
                  </Field>
                </div>
                {(parseFloat(bags || '0') > 0 || parseFloat(extraKg || '0') > 0) && (
                  <p className="mt-1.5 flex items-center gap-1 text-[10px] font-bold text-primary-600">
                    <ChevronLeft className="h-3 w-3" />
                    الإجمالي: {formatNumber(parseFloat(bags || '0') * selectedItem.unit_value + parseFloat(extraKg || '0'))} {selectedItem.content_unit}
                  </p>
                )}
              </div>
            ) : (
              <Field label={`الكمية${selectedItem ? ` (${selectedItem.input_unit})` : ''}`} required>
                <input
                  type="number" min="0.001" step="0.001"
                  value={form.original_quantity} onChange={set('original_quantity')}
                  placeholder="0"
                  className={inputCls}
                />
                {selectedItem && form.original_quantity && (
                  <p className="mt-1.5 flex items-center gap-1 text-[10px] font-bold text-primary-600">
                    <ChevronLeft className="h-3 w-3" />
                    = {formatNumber(Number(form.original_quantity) * selectedItem.unit_value)} {selectedItem.content_unit}
                  </p>
                )}
              </Field>
            )}
          </div>

          {/* Section: Financials */}
          {(() => {
            const total  = parseFloat(form.total_amount)  || 0
            const paid   = parseFloat(form.paid_amount)   || 0
            const remaining = form.payment_status === 'unpaid' ? total : Math.max(0, total - paid)
            const missingPrice = !form.total_amount || total <= 0
            const showDebtHint = missingPrice || remaining > 0
            return (
              <div className="rounded-xl border border-slate-100 bg-slate-50/30 p-4">
                <p className="mb-3 text-[10px] font-bold text-slate-400 uppercase tracking-wider">البيانات المالية</p>
                <div className="grid gap-4 sm:grid-cols-3">
                  <Field label="سعر الوحدة (USD)">
                    <input type="number" min="0" step="0.01" value={form.unit_price} onChange={set('unit_price')} placeholder="0.00" className={inputCls} />
                  </Field>

                  <Field label="الإجمالي (USD)">
                    <input type="number" min="0" step="0.01" value={form.total_amount} onChange={set('total_amount')} placeholder="0.00" className={inputCls} />
                  </Field>

                  <Field label="حالة الدفع">
                    <select value={form.payment_status} onChange={(e) => {
                      set('payment_status')(e)
                      if (e.target.value === 'unpaid') setForm(prev => ({ ...prev, paid_amount: '', payment_status: 'unpaid' }))
                    }} className={inputCls}>
                      <option value="paid">مدفوع</option>
                      <option value="unpaid">غير مدفوع</option>
                    </select>
                  </Field>

                  {form.payment_status === 'paid' && (
                    <Field label="المبلغ المدفوع (USD)">
                      <input type="number" min="0" step="0.01" value={form.paid_amount} onChange={set('paid_amount')} placeholder="0.00" className={inputCls} />
                    </Field>
                  )}
                </div>

                {showDebtHint && (
                  <div className="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-[11px] text-red-600 font-medium">
                    {missingPrice
                      ? 'السعر غير محدد — سيُسجَّل كدين وسيُرحَّل إلى الذمم والمراجعة تلقائياً.'
                      : <>المبلغ غير المدفوع: <span className="font-black">{formatCurrency(remaining)}</span> — سيُرحَّل إلى الذمم والمراجعة تلقائياً.</>}
                  </div>
                )}
              </div>
            )
          })()}

          {/* Section: Supplier */}
          <div className="grid gap-4 sm:grid-cols-3">
            <Field label="اسم المورد">
              <input value={form.supplier_name} onChange={set('supplier_name')} placeholder="اختياري" className={inputCls} />
            </Field>

            <Field label="رقم الفاتورة">
              <input value={form.invoice_no} onChange={set('invoice_no')} placeholder="اختياري" className={inputCls} />
            </Field>

            <Field label="ملاحظات">
              <input value={form.notes} onChange={set('notes')} placeholder="اختياري" className={inputCls} />
            </Field>

            <Field label="مرفق الفاتورة">
              <div className="relative">
                <input
                  type="file"
                  accept="image/*,.pdf"
                  onChange={(e) => setForm(prev => ({ ...prev, attachment: e.target.files?.[0] || null }))}
                  className="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0"
                />
                <div className={cn(
                  inputCls,
                  "flex items-center gap-2 border-dashed truncate text-xs",
                  form.attachment ? "border-primary-300 bg-primary-50 text-primary-700" : "text-slate-400"
                )}>
                  <CheckCircle className={cn("h-3.5 w-3.5 shrink-0", form.attachment ? "text-primary-500" : "hidden")} />
                  {form.attachment ? form.attachment.name : 'اختر صورة أو ملف الفاتورة...'}
                </div>
              </div>
            </Field>
          </div>

          <div className="flex justify-end border-t border-slate-100 pt-5">
            <button
              type="submit"
              disabled={saving || warehouses.length === 0}
              className="group flex items-center gap-2 rounded-xl bg-primary-600 px-6 py-3 text-sm font-bold text-white transition-all duration-200 hover:bg-primary-700 active:scale-[0.97] disabled:opacity-50 shadow-md shadow-primary-200"
            >
              <Truck className="h-4 w-4" />
              {saving ? 'جارٍ الحفظ...' : 'إضافة الحمولة'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// ── Movements table ───────────────────────────────────────────────────────────

function MovementsTable({ transactions }: { transactions: InventoryTransaction[] }) {
  const [directionFilter, setDirectionFilter] = useState<'all' | 'in' | 'out'>('all')
  const [categoryFilter, setCategoryFilter]   = useState<string>('all')

  const categories = Array.from(
    new Map(
      transactions
        .filter(tx => tx.item_type_code)
        .map(tx => [tx.item_type_code as string, tx.item_type_name ?? TYPE_LABEL[tx.item_type_code as string] ?? tx.item_type_code as string])
    ).entries()
  )

  const filtered = transactions.filter(tx =>
    (directionFilter === 'all' || tx.direction === directionFilter) &&
    (categoryFilter === 'all' || tx.item_type_code === categoryFilter)
  )

  const chipCls = (active: boolean) =>
    `rounded-full px-3 py-1 text-xs font-medium border transition-colors ${
      active
        ? 'bg-primary-600 text-white border-primary-600'
        : 'bg-white text-slate-600 border-slate-200 hover:border-primary-400'
    }`

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap gap-2">
        {([['all', 'الكل'], ['in', 'وارد'], ['out', 'صادر']] as const).map(([val, label]) => (
          <button key={val} onClick={() => setDirectionFilter(val)} className={chipCls(directionFilter === val)}>
            {label}
          </button>
        ))}
        <span className="mx-1 w-px self-stretch bg-slate-200" />
        <button onClick={() => setCategoryFilter('all')} className={chipCls(categoryFilter === 'all')}>الكل</button>
        {categories.map(([code, label]) => (
          <button key={code} onClick={() => setCategoryFilter(code)} className={chipCls(categoryFilter === code)}>
            {label}
          </button>
        ))}
      </div>

      {filtered.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white py-20 text-center" style={{ boxShadow: 'var(--shadow-card)' }}>
          <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100">
            <Layers className="h-7 w-7 text-slate-300" />
          </div>
          <p className="font-bold text-slate-600">{transactions.length === 0 ? 'لا توجد حركات مسجّلة' : 'لا توجد حركات مطابقة للفلتر'}</p>
          {transactions.length === 0 && <p className="mt-1 text-xs text-slate-400">أضف حمولة جديدة لبدء تتبع الحركات</p>}
        </div>
      ) : (
      <div className="overflow-x-auto rounded-2xl border border-slate-200/60 bg-white" style={{ boxShadow: 'var(--shadow-card)' }}>
      <table className="w-full min-w-[1100px] text-xs">
        <thead>
          <tr className="sticky top-0 z-10 border-b border-slate-100 bg-slate-50/90 backdrop-blur text-right text-[10px] font-bold uppercase tracking-wider text-slate-400">
            {['التاريخ','الصنف','الفئة','نوع الحركة','الاتجاه','الكمية الأصلية','الكمية المحسوبة','السعر','الإجمالي','حالة الدفع','المورد','رقم الفاتورة','المرجع','الفوج','المستخدم','الملاحظات']
              .map(h => <th key={h} className="px-3 py-3.5 whitespace-nowrap">{h}</th>)}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-50">
          {filtered.map(tx => {
            const dir     = DIRECTION_CONFIG[tx.direction]
            const DirIcon = dir?.icon ?? ArrowDownCircle
            const payment = tx.payment_status ? PAYMENT_STATUS_LABEL[tx.payment_status] : null
            return (
              <tr key={tx.id} className="transition-colors duration-150 hover:bg-slate-50/60">
                <td className="px-3 py-3 text-slate-500 whitespace-nowrap font-mono text-[10px]">{formatDate(tx.transaction_date)}</td>
                <td className="px-3 py-3 font-semibold text-slate-800 whitespace-nowrap">{tx.item_name ?? '—'}</td>
                <td className="px-3 py-3 text-slate-500 whitespace-nowrap">{tx.item_type_name ?? (tx.item_type_code ? (TYPE_LABEL[tx.item_type_code] ?? tx.item_type_code) : '—')}</td>
                <td className="px-3 py-3 text-slate-500 whitespace-nowrap">{TX_TYPE_LABEL[tx.transaction_type] ?? tx.transaction_type}</td>
                <td className="px-3 py-3 whitespace-nowrap">
                  {dir ? (
                    <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ${dir.badgeCls}`}>
                      <DirIcon className="h-3 w-3" />{dir.label}
                    </span>
                  ) : tx.direction}
                </td>
                <td className="px-3 py-3 tabular-nums text-slate-700 whitespace-nowrap">{formatNumber(tx.original_quantity)} {tx.input_unit}</td>
                <td className="px-3 py-3 tabular-nums text-slate-700 whitespace-nowrap">{formatNumber(tx.computed_quantity)} {tx.content_unit}</td>
                <td className="px-3 py-3 tabular-nums text-slate-700 whitespace-nowrap">{tx.unit_price != null ? formatCurrency(tx.unit_price) : '—'}</td>
                <td className={`px-3 py-3 tabular-nums font-semibold whitespace-nowrap ${dir?.amountCls ?? 'text-slate-800'}`}>{tx.total_amount != null ? formatCurrency(tx.total_amount) : '—'}</td>
                <td className="px-3 py-3 whitespace-nowrap">
                  {payment ? <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${payment.color}`}>{payment.label}</span> : '—'}
                </td>
                <td className="px-3 py-3 text-slate-500 whitespace-nowrap">{tx.supplier_name ?? '—'}</td>
                <td className="px-3 py-3 text-slate-400 whitespace-nowrap font-mono text-[10px]">{tx.invoice_no ?? '—'}</td>
                <td className="px-3 py-3 text-slate-400 whitespace-nowrap font-mono text-[10px]">{tx.reference_no ?? '—'}</td>
                <td className="px-3 py-3 text-slate-500 whitespace-nowrap">{tx.flock_name ?? '—'}</td>
                <td className="px-3 py-3 text-slate-400 whitespace-nowrap">{tx.created_by_name ?? '—'}</td>
                <td className="px-3 py-3 text-slate-400 max-w-[120px] truncate">{tx.notes ?? '—'}</td>
              </tr>
            )
          })}
        </tbody>
      </table>
      </div>
      )}
    </div>
  )
}

// ── Alerts tab ────────────────────────────────────────────────────────────────

function AlertsTab({ items }: { items: StockItem[] }) {
  const lowItems = items.filter(i => i.minimum_stock > 0 && i.total_quantity <= i.minimum_stock)
  if (lowItems.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white py-20 text-center" style={{ boxShadow: 'var(--shadow-card)' }}>
        <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50">
          <CheckCircle className="h-7 w-7 text-emerald-400" />
        </div>
        <p className="font-bold text-slate-600">لا توجد تنبيهات</p>
        <p className="mt-1 text-xs text-slate-400">جميع المواد بمستوى آمن ومقبول</p>
      </div>
    )
  }
  const outItems = items.filter(i => i.total_quantity <= 0)
  return (
    <div className="space-y-5">
      {outItems.length > 0 && (
        <div>
          <div className="mb-3 flex items-center gap-2">
            <div className="rounded-lg bg-red-50 p-1.5">
              <AlertCircle className="h-3.5 w-3.5 text-red-600" />
            </div>
            <h3 className="text-xs font-bold text-red-700 uppercase tracking-wider">نفذت المواد التالية</h3>
          </div>
          <div className="space-y-2">
            {outItems.map(item => (
              <div key={item.id} className="flex items-center justify-between rounded-xl border border-red-200 bg-red-50/50 px-5 py-3.5 transition-colors hover:bg-red-50">
                <span className="font-semibold text-red-800 text-sm">{item.name}</span>
                <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-[10px] font-bold text-red-700">
                  <span className="h-1.5 w-1.5 rounded-full bg-red-500 animate-pulse" />
                  نفذ
                </span>
              </div>
            ))}
          </div>
        </div>
      )}

      <div>
        <div className="mb-3 flex items-center gap-2">
          <div className="rounded-lg bg-emerald-50 p-1.5">
            <AlertTriangle className="h-3.5 w-3.5 text-emerald-600" />
          </div>
          <h3 className="text-xs font-bold text-emerald-700 uppercase tracking-wider">مواد تقترب من النفاد</h3>
        </div>
        <div className="overflow-hidden rounded-2xl border border-emerald-200/60 bg-white" style={{ boxShadow: 'var(--shadow-card)' }}>
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-emerald-100 bg-emerald-50/50 text-right text-[10px] font-bold uppercase tracking-wider text-emerald-600">
                <th className="px-4 py-3">الصنف</th>
                <th className="px-4 py-3">النوع</th>
                <th className="px-4 py-3">المتاح</th>
                <th className="px-4 py-3">الحد الأدنى</th>
                <th className="px-4 py-3">النسبة</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-emerald-50">
              {lowItems.map(item => {
                const pct = item.minimum_stock > 0 ? Math.round((item.total_quantity / item.minimum_stock) * 100) : 0
                return (
                   <tr key={item.id} className="transition-colors hover:bg-emerald-50/30">
                    <td className="px-4 py-3.5 font-semibold text-slate-800">{item.name}</td>
                    <td className="px-4 py-3.5 text-slate-500 text-xs">{TYPE_LABEL[item.type_code] ?? item.type_code}</td>
                    <td className="px-4 py-3.5 tabular-nums font-bold text-emerald-700">
                      {formatNumber(item.total_quantity)} {item.content_unit}
                    </td>
                    <td className="px-4 py-3.5 tabular-nums text-slate-400 text-xs">
                      {formatNumber(item.minimum_stock)} {item.content_unit}
                    </td>
                    <td className="px-4 py-3.5">
                      <div className="flex items-center gap-2">
                        <div className="h-1.5 w-16 rounded-full bg-emerald-100 overflow-hidden">
                          <div className="h-full rounded-full bg-emerald-400" style={{ width: `${pct}%` }} />
                        </div>
                        <span className="text-[10px] font-bold text-emerald-600 tabular-nums">{pct}%</span>
                      </div>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}

// ── Main page ─────────────────────────────────────────────────────────────────

export default function InventoryPage() {
  const { currentFarm } = useFarmStore()
  const { setPageTitle, setPageSubtitle } = useLayoutStore()
  const isReadOnly = useIsReadOnly()
  const [activeTab, setActiveTab] = useState<Tab>('overview')
  const [itemsCategoryFilter, setItemsCategoryFilter] = useState<string>('all')

  const openCategory = (category: string) => {
    setItemsCategoryFilter(category)
    setActiveTab('items')
  }
  const [editingItem, setEditingItem] = useState<StockItem | null>(null)

  useEffect(() => {
    setPageTitle('المخزون')
    setPageSubtitle(currentFarm?.name || null)
  }, [currentFarm, setPageTitle, setPageSubtitle])

  const { data: flocksData } = useQuery({
    queryKey: ['flocks', currentFarm?.id],
    queryFn: () => flocksApi.list().then((r) => r.data),
    enabled: !!currentFarm,
    staleTime: 60_000,
  })
  const activeFlockId = flocksData?.find((f) => f.status === 'active')?.id

  const {
    data: overviewData,
    isLoading: loading,
    isError,
    refetch: loadData,
  } = useQuery({
    queryKey: ['inventory-overview', currentFarm?.id],
    queryFn: () => inventoryApi.overview().then((res) => res.data),
    enabled: !!currentFarm,
    staleTime: 30_000,
  })

  const { data: txData } = useQuery({
    queryKey: ['inventory-transactions', currentFarm?.id],
    queryFn: () => inventoryApi.transactions(),
    enabled: !!currentFarm,
    staleTime: 30_000,
  })

  const items        = overviewData?.stock        ?? []
  const summary      = overviewData?.summary      ?? null
  const transactions = txData?.data               ?? []
  const warehouses   = overviewData?.warehouses   ?? []
  const itemTypes    = overviewData?.item_types   ?? []
  const error        = isError ? 'تعذّر تحميل بيانات المخزون' : null

  const lowItems   = items.filter(i => i.minimum_stock > 0 && i.total_quantity <= i.minimum_stock)
  const feedItems  = items.filter(i => i.type_code === 'feed')
  const medItems   = items.filter(i => i.type_code === 'medicine')
  const otherItems = items.filter(i => i.type_code !== 'feed' && i.type_code !== 'medicine')

  const tabs: { id: Tab; label: string; badge?: number; icon: typeof Eye; isAction?: boolean; isPrimary?: boolean }[] = [
    { id: 'overview',     label: 'نظرة عامة',  icon: Eye },
    ...(!isReadOnly ? [
      { id: 'add-shipment' as Tab, label: 'حمولة جديدة', icon: Truck, isAction: true, isPrimary: true },
    ] : []),
    { id: 'items',        label: 'الأصناف',     icon: ListChecks,  badge: items.length },
    ...(!isReadOnly ? [
      { id: 'add-item'     as Tab, label: 'صنف جديد',    icon: Plus,  isAction: true },
    ] : []),
    { id: 'movements',    label: 'الحركات',     icon: BarChart3,   badge: transactions.length },
    { id: 'alerts',       label: 'التنبيهات',   icon: Bell,        badge: lowItems.length || undefined },
  ]

  return (
    <div className="space-y-5">

      {error && (
        <div className="flex items-center gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 text-red-700">
          <AlertCircle className="h-5 w-5 shrink-0" />
          <p className="text-sm font-semibold">{error}</p>
        </div>
      )}

      {loading && (
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            {[1,2,3,4,5].map(i => <div key={i} className="h-28 animate-pulse rounded-2xl bg-slate-200/60" />)}
          </div>
          <div className="h-12 animate-pulse rounded-2xl bg-slate-200/60" />
          <div className="h-64 animate-pulse rounded-2xl bg-slate-200/60" />
        </div>
      )}

      {!loading && !error && (
        <>
          {/* KPI Cards */}
          {summary && (
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
              <KpiCard
                label="رصيد العلف"
                value={formatWeight(summary.feed_quantity, summary.feed_unit)}
                sub={`من إجمالي ${formatWeight(summary.feed_total_received, summary.feed_unit)}`}
                icon={Package}
                color="text-emerald-700"
              />
              <KpiCard
                label="مجموع المصاريف اليومية"
                value={formatCurrency(summary.today_expenses_total)}
                sub={`الإجمالي الكلي: ${formatCurrency(summary.all_expenses_total)}`}
                icon={DollarSign}
                color="text-amber-700"
              />
              <KpiCard label="صهاريج الماء للفوج الحالي" value={formatNumber(summary.water_tanks_count || 0)} sub={`التكلفة: ${formatCurrency(summary.water_tanks_cost || 0)}`} icon={Droplets} color="text-cyan-600" />
            </div>
          )}

          {/* Tab Navigation */}
          <div className="rounded-2xl border border-slate-200/60 bg-white p-1.5" style={{ boxShadow: 'var(--shadow-card)' }}>
            <nav className="flex overflow-x-auto no-scrollbar gap-2 pb-0.5">
              {tabs.map(tab => {
                const isActive = activeTab === tab.id
                return (
                  <button
                    key={tab.id}
                    onClick={() => setActiveTab(tab.id)}
                    className={cn(
                      "flex items-center gap-1.5 rounded-xl px-4 py-2.5 text-xs font-bold transition-all duration-200 whitespace-nowrap flex-shrink-0",
                      isActive
                        ? tab.isPrimary
                          ? "bg-primary-600 text-white shadow-md shadow-primary-200 ring-2 ring-primary-100 ring-offset-1"
                          : "bg-slate-900 text-white shadow-md"
                        : tab.isPrimary
                          ? "bg-primary-50 text-primary-700 hover:bg-primary-100 border border-primary-100/50"
                          : tab.isAction
                            ? "text-primary-600 hover:bg-primary-50"
                            : "text-slate-500 hover:bg-slate-50 hover:text-slate-700"
                    )}
                  >
                    <tab.icon className={cn("h-4 w-4", isActive && "shrink-0")} />
                    <span>{tab.label}</span>
                    {tab.badge != null && tab.badge > 0 && (
                      <span className={cn(
                        "rounded-full px-1.5 py-0.5 text-[9px] font-bold leading-none mr-1",
                        isActive
                          ? "bg-white/20 text-white"
                          : tab.id === 'alerts'
                            ? "bg-red-100 text-red-700"
                            : "bg-slate-100 text-slate-500"
                      )}>
                        {tab.badge}
                      </span>
                    )}
                  </button>
                )
              })}
            </nav>
          </div>

          {/* Tab Content */}
          <div>
            {activeTab === 'overview' && (
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <MaterialCard title="العلف"  items={feedItems}  color="text-emerald-700" icon={Package} onOpen={() => openCategory('feed')} />
                <MaterialCard title="الدواء" items={medItems}   color="text-blue-700"  icon={Package} onOpen={() => openCategory('medicine')} />
                <MaterialCard title="أخرى"  items={otherItems} color="text-slate-600" icon={Package} onOpen={() => openCategory('other')} />
                {feedItems.length === 0 && medItems.length === 0 && otherItems.length === 0 && (
                  <div className="col-span-full flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white py-20 text-center" style={{ boxShadow: 'var(--shadow-card)' }}>
                    <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100">
                      <Package className="h-8 w-8 text-slate-300" />
                    </div>
                    <h3 className="text-lg font-bold text-slate-700">لا توجد أصناف في المخزون</h3>
                    <p className="mt-1 text-xs text-slate-400">{isReadOnly ? 'لا توجد أصناف مضافة بعد' : 'أضف أصناف من تبويب "صنف جديد"'}</p>
                    {!isReadOnly && (
                      <button
                        onClick={() => setActiveTab('add-item')}
                        className="mt-4 flex items-center gap-1.5 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-700 transition-all duration-200 shadow-md shadow-primary-200"
                      >
                        <Plus className="h-4 w-4" /> إضافة صنف جديد
                      </button>
                    )}
                  </div>
                )}
              </div>
            )}

            {activeTab === 'items' && (
              items.length === 0 ? (
                <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white py-20 text-center" style={{ boxShadow: 'var(--shadow-card)' }}>
                  <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100">
                    <Package className="h-7 w-7 text-slate-300" />
                  </div>
                  <h3 className="text-lg font-bold text-slate-700">لا توجد أصناف</h3>
                  {!isReadOnly && (
                    <button onClick={() => setActiveTab('add-item')} className="mt-4 flex items-center gap-1.5 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-700 transition-all duration-200 shadow-md shadow-primary-200">
                      <Plus className="h-4 w-4" /> إضافة صنف
                    </button>
                  )}
                </div>
              ) : (
                <ItemsTable
                  items={items}
                  onEdit={!isReadOnly ? setEditingItem : undefined}
                  categoryFilter={itemsCategoryFilter}
                  onCategoryFilterChange={setItemsCategoryFilter}
                />
              )
            )}

            {activeTab === 'add-item' && (
              <AddItemForm
                itemTypes={itemTypes}
                onSuccess={() => { loadData(); setActiveTab('items') }}
              />
            )}

            {activeTab === 'add-shipment' && (
              <AddShipmentForm
                stockItems={items}
                warehouses={warehouses}
                activeFlockId={activeFlockId}
                onSuccess={() => { loadData(); setActiveTab('movements') }}
              />
            )}

            {activeTab === 'movements' && <MovementsTable transactions={transactions} />}

            {activeTab === 'alerts' && <AlertsTab items={items} />}
          </div>
          
          {editingItem && (
            <EditItemModal
              item={editingItem}
              onClose={() => setEditingItem(null)}
              onSuccess={() => { loadData(); setEditingItem(null); }}
            />
          )}
        </>
      )}
    </div>
  )
}


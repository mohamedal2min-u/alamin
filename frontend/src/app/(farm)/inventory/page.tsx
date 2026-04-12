'use client'

import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  Package,
  AlertTriangle,
  AlertCircle,
  TrendingDown,
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
import { useFarmStore } from '@/stores/farm.store'
import { useLayoutStore } from '@/stores/layout.store'
import { formatNumber, formatDate, cn } from '@/lib/utils'

// ── Constants ────────────────────────────────────────────────────────────────

const TYPE_LABEL: Record<string, string> = {
  feed:     'علف',
  medicine: 'دواء',
  charcoal: 'فحم',
  water:    'ماء',
  other:    'أخرى',
}

const TARGET_TYPE_CODES = ['feed', 'medicine', 'charcoal', 'water']

const DIRECTION_CONFIG: Record<string, { label: string; icon: typeof ArrowDownCircle; color: string }> = {
  in:  { label: 'وارد',  icon: ArrowDownCircle, color: 'text-green-600' },
  out: { label: 'صادر', icon: ArrowUpCircle,   color: 'text-red-500'   },
}

const PAYMENT_STATUS_LABEL: Record<string, { label: string; color: string }> = {
  paid:    { label: 'مدفوع',     color: 'bg-green-100 text-green-700' },
  partial: { label: 'جزئي',      color: 'bg-amber-100 text-amber-700' },
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
    <div className="group relative overflow-hidden rounded-2xl border border-slate-200/60 bg-white p-5 transition-all duration-300 hover:shadow-md hover:-translate-y-0.5" style={{ boxShadow: 'var(--shadow-card)' }}>
      <div className="flex items-start justify-between">
        <div className="min-w-0">
          <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider truncate">{label}</p>
          <p className={`mt-1.5 text-2xl font-black tabular-nums leading-none tracking-tight ${color}`}>{value}</p>
          {sub && <p className="mt-1 text-[10px] font-semibold text-slate-400">{sub}</p>}
        </div>
        <div className={cn("rounded-xl p-2.5 transition-transform duration-300 group-hover:scale-110", bg)}>
          <Icon className={`h-5 w-5 ${color}`} />
        </div>
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
      <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">
        <span className="h-1.5 w-1.5 rounded-full bg-amber-500" />
        منخفض
      </span>
    )
  return (
    <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-bold text-green-700">
      <span className="h-1.5 w-1.5 rounded-full bg-green-500" />
      كافٍ
    </span>
  )
}

// ── Material summary card ─────────────────────────────────────────────────────

function MaterialCard({ title, items, color, icon: CardIcon }: { title: string; items: StockItem[]; color: string; icon: typeof Package }) {
  if (items.length === 0) return null
  const total    = items.reduce((s, i) => s + i.total_quantity, 0)
  const unit     = items[0]?.content_unit ?? ''
  const lowCount = items.filter(i => i.minimum_stock > 0 && i.total_quantity <= i.minimum_stock).length
  const iconBg = color.replace('text-', 'bg-').replace(/-700$/, '-50').replace(/-600$/, '-50')
  return (
    <div className="group rounded-2xl border border-slate-200/60 bg-white transition-all duration-300 hover:shadow-md" style={{ boxShadow: 'var(--shadow-card)' }}>
      {/* Card Header */}
      <div className="flex items-center justify-between p-5 pb-3">
        <div className="flex items-center gap-3">
          <div className={cn("rounded-xl p-2", iconBg)}>
            <CardIcon className={cn("h-4 w-4", color)} />
          </div>
          <div>
            <h3 className={cn("text-sm font-bold", color)}>{title}</h3>
            <p className="text-[10px] text-slate-400 font-medium">{items.length} صنف</p>
          </div>
        </div>
        {lowCount > 0 && (
          <span className="flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-600 border border-amber-100">
            <AlertTriangle className="h-3 w-3" />{lowCount} منخفض
          </span>
        )}
      </div>

      {/* Total */}
      <div className="px-5 pb-4">
        <p className={cn("text-3xl font-black tabular-nums leading-none tracking-tight", color)}>{formatNumber(total)}</p>
        <p className="text-[11px] font-semibold text-slate-400 mt-0.5">{unit}</p>
      </div>

      {/* Items List */}
      <div className="border-t border-slate-100">
        {items.map((item, idx) => {
          const pct = item.minimum_stock > 0 ? Math.min((item.total_quantity / item.minimum_stock) * 100, 100) : 100
          const isLow = item.minimum_stock > 0 && item.total_quantity <= item.minimum_stock
          return (
            <div key={item.id} className={cn(
              "flex items-center justify-between px-5 py-3 transition-colors hover:bg-slate-50/80",
              idx < items.length - 1 && "border-b border-slate-50"
            )}>
              <div className="flex items-center gap-3 min-w-0">
                <div className="flex-1 min-w-0">
                  <span className="text-xs font-semibold text-slate-700 block truncate">{item.name}</span>
                  {item.minimum_stock > 0 && (
                    <div className="mt-1.5 h-1 w-20 rounded-full bg-slate-100 overflow-hidden">
                      <div
                        className={cn(
                          "h-full rounded-full transition-all duration-500",
                          isLow ? "bg-amber-400" : "bg-green-400"
                        )}
                        style={{ width: `${pct}%` }}
                      />
                    </div>
                  )}
                </div>
              </div>
              <span className={cn(
                "text-xs font-bold tabular-nums whitespace-nowrap",
                isLow ? "text-amber-700" : "text-slate-700"
              )}>
                {formatNumber(item.total_quantity)} {item.content_unit}
              </span>
            </div>
          )
        })}
      </div>
    </div>
  )
}

// ── Items table ───────────────────────────────────────────────────────────────

function ItemsTable({ items }: { items: StockItem[] }) {
  const Section = ({ title, rows, color, icon: SectionIcon }: { title: string; rows: StockItem[]; color: string; icon: typeof Package }) => {
    if (rows.length === 0) return null
    const iconBg = color.replace('text-', 'bg-').replace(/-700$/, '-50')
    return (
      <section>
        <div className="mb-3 flex items-center gap-2">
          <div className={cn("rounded-lg p-1.5", iconBg)}>
            <SectionIcon className={cn("h-3.5 w-3.5", color)} />
          </div>
          <h3 className={cn("text-xs font-bold uppercase tracking-wider", color)}>{title}</h3>
          <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">{rows.length}</span>
        </div>
        <div className="overflow-hidden rounded-2xl border border-slate-200/60 bg-white" style={{ boxShadow: 'var(--shadow-card)' }}>
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-100 bg-slate-50/80 text-right text-[10px] font-bold uppercase tracking-wider text-slate-400">
                <th className="px-4 py-3">الصنف</th>
                <th className="px-4 py-3">الكمية الحالية</th>
                <th className="px-4 py-3">الحد الأدنى</th>
                <th className="px-4 py-3">وحدة الإدخال</th>
                <th className="px-4 py-3">الحالة</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-50">
              {rows.map(item => {
                const isLow = item.minimum_stock > 0 && item.total_quantity <= item.minimum_stock
                return (
                  <tr key={item.id} className={cn("transition-colors duration-150 hover:bg-slate-50/60", isLow && 'bg-amber-50/30')}>
                    <td className="px-4 py-3.5 font-semibold text-slate-800">{item.name}</td>
                    <td className="px-4 py-3.5 tabular-nums">
                      <div className="flex items-center gap-1.5">
                        {isLow && <AlertTriangle className="h-3 w-3 text-amber-500 shrink-0" />}
                        <span className={cn("font-semibold", isLow ? 'text-amber-700' : 'text-slate-700')}>
                          {formatNumber(item.total_quantity)} {item.content_unit}
                        </span>
                      </div>
                    </td>
                    <td className="px-4 py-3.5 text-slate-400 tabular-nums text-xs">
                      {item.minimum_stock > 0 ? `${formatNumber(item.minimum_stock)} ${item.content_unit}` : '—'}
                    </td>
                    <td className="px-4 py-3.5 text-slate-400 text-xs">{item.input_unit}</td>
                    <td className="px-4 py-3.5"><StockStatusBadge item={item} /></td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      </section>
    )
  }
  const feedItems  = items.filter(i => i.type_code === 'feed')
  const medItems   = items.filter(i => i.type_code === 'medicine')
  const otherItems = items.filter(i => i.type_code !== 'feed' && i.type_code !== 'medicine')
  return (
    <div className="space-y-6">
      <Section title="العلف"  rows={feedItems}  color="text-amber-700" icon={Package} />
      <Section title="الدواء" rows={medItems}   color="text-blue-700"  icon={Package} />
      <Section title="أخرى"  rows={otherItems} color="text-slate-600" icon={Package} />
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
    setForm(prev => ({ ...prev, [k]: e.target.value }))

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
      <div className="flex flex-col items-center justify-center rounded-2xl border border-green-200 bg-gradient-to-b from-green-50 to-white py-16 text-center" style={{ boxShadow: 'var(--shadow-card)' }}>
        <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
          <CheckCircle className="h-8 w-8 text-green-500" />
        </div>
        <p className="text-lg font-bold text-green-700">تمت إضافة الصنف بنجاح</p>
        <p className="mt-1 text-xs text-green-500">سيتم الانتقال لصفحة الأصناف تلقائياً...</p>
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
              <select value={form.item_type_id} onChange={set('item_type_id')} className={inputCls}>
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

              <Field label="وحدة المحتوى" required hint="مثال: كيلو، لتر">
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

// ── Add Shipment form ─────────────────────────────────────────────────────────

function AddShipmentForm({
  stockItems,
  warehouses,
  onSuccess,
}: {
  stockItems: StockItem[]
  warehouses: Warehouse[]
  onSuccess: () => void
}) {
  const [form, setForm] = useState({
    item_id:           '',
    warehouse_id:      '',
    transaction_date:  today,
    original_quantity: '',
    unit_price:        '',
    total_amount:      '',
    payment_status:    'paid',
    supplier_name:     '',
    invoice_no:        '',
    notes:             '',
    attachment:        null as File | null,
  })
  const [lastEdited, setLastEdited] = useState<'price' | 'total'>('price')
  const [saving,  setSaving]  = useState(false)
  const [error,   setError]   = useState<string | null>(null)
  const [success, setSuccess] = useState(false)

  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const val = e.target.value
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
      const payload: AddShipmentPayload = {
        item_id:           Number(form.item_id),
        warehouse_id:      Number(form.warehouse_id),
        transaction_date:  form.transaction_date,
        original_quantity: Number(form.original_quantity),
        unit_price:        form.unit_price    ? Number(form.unit_price)    : null,
        total_amount:      form.total_amount  ? Number(form.total_amount)  : null,
        payment_status:    (form.payment_status as 'paid' | 'unpaid' | 'partial') || 'paid',
        supplier_name:     form.supplier_name  || null,
        invoice_no:        form.invoice_no     || null,
        notes:             form.notes          || null,
        attachment:        form.attachment,
      }
      await inventoryApi.addShipment(payload)
      setSuccess(true)
      setForm({
        item_id: '',
        warehouse_id: warehouses.length === 1 ? String(warehouses[0].id) : '',
        transaction_date: today,
        original_quantity: '',
        unit_price: '',
        total_amount: '',
        payment_status: 'paid',
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
      <div className="flex flex-col items-center justify-center rounded-2xl border border-green-200 bg-gradient-to-b from-green-50 to-white py-16 text-center" style={{ boxShadow: 'var(--shadow-card)' }}>
        <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
          <CheckCircle className="h-8 w-8 text-green-500" />
        </div>
        <p className="text-lg font-bold text-green-700">تمت إضافة الحمولة بنجاح</p>
        <p className="mt-1 text-xs text-green-500">سيتم الانتقال لصفحة الحركات تلقائياً...</p>
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
          <div className="mb-5 flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-700">
            <AlertTriangle className="h-4 w-4 shrink-0" />
            لا يوجد مستودع نشط. أضف مستودعاً من إعدادات المزرعة أولاً.
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-5">
          {/* Section: Source */}
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="الصنف" required>
              <select value={form.item_id} onChange={set('item_id')} className={inputCls}>
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
          </div>

          {/* Section: Financials */}
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
                <select value={form.payment_status} onChange={set('payment_status')} className={inputCls}>
                  <option value="paid">مدفوع</option>
                  <option value="unpaid">غير مدفوع</option>
                  <option value="partial">جزئي</option>
                </select>
              </Field>
            </div>
          </div>

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
  if (transactions.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white py-20 text-center" style={{ boxShadow: 'var(--shadow-card)' }}>
        <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100">
          <Layers className="h-7 w-7 text-slate-300" />
        </div>
        <p className="font-bold text-slate-600">لا توجد حركات مسجّلة</p>
        <p className="mt-1 text-xs text-slate-400">أضف حمولة جديدة لبدء تتبع الحركات</p>
      </div>
    )
  }
  return (
    <div className="overflow-x-auto rounded-2xl border border-slate-200/60 bg-white" style={{ boxShadow: 'var(--shadow-card)' }}>
      <table className="w-full min-w-[1100px] text-xs">
        <thead>
          <tr className="sticky top-0 z-10 border-b border-slate-100 bg-slate-50/90 backdrop-blur text-right text-[10px] font-bold uppercase tracking-wider text-slate-400">
            {['التاريخ','الصنف','نوع الحركة','الاتجاه','الكمية الأصلية','الكمية المحسوبة','السعر','الإجمالي','حالة الدفع','المورد','رقم الفاتورة','المرجع','الفوج','المستخدم','الملاحظات']
              .map(h => <th key={h} className="px-3 py-3.5 whitespace-nowrap">{h}</th>)}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-50">
          {transactions.map(tx => {
            const dir     = DIRECTION_CONFIG[tx.direction]
            const DirIcon = dir?.icon ?? ArrowDownCircle
            const payment = tx.payment_status ? PAYMENT_STATUS_LABEL[tx.payment_status] : null
            return (
              <tr key={tx.id} className="transition-colors duration-150 hover:bg-slate-50/60">
                <td className="px-3 py-3 text-slate-500 whitespace-nowrap font-mono text-[10px]">{formatDate(tx.transaction_date)}</td>
                <td className="px-3 py-3 font-semibold text-slate-800 whitespace-nowrap">{tx.item_name ?? '—'}</td>
                <td className="px-3 py-3 text-slate-500 whitespace-nowrap">{TX_TYPE_LABEL[tx.transaction_type] ?? tx.transaction_type}</td>
                <td className="px-3 py-3 whitespace-nowrap">
                  {dir ? (
                    <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ${
                      tx.direction === 'in' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'
                    }`}>
                      <DirIcon className="h-3 w-3" />{dir.label}
                    </span>
                  ) : tx.direction}
                </td>
                <td className="px-3 py-3 tabular-nums text-slate-700 whitespace-nowrap">{formatNumber(tx.original_quantity)} {tx.input_unit}</td>
                <td className="px-3 py-3 tabular-nums text-slate-700 whitespace-nowrap">{formatNumber(tx.computed_quantity)} {tx.content_unit}</td>
                <td className="px-3 py-3 tabular-nums text-slate-700 whitespace-nowrap">{tx.unit_price != null ? `${formatNumber(tx.unit_price)} USD` : '—'}</td>
                <td className="px-3 py-3 tabular-nums font-semibold text-slate-800 whitespace-nowrap">{tx.total_amount != null ? `${formatNumber(tx.total_amount)} USD` : '—'}</td>
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
  )
}

// ── Alerts tab ────────────────────────────────────────────────────────────────

function AlertsTab({ items }: { items: StockItem[] }) {
  const lowItems = items.filter(i => i.minimum_stock > 0 && i.total_quantity <= i.minimum_stock)
  if (lowItems.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white py-20 text-center" style={{ boxShadow: 'var(--shadow-card)' }}>
        <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-50">
          <CheckCircle className="h-7 w-7 text-green-400" />
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
          <div className="rounded-lg bg-amber-50 p-1.5">
            <AlertTriangle className="h-3.5 w-3.5 text-amber-600" />
          </div>
          <h3 className="text-xs font-bold text-amber-700 uppercase tracking-wider">مواد تقترب من النفاد</h3>
        </div>
        <div className="overflow-hidden rounded-2xl border border-amber-200/60 bg-white" style={{ boxShadow: 'var(--shadow-card)' }}>
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-amber-100 bg-amber-50/50 text-right text-[10px] font-bold uppercase tracking-wider text-amber-600">
                <th className="px-4 py-3">الصنف</th>
                <th className="px-4 py-3">النوع</th>
                <th className="px-4 py-3">المتاح</th>
                <th className="px-4 py-3">الحد الأدنى</th>
                <th className="px-4 py-3">النسبة</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-amber-50">
              {lowItems.map(item => {
                const pct = item.minimum_stock > 0 ? Math.round((item.total_quantity / item.minimum_stock) * 100) : 0
                return (
                  <tr key={item.id} className="transition-colors hover:bg-amber-50/30">
                    <td className="px-4 py-3.5 font-semibold text-slate-800">{item.name}</td>
                    <td className="px-4 py-3.5 text-slate-500 text-xs">{TYPE_LABEL[item.type_code] ?? item.type_code}</td>
                    <td className="px-4 py-3.5 tabular-nums font-bold text-amber-700">
                      {formatNumber(item.total_quantity)} {item.content_unit}
                    </td>
                    <td className="px-4 py-3.5 tabular-nums text-slate-400 text-xs">
                      {formatNumber(item.minimum_stock)} {item.content_unit}
                    </td>
                    <td className="px-4 py-3.5">
                      <div className="flex items-center gap-2">
                        <div className="h-1.5 w-16 rounded-full bg-amber-100 overflow-hidden">
                          <div className="h-full rounded-full bg-amber-400" style={{ width: `${pct}%` }} />
                        </div>
                        <span className="text-[10px] font-bold text-amber-600 tabular-nums">{pct}%</span>
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
  const [activeTab, setActiveTab] = useState<Tab>('overview')

  useEffect(() => {
    setPageTitle('المخزون')
    setPageSubtitle(currentFarm?.name || null)
  }, [currentFarm, setPageTitle, setPageSubtitle])

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

  const items        = overviewData?.stock        ?? []
  const summary      = overviewData?.summary      ?? null
  const transactions = overviewData?.transactions ?? []
  const warehouses   = overviewData?.warehouses   ?? []
  const itemTypes    = overviewData?.item_types   ?? []
  const error        = isError ? 'تعذّر تحميل بيانات المخزون' : null

  const lowItems   = items.filter(i => i.minimum_stock > 0 && i.total_quantity <= i.minimum_stock)
  const feedItems  = items.filter(i => i.type_code === 'feed')
  const medItems   = items.filter(i => i.type_code === 'medicine')
  const otherItems = items.filter(i => i.type_code !== 'feed' && i.type_code !== 'medicine')

  const tabs: { id: Tab; label: string; badge?: number; icon: typeof Eye; isAction?: boolean }[] = [
    { id: 'overview',     label: 'نظرة عامة',  icon: Eye },
    { id: 'items',        label: 'الأصناف',     icon: ListChecks,  badge: items.length },
    { id: 'add-item',     label: 'صنف جديد',    icon: Plus,        isAction: true },
    { id: 'add-shipment', label: 'حمولة جديدة', icon: Truck,       isAction: true },
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
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
              <KpiCard label="رصيد العلف"           value={formatNumber(summary.feed_quantity)}     sub={summary.feed_unit}                             icon={Package}    color="text-amber-700" />
              <KpiCard label="رصيد الدواء"          value={formatNumber(summary.medicine_quantity)} sub={summary.medicine_unit}                         icon={Package}    color="text-blue-700" />
              <KpiCard label="مواد منخفضة"          value={String(summary.low_stock_count)}         sub={summary.low_stock_count > 0 ? 'تحتاج متابعة' : 'المخزون كافٍ'} icon={TrendingDown} color={summary.low_stock_count > 0 ? 'text-red-600' : 'text-green-600'} />
              <KpiCard label="آخر حمولة"            value={summary.last_shipment_date ? formatDate(summary.last_shipment_date) : '—'}                  icon={Truck}      color="text-slate-600" />
              <KpiCard label="إجمالي قيمة المخزون" value={formatNumber(summary.total_value)}       sub="USD"                                           icon={DollarSign} color="text-emerald-700" />
            </div>
          )}

          {/* Tab Navigation */}
          <div className="rounded-2xl border border-slate-200/60 bg-white p-1.5" style={{ boxShadow: 'var(--shadow-card)' }}>
            <nav className="flex flex-wrap gap-1">
              {tabs.map(tab => {
                const isActive = activeTab === tab.id
                return (
                  <button
                    key={tab.id}
                    onClick={() => setActiveTab(tab.id)}
                    className={cn(
                      "flex items-center gap-1.5 rounded-xl px-3.5 py-2.5 text-xs font-bold transition-all duration-200",
                      isActive
                        ? tab.isAction
                          ? "bg-primary-600 text-white shadow-md shadow-primary-200"
                          : "bg-slate-900 text-white shadow-md"
                        : tab.isAction
                          ? "text-primary-600 hover:bg-primary-50"
                          : "text-slate-500 hover:bg-slate-50 hover:text-slate-700"
                    )}
                  >
                    <tab.icon className={cn("h-3.5 w-3.5", isActive && "shrink-0")} />
                    <span className="hidden sm:inline">{tab.label}</span>
                    <span className="sm:hidden">{tab.id === 'add-item' ? 'صنف' : tab.id === 'add-shipment' ? 'حمولة' : tab.label}</span>
                    {tab.badge != null && tab.badge > 0 && (
                      <span className={cn(
                        "rounded-full px-1.5 py-0.5 text-[9px] font-bold leading-none",
                        isActive
                          ? "bg-white/20 text-white"
                          : tab.id === 'alerts'
                            ? "bg-amber-100 text-amber-700"
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
                <MaterialCard title="العلف"  items={feedItems}  color="text-amber-700" icon={Package} />
                <MaterialCard title="الدواء" items={medItems}   color="text-blue-700"  icon={Package} />
                <MaterialCard title="أخرى"  items={otherItems} color="text-slate-600" icon={Package} />
                {feedItems.length === 0 && medItems.length === 0 && otherItems.length === 0 && (
                  <div className="col-span-full flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white py-20 text-center" style={{ boxShadow: 'var(--shadow-card)' }}>
                    <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100">
                      <Package className="h-8 w-8 text-slate-300" />
                    </div>
                    <h3 className="text-lg font-bold text-slate-700">لا توجد أصناف في المخزون</h3>
                    <p className="mt-1 text-xs text-slate-400">أضف أصناف من تبويب &quot;صنف جديد&quot;</p>
                    <button
                      onClick={() => setActiveTab('add-item')}
                      className="mt-4 flex items-center gap-1.5 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-700 transition-all duration-200 shadow-md shadow-primary-200"
                    >
                      <Plus className="h-4 w-4" /> إضافة صنف جديد
                    </button>
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
                  <button onClick={() => setActiveTab('add-item')} className="mt-4 flex items-center gap-1.5 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-700 transition-all duration-200 shadow-md shadow-primary-200">
                    <Plus className="h-4 w-4" /> إضافة صنف
                  </button>
                </div>
              ) : <ItemsTable items={items} />
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
                onSuccess={() => { loadData(); setActiveTab('movements') }}
              />
            )}

            {activeTab === 'movements' && <MovementsTable transactions={transactions} />}

            {activeTab === 'alerts' && <AlertsTab items={items} />}
          </div>
        </>
      )}
    </div>
  )
}

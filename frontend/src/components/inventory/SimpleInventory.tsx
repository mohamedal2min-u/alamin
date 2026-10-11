'use client'

import { useMemo, useState } from 'react'
import { toast } from 'sonner'
import { AlertTriangle, Boxes, Check, Loader2, Package, Pill, Plus, Search, Wheat } from 'lucide-react'
import { Dialog } from '@/components/ui/Dialog'
import { inventoryApi } from '@/lib/api/inventory'
import type { ItemType, StockItem, Warehouse } from '@/lib/api/inventory'
import { cn, formatNumber, toEnglishDigits } from '@/lib/utils'

// ── مخزون مبسّط للمشرف: أقسام واضحة، وزر واحد لكل خطوة، بلا أسعار ولا وحدات معقّدة ──────────

type Section = 'feed' | 'medicine' | 'other'

const SECTIONS: { key: Section; title: string; icon: React.ElementType; tone: string }[] = [
  { key: 'feed',     title: 'العلف',        icon: Wheat,   tone: 'text-amber-600 bg-amber-50 dark:bg-amber-900/20' },
  { key: 'medicine', title: 'الأدوية',      icon: Pill,    tone: 'text-teal-600 bg-teal-50 dark:bg-teal-900/20' },
  { key: 'other',    title: 'أصناف أخرى',   icon: Package, tone: 'text-slate-600 bg-slate-100 dark:bg-slate-700' },
]

const sectionOf = (item: StockItem): Section =>
  item.type_code === 'feed' ? 'feed' : item.type_code === 'medicine' ? 'medicine' : 'other'

const UNIT_CHIPS: Record<Exclude<Section, 'feed'>, string[]> = {
  medicine: ['عبوة', 'زجاجة', 'علبة', 'كيس', 'لتر', 'مل', 'جرام'],
  other:    ['كيس', 'كيلو', 'لتر', 'حبة', 'قطعة'],
}

const today = () => {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const num = (v: string) => parseFloat(toEnglishDigits(v).replace(',', '.'))

interface Props {
  items: StockItem[]
  warehouses: Warehouse[]
  itemTypes: ItemType[]
  onChanged: () => void
}

export function SimpleInventory({ items, warehouses, itemTypes, onChanged }: Props) {
  const [query, setQuery] = useState('')
  const [qtyItem, setQtyItem] = useState<StockItem | null>(null)
  const [newSection, setNewSection] = useState<Section | null>(null)

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase()
    return q ? items.filter((i) => i.name.toLowerCase().includes(q)) : items
  }, [items, query])

  return (
    <div className="space-y-5 pb-6" dir="rtl">
      {/* بحث */}
      <div className="relative">
        <Search className="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="ابحث عن صنف..."
          className="h-12 w-full rounded-2xl border border-slate-200 bg-white pe-4 ps-4 pr-10 text-sm font-semibold text-slate-800 outline-none focus:border-primary-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
        />
      </div>

      {SECTIONS.map((sec) => {
        const list = filtered.filter((i) => sectionOf(i) === sec.key)
        const Icon = sec.icon
        return (
          <section key={sec.key} className="space-y-2.5">
            <div className="flex items-center justify-between">
              <h2 className="flex items-center gap-2 text-base font-extrabold text-slate-800 dark:text-slate-100">
                <span className={cn('flex h-8 w-8 items-center justify-center rounded-xl', sec.tone)}><Icon className="h-4 w-4" /></span>
                {sec.title}
                <span className="text-xs font-bold text-slate-400">({list.length})</span>
              </h2>
              <button
                onClick={() => setNewSection(sec.key)}
                className="flex items-center gap-1 rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-bold text-primary-700 active:scale-95 dark:border-primary-800 dark:bg-primary-900/20 dark:text-primary-300"
              >
                <Plus className="h-3.5 w-3.5" /> صنف جديد
              </button>
            </div>

            {list.length === 0 ? (
              <p className="rounded-2xl border border-dashed border-slate-200 py-6 text-center text-sm text-slate-400 dark:border-slate-700">
                {query ? 'لا نتائج' : 'لا توجد أصناف بعد'}
              </p>
            ) : (
              <ul className="space-y-2.5">
                {list.map((item) => (
                  <ItemCard key={item.id} item={item} onAdd={() => setQtyItem(item)} />
                ))}
              </ul>
            )}
          </section>
        )
      })}

      <AddQuantitySheet
        item={qtyItem}
        warehouses={warehouses}
        onClose={() => setQtyItem(null)}
        onDone={() => { setQtyItem(null); onChanged() }}
      />
      <NewItemSheet
        section={newSection}
        items={items}
        itemTypes={itemTypes}
        warehouses={warehouses}
        onClose={() => setNewSection(null)}
        onDone={() => { setNewSection(null); onChanged() }}
      />
    </div>
  )
}

// ── بطاقة صنف ───────────────────────────────────────────────────────────────
function ItemCard({ item, onAdd }: { item: StockItem; onAdd: () => void }) {
  const isFeed = item.type_code === 'feed'
  const low = item.minimum_stock > 0 && item.total_quantity <= item.minimum_stock
  const empty = item.total_quantity <= 0
  const bags = isFeed && item.unit_value > 1 ? item.total_quantity / item.unit_value : null

  return (
    <li className={cn('flex items-center gap-3 rounded-2xl border bg-white p-3.5 shadow-sm dark:bg-slate-800', low || empty ? 'border-amber-300 dark:border-amber-700' : 'border-slate-200 dark:border-slate-700')}>
      <div className="min-w-0 flex-1">
        <h3 className="truncate text-[15px] font-bold text-slate-800 dark:text-slate-100">{item.name}</h3>
        <div className="mt-0.5 flex flex-wrap items-baseline gap-x-2">
          <span className={cn('text-2xl font-extrabold tabular-nums', empty ? 'text-rose-600' : 'text-slate-800 dark:text-slate-100')}>
            {formatNumber(item.total_quantity)}
          </span>
          <span className="text-xs font-semibold text-slate-500">{item.content_unit}</span>
          {bags != null && <span className="text-xs text-slate-400">≈ {formatNumber(Math.round(bags * 10) / 10)} {item.input_unit}</span>}
        </div>
        {(low || empty) && (
          <p className="mt-1 flex items-center gap-1 text-[11px] font-bold text-amber-600">
            <AlertTriangle className="h-3 w-3" /> {empty ? 'نفد المخزون' : 'المخزون قليل'}
          </p>
        )}
      </div>
      <button
        onClick={onAdd}
        className="flex h-14 shrink-0 flex-col items-center justify-center gap-0.5 rounded-2xl bg-primary-600 px-4 text-white shadow-sm active:scale-95"
        aria-label={`إضافة كمية إلى ${item.name}`}
      >
        <Plus className="h-5 w-5" />
        <span className="text-[10px] font-bold">إضافة كمية</span>
      </button>
    </li>
  )
}

// ── إضافة كمية لصنف موجود ─────────────────────────────────────────────────────
function AddQuantitySheet({ item, warehouses, onClose, onDone }: { item: StockItem | null; warehouses: Warehouse[]; onClose: () => void; onDone: () => void }) {
  const [qty, setQty] = useState('')
  const [date, setDate] = useState(today())
  const [note, setNote] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const close = () => { setQty(''); setNote(''); setError(null); setDate(today()); onClose() }
  const q = num(qty)
  const isFeed = item?.type_code === 'feed'
  const kg = item && isFeed && q > 0 ? q * item.unit_value : null

  const save = async () => {
    if (!item) return
    if (!(q > 0)) { setError('اكتب الكمية'); return }
    const warehouse = warehouses[0]
    if (!warehouse) { setError('لا يوجد مستودع، تواصل مع مدير النظام'); return }
    setSaving(true); setError(null)
    try {
      await inventoryApi.addShipment({
        item_id: item.id,
        warehouse_id: warehouse.id,
        transaction_date: date,
        original_quantity: q,
        notes: note.trim() || null,
      })
      toast.success(`تمت إضافة ${formatNumber(q)} ${item.input_unit} إلى ${item.name}`)
      setQty(''); setNote('')
      onDone()
    } catch (e: unknown) {
      setError((e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'تعذر الحفظ، حاول مرة أخرى')
    } finally {
      setSaving(false)
    }
  }

  return (
    <Dialog isOpen={!!item} onClose={close} title={item ? `إضافة كمية — ${item.name}` : ''}>
      {item && (
        <div className="space-y-4 p-5" dir="rtl">
          <p className="text-sm text-slate-500">
            الموجود الآن: <strong className="text-slate-800 dark:text-slate-100">{formatNumber(item.total_quantity)} {item.content_unit}</strong>
          </p>

          <div>
            <label className="mb-1 block text-xs font-semibold text-slate-500">الكمية المضافة ({item.input_unit})</label>
            <input
              autoFocus
              inputMode="decimal"
              value={qty}
              onChange={(e) => setQty(toEnglishDigits(e.target.value))}
              placeholder="0"
              className="h-14 w-full rounded-2xl border border-slate-300 px-4 text-2xl font-extrabold text-slate-800 outline-none focus:border-primary-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
            />
            {kg != null && (
              <p className="mt-1.5 text-xs font-semibold text-primary-600">= {formatNumber(kg)} {item.content_unit}</p>
            )}
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="mb-1 block text-xs font-semibold text-slate-500">التاريخ</label>
              <input type="date" value={date} max={today()} onChange={(e) => setDate(e.target.value)}
                className="h-11 w-full rounded-xl border border-slate-300 px-3 text-sm outline-none focus:border-primary-500 dark:border-slate-600 dark:bg-slate-800" />
            </div>
            <div>
              <label className="mb-1 block text-xs font-semibold text-slate-500">ملاحظة (اختياري)</label>
              <input value={note} onChange={(e) => setNote(e.target.value)} placeholder="مثال: من المورد"
                className="h-11 w-full rounded-xl border border-slate-300 px-3 text-sm outline-none focus:border-primary-500 dark:border-slate-600 dark:bg-slate-800" />
            </div>
          </div>

          <p className="rounded-lg bg-slate-50 px-3 py-2 text-[11px] font-semibold text-slate-500 dark:bg-slate-800">السعر يحدده المحاسب لاحقاً.</p>
          {error && <p className="text-sm font-semibold text-red-600">{error}</p>}

          <button onClick={save} disabled={saving}
            className="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-primary-600 text-base font-bold text-white active:scale-[0.99] disabled:opacity-60">
            {saving ? <Loader2 className="h-5 w-5 animate-spin" /> : <Check className="h-5 w-5" />} إضافة
          </button>
        </div>
      )}
    </Dialog>
  )
}

// ── صنف جديد (نموذج واحد قصير) ────────────────────────────────────────────────
function NewItemSheet({ section, items, itemTypes, warehouses, onClose, onDone }: {
  section: Section | null; items: StockItem[]; itemTypes: ItemType[]; warehouses: Warehouse[]; onClose: () => void; onDone: () => void
}) {
  const [name, setName] = useState('')
  const [unit, setUnit] = useState('')
  const [bagKg, setBagKg] = useState('50')
  const [otherTypeId, setOtherTypeId] = useState('')
  const [qty, setQty] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const close = () => { setName(''); setUnit(''); setBagKg('50'); setOtherTypeId(''); setQty(''); setError(null); onClose() }

  const otherTypes = itemTypes.filter((t) => t.code && t.code !== 'feed' && t.code !== 'medicine')
  const duplicate = items.find((i) => i.name.trim().toLowerCase() === name.trim().toLowerCase() && name.trim() !== '')
  const sectionTitle = SECTIONS.find((s) => s.key === section)?.title ?? ''
  const typeId = section === 'feed'
    ? itemTypes.find((t) => t.code === 'feed')?.id
    : section === 'medicine'
      ? itemTypes.find((t) => t.code === 'medicine')?.id
      : otherTypeId ? Number(otherTypeId) : otherTypes.length === 1 ? otherTypes[0].id : undefined

  const save = async () => {
    if (!section) return
    if (!name.trim()) { setError('اكتب اسم الصنف'); return }
    if (duplicate) { setError('هذا الصنف موجود بالفعل — استعمل زر «إضافة كمية» بجانبه'); return }
    if (section !== 'feed' && !unit) { setError('اختر الوحدة'); return }
    if (section === 'feed' && !(num(bagKg) > 0)) { setError('اكتب وزن الكيس بالكيلو'); return }
    if (!typeId) { setError('اختر نوع الصنف'); return }

    const isFeed = section === 'feed'
    setSaving(true); setError(null)
    try {
      const created = await inventoryApi.createItem({
        item_type_id: typeId,
        name: name.trim(),
        input_unit: isFeed ? 'كيس' : unit,
        unit_value: isFeed ? num(bagKg) : 1,
        content_unit: isFeed ? 'كيلو' : unit,
        minimum_stock: null,
        notes: null,
      })

      const q = num(qty)
      if (q > 0 && warehouses[0]) {
        await inventoryApi.addShipment({
          item_id: created.data.id,
          warehouse_id: warehouses[0].id,
          transaction_date: today(),
          original_quantity: q,
        })
      }
      toast.success(q > 0 ? `تمت إضافة «${name.trim()}» بكمية ${formatNumber(q)}` : `تمت إضافة «${name.trim()}»`)
      close()
      onDone()
    } catch (e: unknown) {
      setError((e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'تعذر الحفظ، حاول مرة أخرى')
    } finally {
      setSaving(false)
    }
  }

  return (
    <Dialog isOpen={!!section} onClose={close} title={section ? `صنف جديد — ${sectionTitle}` : ''}>
      {section && (
        <div className="space-y-4 p-5" dir="rtl">
          {section === 'other' && otherTypes.length > 1 && (
            <div>
              <label className="mb-1 block text-xs font-semibold text-slate-500">النوع</label>
              <select value={otherTypeId} onChange={(e) => setOtherTypeId(e.target.value)}
                className="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm dark:border-slate-600 dark:bg-slate-800">
                <option value="">اختر النوع</option>
                {otherTypes.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
              </select>
            </div>
          )}

          <div>
            <label className="mb-1 block text-xs font-semibold text-slate-500">اسم الصنف</label>
            <input autoFocus value={name} onChange={(e) => setName(e.target.value)}
              placeholder={section === 'feed' ? 'مثال: علف بادئ' : section === 'medicine' ? 'مثال: لقاح نيوكاسل' : 'اسم الصنف'}
              className="h-12 w-full rounded-xl border border-slate-300 px-3 text-base font-semibold outline-none focus:border-primary-500 dark:border-slate-600 dark:bg-slate-800" />
            {duplicate && (
              <p className="mt-1.5 flex items-center gap-1 text-xs font-semibold text-amber-600">
                <AlertTriangle className="h-3.5 w-3.5" /> «{duplicate.name}» موجود بالفعل — أضف إليه كمية بدل صنف جديد.
              </p>
            )}
          </div>

          {section === 'feed' ? (
            <div>
              <label className="mb-1 block text-xs font-semibold text-slate-500">وزن الكيس الواحد (كيلو)</label>
              <input inputMode="decimal" value={bagKg} onChange={(e) => setBagKg(toEnglishDigits(e.target.value))}
                className="h-12 w-full rounded-xl border border-slate-300 px-3 text-base font-bold outline-none focus:border-primary-500 dark:border-slate-600 dark:bg-slate-800" />
              <p className="mt-1 text-[11px] text-slate-400">يُحسب المخزون بالكيلو تلقائياً من عدد الأكياس.</p>
            </div>
          ) : (
            <div>
              <label className="mb-1.5 block text-xs font-semibold text-slate-500">الوحدة</label>
              <div className="flex flex-wrap gap-2">
                {UNIT_CHIPS[section].map((u) => (
                  <button key={u} type="button" onClick={() => setUnit(u)}
                    className={cn('rounded-full border px-4 py-2 text-sm font-bold transition-colors',
                      unit === u ? 'border-primary-600 bg-primary-600 text-white' : 'border-slate-200 bg-white text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300')}>
                    {u}
                  </button>
                ))}
              </div>
            </div>
          )}

          <div>
            <label className="mb-1 block text-xs font-semibold text-slate-500">
              الكمية الحالية {section === 'feed' ? '(بالأكياس)' : unit ? `(${unit})` : ''} — اختياري
            </label>
            <input inputMode="decimal" value={qty} onChange={(e) => setQty(toEnglishDigits(e.target.value))} placeholder="0"
              className="h-12 w-full rounded-xl border border-slate-300 px-3 text-lg font-bold outline-none focus:border-primary-500 dark:border-slate-600 dark:bg-slate-800" />
          </div>

          {error && <p className="text-sm font-semibold text-red-600">{error}</p>}

          <button onClick={save} disabled={saving}
            className="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-primary-600 text-base font-bold text-white active:scale-[0.99] disabled:opacity-60">
            {saving ? <Loader2 className="h-5 w-5 animate-spin" /> : <Boxes className="h-5 w-5" />} حفظ الصنف
          </button>
        </div>
      )}
    </Dialog>
  )
}

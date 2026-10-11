'use client'

import { useCallback, useState } from 'react'
import { toast } from 'sonner'
import { AlertTriangle, Check, Loader2, Package, Pill, Plus, ScanLine, Wheat } from 'lucide-react'
import { Dialog } from '@/components/ui/Dialog'
import { BarcodeScanner } from './BarcodeScanner'
import { inventoryApi } from '@/lib/api/inventory'
import type { BarcodeSuggestion, ItemType, StockItem, Warehouse } from '@/lib/api/inventory'
import { cn, formatNumber, toEnglishDigits } from '@/lib/utils'

// ── إضافة حمولة موحّدة (صنف موجود أو جديد) + مسح باركود — بلا أسعار ──────────────────────────

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

interface ShipmentTarget {
  key: number
  section: Section | null          // null = غير معروف بعد (باركود جديد): يختاره المستخدم
  item: StockItem | null
  barcode?: string
  suggestedName?: string | null
  suggestion?: BarcodeSuggestion | null
}

interface Props {
  items: StockItem[]
  warehouses: Warehouse[]
  itemTypes: ItemType[]
  onChanged: () => void
}

export function ShipmentActions({ items, warehouses, itemTypes, onChanged }: Props) {
  const [shipment, setShipment] = useState<ShipmentTarget | null>(null)
  const [scanning, setScanning] = useState(false)
  const [looking, setLooking] = useState(false)

  const open = (t: Omit<ShipmentTarget, 'key'>) => setShipment({ ...t, key: Date.now() })

  const onScanned = useCallback(async (code: string) => {
    setScanning(false)
    const local = items.find((i) => i.barcode === code)
    if (local) { open({ section: sectionOf(local), item: local, barcode: code }); return }
    setLooking(true)
    try {
      const r = await inventoryApi.lookupBarcode(code)
      const found = r.found && r.item_id ? items.find((i) => i.id === r.item_id) : undefined
      if (found) open({ section: sectionOf(found), item: found, barcode: code })
      else open({ section: r.suggestion?.section ?? null, item: null, barcode: code, suggestedName: r.suggested_name, suggestion: r.suggestion })
    } catch {
      open({ section: null, item: null, barcode: code })
    } finally {
      setLooking(false)
    }
  }, [items])

  return (
    <>
      <div className="grid grid-cols-2 gap-3" dir="rtl">
        <button
          onClick={() => open({ section: null, item: null })}
          className="flex h-14 items-center justify-center gap-2 rounded-2xl bg-primary-600 text-sm font-bold text-white shadow-md shadow-primary-200 active:scale-[0.98]"
        >
          <Plus className="h-5 w-5" /> إضافة حمولة
        </button>
        <button
          onClick={() => setScanning(true)}
          disabled={looking}
          className="flex h-14 items-center justify-center gap-2 rounded-2xl bg-slate-900 text-sm font-bold text-white shadow-sm active:scale-[0.98] disabled:opacity-60 dark:bg-slate-100 dark:text-slate-900"
        >
          {looking ? <Loader2 className="h-5 w-5 animate-spin" /> : <ScanLine className="h-5 w-5" />}
          مسح باركود
        </button>
      </div>

      <BarcodeScanner isOpen={scanning} onClose={() => setScanning(false)} onDetected={onScanned} />
      <ShipmentSheet
        key={shipment?.key ?? 0}
        target={shipment}
        items={items}
        itemTypes={itemTypes}
        warehouses={warehouses}
        onClose={() => setShipment(null)}
        onDone={() => { setShipment(null); onChanged() }}
      />
    </>
  )
}

// ── إضافة حمولة (صنف موجود أو جديد في نموذج واحد) ─────────────────────────────
function ShipmentSheet({ target, items, itemTypes, warehouses, onClose, onDone }: {
  target: ShipmentTarget | null
  items: StockItem[]; itemTypes: ItemType[]; warehouses: Warehouse[]
  onClose: () => void; onDone: () => void
}) {
  const [name, setName] = useState(target?.suggestedName ?? '')
  const [pickedSection, setPickedSection] = useState<Section | null>(null)
  const [qty, setQty] = useState('')
  const [date, setDate] = useState(today())
  const [note, setNote] = useState('')
  const sug = target?.suggestion
  const [unit, setUnit] = useState(sug?.unit ?? '')
  const [bagKg, setBagKg] = useState(sug?.weight_kg ? String(sug.weight_kg) : '50')
  const [otherTypeId, setOtherTypeId] = useState('')
  const [confirmNew, setConfirmNew] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const section = target ? (target.section ?? pickedSection) : null
  const barcode = target?.barcode
  const locked = target?.item ?? null
  const isFeed = section === 'feed'

  const close = () => {
    setName(''); setQty(''); setNote(''); setUnit(''); setBagKg('50'); setOtherTypeId('')
    setConfirmNew(false); setError(null); setDate(today()); onClose()
  }

  const typed = name.trim().toLowerCase()
  const sectionItems = section ? items.filter((i) => sectionOf(i) === section) : []
  const matched = locked ?? (typed ? sectionItems.find((i) => i.name.trim().toLowerCase() === typed) : undefined)
  const suggestions = !locked && typed && !matched ? sectionItems.filter((i) => i.name.toLowerCase().includes(typed)).slice(0, 4) : []
  const isNew = !matched && typed !== ''

  const otherTypes = itemTypes.filter((t) => t.code && t.code !== 'feed' && t.code !== 'medicine')
  const typeId = section === 'feed'
    ? itemTypes.find((t) => t.code === 'feed')?.id
    : section === 'medicine'
      ? itemTypes.find((t) => t.code === 'medicine')?.id
      : otherTypeId ? Number(otherTypeId) : otherTypes.length === 1 ? otherTypes[0].id : undefined

  const q = num(qty)
  const shownUnit = matched ? matched.input_unit : isFeed ? 'كيس' : unit
  const kg = matched && matched.type_code === 'feed' && q > 0 ? q * matched.unit_value : null

  const validate = (): boolean => {
    if (!matched && !name.trim()) { setError('اكتب اسم الصنف'); return false }
    if (!(q > 0)) { setError('اكتب الكمية'); return false }
    if (!warehouses[0]) { setError('لا يوجد مستودع، تواصل مع مدير النظام'); return false }
    if (isNew) {
      if (!isFeed && !unit) { setError('اختر الوحدة'); return false }
      if (isFeed && !(num(bagKg) > 0)) { setError('اكتب وزن الكيس بالكيلو'); return false }
      if (!typeId) { setError('اختر نوع الصنف'); return false }
    }
    return true
  }

  const submit = async () => {
    setError(null)
    if (!validate()) return
    if (isNew && !confirmNew) { setConfirmNew(true); return }   // تأكيد أنه صنف جديد
    setSaving(true)
    try {
      let itemId = matched?.id
      if (matched && barcode && !matched.barcode) {
        await inventoryApi.setBarcode(matched.id, barcode)   // ربط الباركود بصنف موجود ليُعرف المرة القادمة
      }
      if (!itemId) {
        const created = await inventoryApi.createItem({
          item_type_id: typeId!,
          name: name.trim(),
          barcode: barcode ?? null,
          input_unit: isFeed ? 'كيس' : unit,
          unit_value: isFeed ? num(bagKg) : 1,
          content_unit: isFeed ? 'كيلو' : unit,
          minimum_stock: null,
          notes: null,
        })
        itemId = created.data.id
      }
      await inventoryApi.addShipment({
        item_id: itemId,
        warehouse_id: warehouses[0].id,
        transaction_date: date,
        original_quantity: q,
        notes: note.trim() || null,
      })
      toast.success(`تمت إضافة ${formatNumber(q)} ${shownUnit} إلى ${matched?.name ?? name.trim()}`)
      close()
      onDone()
    } catch (e: unknown) {
      setError((e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'تعذر الحفظ، حاول مرة أخرى')
      setConfirmNew(false)
    } finally {
      setSaving(false)
    }
  }

  const sectionTitle = SECTIONS.find((s) => s.key === section)?.title ?? ''
  const field = 'w-full rounded-xl border border-slate-300 px-3 outline-none focus:border-primary-500 dark:border-slate-600 dark:bg-slate-800'

  return (
    <Dialog isOpen={!!target} onClose={close} title={target ? (section || locked ? `إضافة حمولة — ${locked ? locked.name : sectionTitle}` : 'إضافة حمولة') : ''}>
      {target && !section && (
        <div className="space-y-3 p-5" dir="rtl">
          <p className="text-sm font-bold text-slate-700 dark:text-slate-200">{target.barcode ? 'باركود جديد غير مسجل. ما نوع الصنف؟' : 'ما نوع الصنف الذي وصل؟'}</p>
          <div className="grid grid-cols-3 gap-2">
            {SECTIONS.map((sec) => {
              const Icon = sec.icon
              return (
                <button key={sec.key} onClick={() => setPickedSection(sec.key)}
                  className="flex flex-col items-center gap-1.5 rounded-2xl border border-slate-200 bg-white py-4 text-sm font-bold text-slate-700 active:scale-95 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                  <span className={cn('flex h-10 w-10 items-center justify-center rounded-xl', sec.tone)}><Icon className="h-5 w-5" /></span>
                  {sec.title}
                </button>
              )
            })}
          </div>
        </div>
      )}
      {section && (
        <div className="space-y-4 p-5" dir="rtl">
          {locked ? (
            <p className="text-sm text-slate-500">
              الموجود الآن: <strong className="text-slate-800 dark:text-slate-100">{formatNumber(locked.total_quantity)} {locked.content_unit}</strong>
            </p>
          ) : (
            <div>
              <label className="mb-1 block text-xs font-semibold text-slate-500">اسم الصنف</label>
              <input autoFocus={!target?.suggestedName} value={name} onChange={(e) => { setName(e.target.value); setConfirmNew(false) }}
                placeholder={isFeed ? 'مثال: علف بادئ' : section === 'medicine' ? 'مثال: لقاح نيوكاسل' : 'اسم الصنف'}
                className={cn(field, 'h-12 text-base font-semibold')} />
              {suggestions.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-2">
                  {suggestions.map((s) => (
                    <button key={s.id} type="button" onClick={() => setName(s.name)}
                      className="rounded-full border border-primary-200 bg-primary-50 px-3 py-1.5 text-xs font-bold text-primary-700 dark:border-primary-800 dark:bg-primary-900/20 dark:text-primary-300">
                      {s.name}
                    </button>
                  ))}
                </div>
              )}
              {matched && <p className="mt-1.5 flex items-center gap-1 text-xs font-semibold text-emerald-600"><Check className="h-3.5 w-3.5" /> صنف موجود — الحمولة ستُضاف إليه.</p>}
              {isNew && <p className="mt-1.5 flex items-center gap-1 text-xs font-semibold text-amber-600"><AlertTriangle className="h-3.5 w-3.5" /> هذا الاسم غير موجود في المخزون، سيُعتبر صنفاً جديداً.</p>}
              {barcode && (
                <div className="mt-2 rounded-xl bg-primary-50 px-3 py-2 text-[11px] font-semibold text-primary-800 dark:bg-primary-900/20 dark:text-primary-200">
                  <p>الباركود: <span className="tabular-nums">{barcode}</span></p>
                  {sug ? (
                    <p className="mt-0.5">تعبّأت البيانات تلقائياً من الباركود{sug.quantity_text ? ` (العبوة: ${sug.quantity_text})` : ''} — راجعها وعدّلها إن لزم.</p>
                  ) : (
                    <p className="mt-0.5 text-slate-500">لم نجد معلومات لهذا الباركود، اكتب الاسم مرة واحدة وسيُعرف بعدها تلقائياً.</p>
                  )}
                </div>
              )}
            </div>
          )}

          {isNew && (
            <>
              {section === 'other' && otherTypes.length > 1 && (
                <select value={otherTypeId} onChange={(e) => setOtherTypeId(e.target.value)} className={cn(field, 'h-11 bg-white text-sm')}>
                  <option value="">اختر النوع</option>
                  {otherTypes.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                </select>
              )}
              {isFeed ? (
                <div>
                  <label className="mb-1 block text-xs font-semibold text-slate-500">وزن الكيس الواحد (كيلو)</label>
                  <input inputMode="decimal" value={bagKg} onChange={(e) => setBagKg(toEnglishDigits(e.target.value))} className={cn(field, 'h-12 text-base font-bold')} />
                </div>
              ) : (
                <div>
                  <label className="mb-1.5 block text-xs font-semibold text-slate-500">الوحدة</label>
                  <div className="flex flex-wrap gap-2">
                    {Array.from(new Set([...(sug?.unit ? [sug.unit] : []), ...UNIT_CHIPS[section as Exclude<Section, 'feed'>]])).map((u) => (
                      <button key={u} type="button" onClick={() => setUnit(u)}
                        className={cn('rounded-full border px-4 py-2 text-sm font-bold transition-colors',
                          unit === u ? 'border-primary-600 bg-primary-600 text-white' : 'border-slate-200 bg-white text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300')}>
                        {u}
                      </button>
                    ))}
                  </div>
                </div>
              )}
            </>
          )}

          <div>
            <label className="mb-1 block text-xs font-semibold text-slate-500">الكمية{shownUnit ? ` (${shownUnit})` : ''}</label>
            <input inputMode="decimal" value={qty} onChange={(e) => setQty(toEnglishDigits(e.target.value))} placeholder="0"
              autoFocus={!!locked || !!target?.suggestedName}
              className="h-14 w-full rounded-2xl border border-slate-300 px-4 text-2xl font-extrabold text-slate-800 outline-none focus:border-primary-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
            {kg != null && <p className="mt-1.5 text-xs font-semibold text-primary-600">= {formatNumber(kg)} {matched!.content_unit}</p>}
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="mb-1 block text-xs font-semibold text-slate-500">التاريخ</label>
              <input type="date" value={date} max={today()} onChange={(e) => setDate(e.target.value)} className={cn(field, 'h-11 text-sm')} />
            </div>
            <div>
              <label className="mb-1 block text-xs font-semibold text-slate-500">ملاحظة (اختياري)</label>
              <input value={note} onChange={(e) => setNote(e.target.value)} placeholder="مثال: من المورد" className={cn(field, 'h-11 text-sm')} />
            </div>
          </div>

          <p className="rounded-lg bg-slate-50 px-3 py-2 text-[11px] font-semibold text-slate-500 dark:bg-slate-800">السعر يحدده المحاسب لاحقاً.</p>

          {confirmNew && (
            <div className="rounded-2xl border border-amber-300 bg-amber-50 p-3.5 dark:border-amber-700 dark:bg-amber-900/20">
              <p className="text-sm font-bold text-amber-800 dark:text-amber-200">
                «{name.trim()}» غير موجود في المخزون. هل هو صنف جديد فعلاً؟
              </p>
              <p className="mt-1 text-xs text-amber-700 dark:text-amber-300">إن كان مكتوباً بشكل مختلف عن صنف موجود، ارجع واختره من الاقتراحات.</p>
            </div>
          )}

          {error && <p className="text-sm font-semibold text-red-600">{error}</p>}

          <div className="flex gap-2">
            {confirmNew && (
              <button onClick={() => setConfirmNew(false)} disabled={saving}
                className="h-12 flex-1 rounded-2xl border border-slate-300 text-base font-bold text-slate-600 dark:border-slate-600 dark:text-slate-300">
                رجوع
              </button>
            )}
            <button onClick={submit} disabled={saving}
              className="flex h-12 flex-[2] items-center justify-center gap-2 rounded-2xl bg-primary-600 text-base font-bold text-white active:scale-[0.99] disabled:opacity-60">
              {saving ? <Loader2 className="h-5 w-5 animate-spin" /> : <Check className="h-5 w-5" />}
              {confirmNew ? 'نعم، صنف جديد — أضفه' : 'إضافة الحمولة'}
            </button>
          </div>
        </div>
      )}
    </Dialog>
  )
}

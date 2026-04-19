'use client'

import React, { useMemo, useState, useEffect } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { expensesApi, type ExpenseItem, type ExpenseCategory, type CreateExpensePayload } from '@/lib/api/expenses'
import {
  Wallet, AlertCircle, Plus, Layers, CircleDollarSign,
  AlertTriangle, CheckCircle, ChevronDown, ChevronUp,
} from 'lucide-react'
import { formatDate, formatNumber, cn } from '@/lib/utils'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import type { FlockStatus } from '@/types/flock'

// ── Quick-entry presets ──────────────────────────────────────────────────────

interface QuickPreset {
  code: string
  label: string
  icon: string
  color: string
  bgColor: string
  unitLabel: string
}

const QUICK_PRESETS: QuickPreset[] = [
  { code: 'bedding',      label: 'نشارة',       icon: '🪵', color: 'text-amber-700', bgColor: 'bg-amber-50 border-amber-200 hover:bg-amber-100',  unitLabel: 'كيس' },
  { code: 'farm_wash',    label: 'غسيل مدجنة',  icon: '🧹', color: 'text-sky-700',   bgColor: 'bg-sky-50 border-sky-200 hover:bg-sky-100',        unitLabel: 'مرة' },
  { code: 'disinfectant', label: 'معقم',         icon: '🧴', color: 'text-primary-700', bgColor: 'bg-primary-50 border-primary-200 hover:bg-primary-100', unitLabel: 'قطعة' },
]

// ── Constants ────────────────────────────────────────────────────────────────

const PAYMENT_BADGE: Record<string, { label: string; color: string }> = {
  paid:    { label: 'مدفوع',     color: 'bg-emerald-100 text-emerald-700' },
  partial: { label: 'جزئي',      color: 'bg-amber-100 text-amber-700' },
  unpaid:  { label: 'ذمم',       color: 'bg-red-100 text-red-700' },
  debt:    { label: 'ذمم',       color: 'bg-red-100 text-red-700' },
}

const today = new Date().toISOString().split('T')[0]

// ── Props ────────────────────────────────────────────────────────────────────

interface Props {
  flockId: number
  flockStatus: FlockStatus
}

// ── Component ────────────────────────────────────────────────────────────────

export function ExpensesTab({ flockId, flockStatus }: Props) {
  const queryClient = useQueryClient()
  const canAdd = flockStatus === 'active'

  // Active form state: null = closed, string = category code being entered
  const [activeForm, setActiveForm] = useState<string | null>(null)
  const [customMode, setCustomMode] = useState(false)
  const [expanded, setExpanded] = useState(false)

  // ── Data queries ─────────────────────────────────────────────────────────
  const { data, isLoading, isError } = useQuery({
    queryKey: ['flock-expenses', flockId],
    queryFn: () => expensesApi.list(flockId),
    staleTime: 30_000,
  })

  const { data: categoriesData } = useQuery({
    queryKey: ['expense-categories'],
    queryFn: () => expensesApi.categories(),
    staleTime: 5 * 60 * 1000,
  })

  const expenses = data?.data || []
  const categories = categoriesData?.data || []

  // Find category ID by code
  const getCategoryId = (code: string) => categories.find(c => c.code === code)?.id

  // ── Grouping logic ───────────────────────────────────────────────────────
  const { summary, recentExpenses } = useMemo(() => {
    const totalAmount = expenses.reduce((s, e) => s + e.total_amount, 0)
    const unpaidAmount = expenses.filter(e => e.payment_status !== 'paid').reduce((s, e) => s + (e.total_amount - e.paid_amount), 0)
    const recent = expenses.slice(0, expanded ? 50 : 5)
    return {
      summary: { totalAmount, unpaidAmount, count: expenses.length },
      recentExpenses: recent,
    }
  }, [expenses, expanded])

  // ── Loading & Error ──────────────────────────────────────────────────────
  if (isLoading) return (
    <div className="flex items-center justify-center py-20 text-slate-400">
      <div className="h-6 w-6 animate-spin rounded-full border-2 border-slate-200 border-t-primary-500" />
    </div>
  )

  if (isError) return (
    <div className="m-4 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">
      <AlertCircle className="h-5 w-5 shrink-0" />
      <p className="text-sm">تعذّر تحميل سجل المصروفات</p>
    </div>
  )

  const handleSuccess = () => {
    setActiveForm(null)
    setCustomMode(false)
    queryClient.invalidateQueries({ queryKey: ['flock-expenses', flockId] })
    queryClient.invalidateQueries({ queryKey: ['today-summary'] })
  }

  return (
    <div className="p-4 space-y-4">

      {/* ── Quick Entry Buttons ─────────────────────────────────────────── */}
      {canAdd && (
        <div className="space-y-3">
          <p className="text-xs font-bold text-slate-500 uppercase tracking-wider">إدخال سريع</p>
          <div className="grid grid-cols-2 gap-2.5">
            {QUICK_PRESETS.map(preset => (
              <button
                key={preset.code}
                onClick={() => {
                  setActiveForm(activeForm === preset.code ? null : preset.code)
                  setCustomMode(false)
                }}
                className={cn(
                  'flex items-center gap-2.5 rounded-xl border px-4 py-3 transition-all duration-200 active:scale-[0.97]',
                  activeForm === preset.code
                    ? 'ring-2 ring-primary-400 border-primary-300 bg-primary-50 shadow-md'
                    : preset.bgColor,
                )}
              >
                <span className="text-lg">{preset.icon}</span>
                <span className={cn('text-sm font-bold', preset.color)}>{preset.label}</span>
              </button>
            ))}
            {/* Custom expense button */}
            <button
              onClick={() => {
                setActiveForm(customMode ? null : '__custom__')
                setCustomMode(!customMode)
              }}
              className={cn(
                'flex items-center gap-2.5 rounded-xl border px-4 py-3 transition-all duration-200 active:scale-[0.97]',
                customMode
                  ? 'ring-2 ring-primary-400 border-primary-300 bg-primary-50 shadow-md'
                  : 'bg-slate-50 border-slate-200 hover:bg-slate-100',
              )}
            >
              <Plus className="h-5 w-5 text-slate-500" />
              <span className="text-sm font-bold text-slate-600">مصروف مخصص</span>
            </button>
          </div>
        </div>
      )}

      {/* ── Quick Entry Form ────────────────────────────────────────────── */}
      {canAdd && activeForm && !customMode && (
        <QuickEntryForm
          key={activeForm}
          preset={QUICK_PRESETS.find(p => p.code === activeForm)!}
          categoryId={getCategoryId(activeForm)}
          flockId={flockId}
          onSuccess={handleSuccess}
          onCancel={() => setActiveForm(null)}
        />
      )}

      {/* ── Custom Expense Form ─────────────────────────────────────────── */}
      {canAdd && customMode && (
        <CustomExpenseForm
          categories={categories}
          flockId={flockId}
          onSuccess={handleSuccess}
          onCancel={() => { setActiveForm(null); setCustomMode(false) }}
        />
      )}

      {/* ── Summary strip ───────────────────────────────────────────────── */}
      {expenses.length > 0 && (
        <div className="grid grid-cols-2 gap-2.5">
          <div className="rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm">
            <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">إجمالي المصروفات</p>
            <p className="mt-1 text-xl font-black text-slate-900 tabular-nums">
              {formatNumber(summary.totalAmount)} <span className="text-xs font-bold text-slate-400">$</span>
            </p>
            <p className="text-[10px] text-slate-400 mt-0.5">{summary.count} حركة</p>
          </div>
          {summary.unpaidAmount > 0 && (
            <div className="rounded-xl border border-red-200 bg-red-50 p-3.5 shadow-sm">
              <p className="text-[10px] font-bold text-red-500 uppercase tracking-wider flex items-center gap-1">
                <AlertTriangle className="h-3 w-3" /> ذمم غير مسددة
              </p>
              <p className="mt-1 text-xl font-black text-red-700 tabular-nums">
                {formatNumber(summary.unpaidAmount)} <span className="text-xs font-bold text-red-400">$</span>
              </p>
            </div>
          )}
        </div>
      )}

      {/* ── Expense list ────────────────────────────────────────────────── */}
      {expenses.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-16 text-center text-slate-400">
          <Wallet className="mb-3 h-10 w-10 opacity-30" />
          <p className="text-base font-medium text-slate-600">لا توجد مصروفات مسجلة لهذا الفوج</p>
          {canAdd && <p className="mt-1 text-sm">استخدم أزرار الإدخال السريع لإضافة مصروف</p>}
        </div>
      ) : (
        <div className="space-y-2">
          {recentExpenses.map((exp) => (
            <ExpenseRow key={exp.id} expense={exp} />
          ))}

          {/* Show more/less toggle */}
          {expenses.length > 5 && (
            <button
              onClick={() => setExpanded(p => !p)}
              className="flex w-full items-center justify-center gap-1 rounded-xl border border-slate-200 bg-white py-2.5 text-[11px] font-bold text-slate-400 transition-colors hover:bg-slate-50 active:bg-slate-100"
            >
              {expanded ? (
                <><ChevronUp className="h-3.5 w-3.5" /> إخفاء</>
              ) : (
                <><ChevronDown className="h-3.5 w-3.5" /> عرض الكل ({expenses.length})</>
              )}
            </button>
          )}
        </div>
      )}
    </div>
  )
}

// ── Quick Entry Form ─────────────────────────────────────────────────────────

function QuickEntryForm({
  preset,
  categoryId,
  flockId,
  onSuccess,
  onCancel,
}: {
  preset: QuickPreset
  categoryId: number | undefined
  flockId: number
  onSuccess: () => void
  onCancel: () => void
}) {
  const [quantity, setQuantity] = useState('')
  const [unitPrice, setUnitPrice] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const qty = parseFloat(quantity)
  const price = parseFloat(unitPrice)
  const total = !isNaN(qty) && !isNaN(price) ? qty * price : 0
  const hasPrice = !isNaN(price) && price > 0

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!quantity || isNaN(qty) || qty <= 0) {
      setError('يرجى إدخال العدد')
      return
    }
    if (!categoryId) {
      setError('لم يتم العثور على الفئة. يرجى تحديث الصفحة.')
      return
    }

    setSaving(true)
    setError(null)
    try {
      const payload: CreateExpensePayload = {
        expense_category_id: categoryId,
        flock_id: flockId,
        entry_date: today,
        quantity: qty,
        unit_price: hasPrice ? price : null,
        total_amount: hasPrice ? total : 0,
        paid_amount: hasPrice ? total : 0,
        payment_status: hasPrice ? 'paid' : 'unpaid',
        description: preset.label,
      }
      await expensesApi.create(payload)
      onSuccess()
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const first = axiosErr?.response?.data?.errors
        ? Object.values(axiosErr.response.data.errors)[0]?.[0]
        : null
      setError(first ?? axiosErr?.response?.data?.message ?? 'حدث خطأ غير متوقع')
    } finally {
      setSaving(false)
    }
  }

  return (
    <form
      onSubmit={handleSubmit}
      className="rounded-xl border-2 border-primary-200 bg-gradient-to-b from-primary-50/60 to-white p-4 space-y-3 animate-in slide-in-from-top-2 duration-200"
      noValidate
    >
      <div className="flex items-center gap-2.5 mb-1">
        <span className="text-lg">{preset.icon}</span>
        <p className="text-sm font-bold text-slate-800">{preset.label}</p>
      </div>

      <div className="grid grid-cols-2 gap-3">
        <Input
          id={`quick_qty_${preset.code}`}
          label={`العدد (${preset.unitLabel})`}
          type="number"
          step="1"
          min={1}
          placeholder="مثال: 5"
          value={quantity}
          onChange={e => setQuantity(e.target.value)}
          required
          autoFocus
        />
        <Input
          id={`quick_price_${preset.code}`}
          label="سعر الوحدة ($)"
          type="number"
          step="0.01"
          min={0}
          placeholder="0.00"
          value={unitPrice}
          onChange={e => setUnitPrice(e.target.value)}
        />
      </div>

      <p className="text-[10px] font-bold text-red-600 px-1 -mt-1">
        إذا تم ترك السعر فارغاً، سوف يتم إضافة المصروف إلى الذمم والمراجعة
      </p>

      {/* Calculated total or debt warning */}
      <div className="rounded-lg bg-white px-3 py-2 border border-slate-100">
        {hasPrice ? (
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold text-slate-500">الإجمالي</span>
            <span className="text-base font-black text-primary-700 tabular-nums">
              {formatNumber(total)} <span className="text-[10px]">$</span>
            </span>
          </div>
        ) : (
          <div className="flex items-center gap-2 text-xs">
            <AlertTriangle className="h-3.5 w-3.5 text-amber-500 shrink-0" />
            <span className="font-medium text-amber-700">
              بدون سعر — سيتم إضافة الكمية إلى الذمم والمراجعة لاحقاً
            </span>
          </div>
        )}
      </div>

      {error && (
        <div className="flex items-center gap-2 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs font-bold text-red-700">
          <AlertCircle className="h-3.5 w-3.5 shrink-0" />{error}
        </div>
      )}

      <div className="flex gap-2 justify-end">
        <Button type="button" variant="outline" size="sm" onClick={onCancel}>
          إلغاء
        </Button>
        <Button type="submit" size="sm" loading={saving}>
          {hasPrice ? 'تسجيل' : 'تسجيل كذمم'}
        </Button>
      </div>
    </form>
  )
}

// ── Custom Expense Form ──────────────────────────────────────────────────────

function CustomExpenseForm({
  categories,
  flockId,
  onSuccess,
  onCancel,
}: {
  categories: ExpenseCategory[]
  flockId: number
  onSuccess: () => void
  onCancel: () => void
}) {
  const [categoryId, setCategoryId] = useState('')
  const [description, setDescription] = useState('')
  const [quantity, setQuantity] = useState('')
  const [unitPrice, setUnitPrice] = useState('')
  const [entryDate, setEntryDate] = useState(today)
  const [notes, setNotes] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // default to "other" category if available
  useEffect(() => {
    const otherCat = categories.find(c => c.code === 'other')
    if (otherCat && !categoryId) setCategoryId(String(otherCat.id))
  }, [categories, categoryId])

  const qty = parseFloat(quantity)
  const price = parseFloat(unitPrice)
  const total = !isNaN(qty) && !isNaN(price) ? qty * price : 0
  const hasPrice = !isNaN(price) && price > 0

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!quantity || isNaN(qty) || qty <= 0) {
      setError('يرجى إدخال العدد')
      return
    }
    if (!description.trim()) {
      setError('يرجى إدخال وصف المصروف')
      return
    }
    if (!categoryId) {
      setError('يرجى اختيار الفئة')
      return
    }

    setSaving(true)
    setError(null)
    try {
      const payload: CreateExpensePayload = {
        expense_category_id: Number(categoryId),
        flock_id: flockId,
        entry_date: entryDate,
        quantity: qty,
        unit_price: hasPrice ? price : null,
        total_amount: hasPrice ? total : 0,
        paid_amount: hasPrice ? total : 0,
        payment_status: hasPrice ? 'paid' : 'unpaid',
        description: description.trim(),
        notes: notes.trim() || undefined,
      }
      await expensesApi.create(payload)
      onSuccess()
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const first = axiosErr?.response?.data?.errors
        ? Object.values(axiosErr.response.data.errors)[0]?.[0]
        : null
      setError(first ?? axiosErr?.response?.data?.message ?? 'حدث خطأ غير متوقع')
    } finally {
      setSaving(false)
    }
  }

  return (
    <form
      onSubmit={handleSubmit}
      className="rounded-xl border-2 border-slate-300 bg-gradient-to-b from-slate-50 to-white p-4 space-y-3 animate-in slide-in-from-top-2 duration-200"
      noValidate
    >
      <div className="flex items-center gap-2.5 mb-1">
        <Plus className="h-5 w-5 text-slate-500" />
        <p className="text-sm font-bold text-slate-800">مصروف مخصص</p>
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div className="space-y-1">
          <label className="text-sm font-medium text-slate-700">
            الفئة <span className="text-red-400">*</span>
          </label>
          <select
            value={categoryId}
            onChange={e => setCategoryId(e.target.value)}
            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
          >
            <option value="">-- اختر الفئة --</option>
            {categories.map(c => (
              <option key={c.id} value={c.id}>{c.name}</option>
            ))}
          </select>
        </div>

        <Input
          id="custom_desc"
          label="وصف المصروف"
          type="text"
          placeholder="مثال: إصلاح سقف"
          value={description}
          onChange={e => setDescription(e.target.value)}
          required
        />
      </div>

      <div className="grid grid-cols-2 gap-3">
        <Input
          id="custom_qty"
          label="العدد"
          type="number"
          step="1"
          min={1}
          placeholder="مثال: 1"
          value={quantity}
          onChange={e => setQuantity(e.target.value)}
          required
        />
        <Input
          id="custom_price"
          label="سعر الوحدة ($)"
          type="number"
          step="0.01"
          min={0}
          placeholder="0.00"
          value={unitPrice}
          onChange={e => setUnitPrice(e.target.value)}
        />
      </div>

      <p className="text-[10px] font-bold text-red-600 px-1 -mt-1">
        إذا تم ترك السعر فارغاً، سوف يتم إضافة المصروف إلى الذمم والمراجعة
      </p>

      {/* Calculated total or debt warning */}
      <div className="rounded-lg bg-white px-3 py-2 border border-slate-100">
        {hasPrice ? (
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold text-slate-500">الإجمالي</span>
            <span className="text-base font-black text-primary-700 tabular-nums">
              {formatNumber(total)} <span className="text-[10px]">$</span>
            </span>
          </div>
        ) : (
          <div className="flex items-center gap-2 text-xs">
            <AlertTriangle className="h-3.5 w-3.5 text-amber-500 shrink-0" />
            <span className="font-medium text-amber-700">
              بدون سعر — سيتم إضافة الكمية إلى الذمم والمراجعة لاحقاً
            </span>
          </div>
        )}
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Input
          id="custom_date"
          label="التاريخ"
          type="date"
          value={entryDate}
          onChange={e => setEntryDate(e.target.value)}
          max={today}
        />
        <div className="space-y-1">
          <label htmlFor="custom_notes" className="text-sm font-medium text-slate-700">
            ملاحظات <span className="text-xs text-slate-400">(اختياري)</span>
          </label>
          <input
            id="custom_notes"
            type="text"
            placeholder="اختياري"
            value={notes}
            onChange={e => setNotes(e.target.value)}
            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
          />
        </div>
      </div>

      {error && (
        <div className="flex items-center gap-2 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs font-bold text-red-700">
          <AlertCircle className="h-3.5 w-3.5 shrink-0" />{error}
        </div>
      )}

      <div className="flex gap-2 justify-end">
        <Button type="button" variant="outline" size="sm" onClick={onCancel}>
          إلغاء
        </Button>
        <Button type="submit" size="sm" loading={saving}>
          {hasPrice ? 'تسجيل المصروف' : 'تسجيل كذمم'}
        </Button>
      </div>
    </form>
  )
}

// ── Expense Row ──────────────────────────────────────────────────────────────

function ExpenseRow({ expense }: { expense: ExpenseItem }) {
  const statusObj = PAYMENT_BADGE[expense.payment_status] ?? PAYMENT_BADGE.paid
  const isDebt = expense.payment_status === 'unpaid' || expense.payment_status === 'debt'
  const name = expense.description || expense.category_name || expense.expense_type || 'مصروف'

  return (
    <div
      className={cn(
        'flex items-center justify-between gap-3 rounded-xl border bg-white px-4 py-3 shadow-sm transition-all duration-150',
        isDebt ? 'border-red-200' : 'border-slate-200',
      )}
      style={{ boxShadow: 'var(--shadow-card)' }}
    >
      <div className="flex items-center gap-3 min-w-0">
        <div className={cn(
          'w-9 h-9 rounded-xl flex items-center justify-center shrink-0',
          isDebt ? 'bg-red-50' : 'bg-sky-50',
        )}>
          {isDebt
            ? <AlertTriangle className="w-4 h-4 text-red-500" />
            : <Wallet className="w-4 h-4 text-sky-600" />}
        </div>
        <div className="min-w-0">
          <p className="text-sm font-bold text-slate-900 truncate">{name}</p>
          <div className="flex flex-wrap items-center gap-2 mt-0.5">
            <span className="text-[10px] font-medium text-slate-400">{formatDate(expense.entry_date)}</span>
            {expense.quantity && (
              <span className="text-[10px] font-medium text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded-full tabular-nums">
                {formatNumber(expense.quantity)} وحدة
              </span>
            )}
            <span className={cn('px-1.5 py-0.5 rounded-full text-[10px] font-bold', statusObj.color)}>
              {statusObj.label}
            </span>
          </div>
        </div>
      </div>
      <div className="text-left shrink-0">
        {expense.total_amount > 0 ? (
          <p className="text-base font-black text-slate-900 tabular-nums">
            {formatNumber(expense.total_amount)} <span className="text-[10px] text-slate-400">$</span>
          </p>
        ) : (
          <p className="text-xs font-bold text-red-500">ذمم</p>
        )}
      </div>
    </div>
  )
}


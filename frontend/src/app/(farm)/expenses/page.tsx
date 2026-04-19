'use client'

import { useState } from 'react'
import { Receipt, AlertCircle, Plus, X, ClipboardList } from 'lucide-react'
import Link from 'next/link'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import { expensesApi, type ExpenseItem, type ExpenseCategory } from '@/lib/api/expenses'
import { flocksApi } from '@/lib/api/flocks'
import { useFarmStore } from '@/stores/farm.store'
import { useIsReadOnly } from '@/lib/roles'
import { formatDate, formatNumber } from '@/lib/utils'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'

// ── Schema ────────────────────────────────────────────────────────────────────
const schema = z.object({
  expense_category_id: z.preprocess(
    (v) => Number(v),
    z.number({ invalid_type_error: 'التصنيف مطلوب' }).min(1, 'التصنيف مطلوب')
  ),
  entry_date:   z.string().min(1, 'التاريخ مطلوب').regex(/^\d{4}-\d{2}-\d{2}$/),
  total_amount: z.preprocess(
    (v) => Number(v),
    z.number({ invalid_type_error: 'المبلغ يجب أن يكون رقماً' }).min(0.01, 'المبلغ يجب أن يكون أكبر من صفر')
  ),
  paid_amount: z.preprocess(
    (v) => v === '' || v === undefined || v === null ? undefined : Number(v),
    z.number().min(0).optional()
  ),
  payment_status: z.enum(['paid', 'partial', 'unpaid']).default('paid'),
  description:  z.string().max(255).optional().or(z.literal('')),
  notes:        z.string().max(5000).optional().or(z.literal('')),
})
type FormData = z.infer<typeof schema>

const PAYMENT_STATUS_LABEL: Record<string, { label: string; color: string }> = {
  paid:    { label: 'مدفوع',     color: 'bg-emerald-100 text-emerald-700' },
  partial: { label: 'جزئي',      color: 'bg-emerald-100 text-emerald-700' },
  unpaid:  { label: 'ذمم',       color: 'bg-red-100 text-red-700' },
}

// ── Page ──────────────────────────────────────────────────────────────────────
export default function ExpensesPage() {
  const { currentFarm } = useFarmStore()
  const queryClient = useQueryClient()
  const isReadOnly = useIsReadOnly()
  const [showForm, setShowForm] = useState(false)

  // Always fetch flocks to get the reliable active flock ID
  const { data: flocksData } = useQuery({
    queryKey: ['flocks', currentFarm?.id],
    queryFn: () => flocksApi.list().then((r) => r.data),
    enabled: !!currentFarm,
    staleTime: 60_000,
  })
  const activeFlockId = flocksData?.find((f) => f.status === 'active')?.id

  const { data, isLoading: loading, isError } = useQuery({
    queryKey: ['expenses', currentFarm?.id, activeFlockId],
    queryFn: () => expensesApi.list(activeFlockId),
    enabled: !!currentFarm && activeFlockId !== undefined,
    staleTime: 30_000,
  })

  const { data: catsData } = useQuery({
    queryKey: ['expense-categories'],
    queryFn: () => expensesApi.categories(),
    staleTime: 5 * 60_000,
    enabled: showForm,
  })

  const categories: ExpenseCategory[] = catsData?.data ?? []
  const expenses: ExpenseItem[] = data?.data ?? []
  const totalAmount = data?.meta.total_amount ?? 0
  const error = isError ? 'تعذّر تحميل المصروفات' : null

  // ── Form ─────────────────────────────────────────────────────────────────────
  const {
    register,
    handleSubmit,
    reset,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      entry_date:     new Date().toISOString().split('T')[0],
      payment_status: 'paid',
    },
  })

  const paymentStatus = watch('payment_status')
  const totalAmountVal = watch('total_amount')
  const paidAmountVal  = watch('paid_amount')
  const showPaidHint   =
    paymentStatus === 'unpaid' ||
    (paymentStatus === 'partial' && (!paidAmountVal || paidAmountVal <= 0 || paidAmountVal !== Number(totalAmountVal)))

  const handleCancel = () => {
    reset({ entry_date: new Date().toISOString().split('T')[0], payment_status: 'paid' })
    setShowForm(false)
  }

  const onSubmit = async (data: FormData) => {
    try {
      await expensesApi.create({
        expense_category_id: data.expense_category_id,
        flock_id:            activeFlockId ?? undefined,
        entry_date:          data.entry_date,
        quantity:            1,
        total_amount:        data.total_amount,
        paid_amount:         data.payment_status === 'partial' ? (data.paid_amount ?? 0) : undefined,
        payment_status:      data.payment_status,
        description:         data.description || undefined,
        notes:               data.notes || undefined,
      })
      toast.success('تم تسجيل المصروف بنجاح')
      handleCancel()
      queryClient.invalidateQueries({ queryKey: ['expenses', currentFarm?.id, activeFlockId] })
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { message?: string } } }
      toast.error(axiosErr?.response?.data?.message ?? 'حدث خطأ غير متوقع')
    }
  }

  return (
    <div className="space-y-5">

      {/* Toolbar */}
      <div className="flex items-center justify-between">
        {!loading && !error && expenses.length > 0 ? (
          <div className="rounded-2xl border border-slate-200/60 bg-white px-5 py-3" style={{ boxShadow: 'var(--shadow-card)' }}>
            <p className="text-[11px] font-semibold text-slate-500">الإجمالي</p>
            <p className="text-lg font-bold text-slate-900">{formatNumber(totalAmount)} USD</p>
          </div>
        ) : <span />}

        {!showForm && !isReadOnly && (
          <Button size="sm" onClick={() => setShowForm(true)}>
            <Plus className="me-1.5 h-4 w-4" />
            إضافة مصروف
          </Button>
        )}
      </div>

      {/* Inline creation form */}
      {showForm && !isReadOnly && (
        <form
          onSubmit={handleSubmit(onSubmit)}
          className="rounded-2xl border border-primary-200 bg-primary-50/40 p-5 space-y-4"
          noValidate
        >
          <div className="flex items-center justify-between">
            <p className="text-sm font-bold text-slate-800">تسجيل مصروف جديد</p>
            <button type="button" onClick={handleCancel} className="text-slate-400 hover:text-slate-600">
              <X className="h-4 w-4" />
            </button>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {/* Category */}
            <div className="space-y-1">
              <label className="text-sm font-medium text-slate-700">
                التصنيف <span className="text-red-500">*</span>
              </label>
              <select
                {...register('expense_category_id')}
                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
              >
                <option value="">اختر التصنيف</option>
                {categories.map((cat) => (
                  <option key={cat.id} value={cat.id}>{cat.name}</option>
                ))}
              </select>
              {errors.expense_category_id && (
                <p className="text-xs text-red-600">{errors.expense_category_id.message}</p>
              )}
            </div>

            {/* Entry date */}
            <Input
              {...register('entry_date')}
              id="exp_entry_date"
              label="تاريخ المصروف"
              type="date"
              max={new Date().toISOString().split('T')[0]}
              error={errors.entry_date?.message}
              required
            />
          </div>

          {(paymentStatus === 'partial' || paymentStatus === 'unpaid') && (
            <p className="text-xs text-slate-500 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2">
              {paymentStatus === 'partial'
                ? 'أدخل القيمة الإجمالية للمصروف في "المبلغ الكامل"، ثم أدخل ما تم دفعه فعلياً في "المبلغ المدفوع".'
                : 'أدخل القيمة الإجمالية للمصروف — سيُسجَّل كذمة كاملة حتى يتم تسديدها لاحقاً.'}
            </p>
          )}

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {/* Amount */}
            <Input
              {...register('total_amount')}
              id="exp_total_amount"
              label={paymentStatus === 'partial' ? 'المبلغ الكامل (USD)' : 'المبلغ (USD)'}
              type="number"
              step="0.01"
              min="0.01"
              placeholder="0.00"
              error={errors.total_amount?.message}
              required
            />

            {/* Payment status */}
            <div className="space-y-1">
              <label className="text-sm font-medium text-slate-700">حالة الدفع</label>
              <select
                {...register('payment_status')}
                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
              >
                <option value="paid">مدفوع بالكامل</option>
                <option value="partial">مدفوع جزئياً</option>
                <option value="unpaid">غير مدفوع (ذمم)</option>
              </select>
            </div>
          </div>

          {/* Paid amount — only when partial */}
          {paymentStatus === 'partial' && (
            <Input
              {...register('paid_amount')}
              id="exp_paid_amount"
              label="المبلغ المدفوع (USD)"
              type="number"
              step="0.01"
              min="0"
              placeholder="0.00"
              error={errors.paid_amount?.message}
            />
          )}

          {/* Review queue hint — shown for unpaid, or partial with amount != total */}
          {showPaidHint && (
            <p className="flex items-center gap-1.5 rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2 text-xs text-emerald-700">
              <ClipboardList className="h-3.5 w-3.5 shrink-0" />
              سيُضاف هذا المصروف تلقائياً إلى{' '}
              <Link href="/accounting?tab=review" className="underline font-semibold hover:text-emerald-900">
                قائمة الذمم والمراجعة
              </Link>
            </p>
          )}

          {/* Description */}
          <Input
            {...register('description')}
            id="exp_description"
            label="الوصف"
            type="text"
            placeholder="مثال: شراء علف أسبوعي"
            error={errors.description?.message}
          />

          <div className="flex gap-2 justify-end">
            <Button type="button" variant="outline" size="sm" onClick={handleCancel}>إلغاء</Button>
            <Button type="submit" size="sm" loading={isSubmitting}>تسجيل</Button>
          </div>
        </form>
      )}

      {/* Error */}
      {error && (
        <div className="flex items-center gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 text-red-700">
          <AlertCircle className="h-5 w-5 shrink-0" />
          <p className="text-sm font-medium">{error}</p>
        </div>
      )}

      {/* Loading skeleton */}
      {loading && <div className="h-64 animate-pulse rounded-2xl bg-slate-200/60" />}

      {/* No active flock */}
      {!loading && !activeFlockId && (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white py-16 text-center" style={{ boxShadow: 'var(--shadow-card)' }}>
          <Receipt className="mb-4 h-12 w-12 text-slate-300" />
          <h3 className="text-base font-bold text-slate-700">لا يوجد فوج نشط</h3>
          <p className="mt-1 text-sm text-slate-500 font-medium">تظهر المصروفات فقط عند وجود فوج نشط</p>
        </div>
      )}

      {/* Empty state */}
      {!loading && !error && !!activeFlockId && expenses.length === 0 && (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white py-16 text-center" style={{ boxShadow: 'var(--shadow-card)' }}>
          <Receipt className="mb-4 h-12 w-12 text-slate-300" />
          <h3 className="text-base font-bold text-slate-700">لا توجد مصروفات مسجّلة</h3>
          <p className="mt-1 text-sm text-slate-500 font-medium">اضغط «إضافة مصروف» لتسجيل أول مصروف</p>
        </div>
      )}

      {/* Table */}
      {!loading && !error && expenses.length > 0 && (
        <div className="overflow-hidden rounded-2xl border border-slate-200/60 bg-white" style={{ boxShadow: 'var(--shadow-card)' }}>
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-100 bg-slate-50 text-right text-xs font-semibold text-slate-500">
                <th className="px-5 py-3">التاريخ</th>
                <th className="px-5 py-3">التصنيف</th>
                <th className="px-5 py-3">الفوج</th>
                <th className="px-5 py-3">المبلغ</th>
                <th className="px-5 py-3">حالة الدفع</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {expenses.map((expense) => {
                const status = PAYMENT_STATUS_LABEL[expense.payment_status]
                return (
                  <tr key={expense.id} className="transition-colors duration-200 hover:bg-slate-50">
                    <td className="px-5 py-3 text-slate-500">{formatDate(expense.entry_date)}</td>
                    <td className="px-5 py-3 font-medium text-slate-800">
                      {expense.category_name ?? expense.expense_type ?? '—'}
                    </td>
                    <td className="px-5 py-3 text-slate-500">{expense.flock_name ?? '—'}</td>
                    <td className="px-5 py-3 tabular-nums font-semibold text-slate-800">
                      {formatNumber(expense.total_amount)} USD
                    </td>
                    <td className="px-5 py-3">
                      {status && (
                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${status.color}`}>
                          {status.label}
                        </span>
                      )}
                    </td>
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


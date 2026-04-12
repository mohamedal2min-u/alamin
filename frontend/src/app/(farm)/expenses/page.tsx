'use client'

import { Receipt, AlertCircle } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { expensesApi, type ExpenseItem } from '@/lib/api/expenses'
import { useFarmStore } from '@/stores/farm.store'
import { formatDate, formatNumber } from '@/lib/utils'

const EXPENSE_TYPE_LABEL: Record<string, string> = {
  water:   'مياه',
  bedding: 'فرشة',
  other:   'أخرى',
}

const PAYMENT_STATUS_LABEL: Record<string, { label: string; color: string }> = {
  paid:        { label: 'مدفوع',    color: 'bg-green-100 text-green-700' },
  partial:     { label: 'جزئي',     color: 'bg-amber-100 text-amber-700' },
  unpaid:      { label: 'غير مدفوع', color: 'bg-red-100 text-red-700' },
}

export default function ExpensesPage() {
  const { currentFarm } = useFarmStore()

  const {
    data,
    isLoading: loading,
    isError,
  } = useQuery({
    queryKey: ['expenses', currentFarm?.id],
    queryFn: () => expensesApi.list(),
    enabled: !!currentFarm,
    staleTime: 30_000,
  })

  const expenses: ExpenseItem[] = data?.data ?? []
  const totalAmount = data?.meta.total_amount ?? 0
  const error = isError ? 'تعذّر تحميل المصروفات' : null

  return (
    <div className="space-y-5">
      {/* Header — total only (page title in global header) */}
      {!loading && !error && expenses.length > 0 && (
        <div className="flex items-center justify-end">
          <div className="rounded-2xl border border-slate-200/60 bg-white px-5 py-3 text-left" style={{ boxShadow: 'var(--shadow-card)' }}>
            <p className="text-[11px] font-semibold text-slate-500">الإجمالي</p>
            <p className="text-lg font-bold text-slate-900">{formatNumber(totalAmount)} USD</p>
          </div>
        </div>
      )}

      {error && (
        <div className="flex items-center gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 text-red-700">
          <AlertCircle className="h-5 w-5 shrink-0" />
          <p className="text-sm font-medium">{error}</p>
        </div>
      )}

      {loading && (
        <div className="h-64 animate-pulse rounded-2xl bg-slate-200/60" />
      )}

      {!loading && !error && expenses.length === 0 && (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white py-16 text-center" style={{ boxShadow: 'var(--shadow-card)' }}>
          <Receipt className="mb-4 h-12 w-12 text-slate-300" />
          <h3 className="text-base font-bold text-slate-700">لا توجد مصروفات مسجّلة</h3>
          <p className="mt-1 text-sm text-slate-500 font-medium">أضف مصروفات من لوحة التحكم أو صفحة الفوج</p>
        </div>
      )}

      {!loading && !error && expenses.length > 0 && (
        <div className="overflow-hidden rounded-2xl border border-slate-200/60 bg-white" style={{ boxShadow: 'var(--shadow-card)' }}>
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-100 bg-slate-50 text-right text-xs font-semibold text-slate-500">
                <th className="px-5 py-3">التاريخ</th>
                <th className="px-5 py-3">النوع</th>
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
                      {EXPENSE_TYPE_LABEL[expense.expense_type] ?? expense.expense_type}
                      {expense.category_name && (
                        <span className="ms-2 text-xs text-slate-400">{expense.category_name}</span>
                      )}
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

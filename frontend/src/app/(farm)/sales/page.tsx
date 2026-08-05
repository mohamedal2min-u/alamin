'use client'

import { useState } from 'react'
import { ShoppingCart, AlertCircle, Bird } from 'lucide-react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { salesApi } from '@/lib/api/sales'
import { flocksApi } from '@/lib/api/flocks'
import { useFarmStore } from '@/stores/farm.store'
import { PaymentStatusBadge } from '@/components/sales/PaymentStatusBadge'
import { UpdatePaymentDialog } from '@/components/sales/UpdatePaymentDialog'
import { formatDate, formatNumber, formatCurrency } from '@/lib/utils'
import type { Sale } from '@/types/sale'
import type { Flock } from '@/types/flock'

export default function SalesPage() {
  const { currentFarm } = useFarmStore()
  const queryClient = useQueryClient()
  const [paymentSale, setPaymentSale] = useState<Sale | null>(null)

  const { data: flocks = [] } = useQuery<Flock[]>({
    queryKey: ['flocks', currentFarm?.id],
    queryFn: () => flocksApi.list().then((r) => r.data),
    enabled: !!currentFarm,
    staleTime: 60_000,
  })

  // الفوج النشط → آخر فوج مُغلق → لا شيء
  const targetFlock =
    flocks.find((f) => f.status === 'active') ??
    flocks.filter((f) => f.status === 'closed').sort((a, b) => (b.id ?? 0) - (a.id ?? 0))[0] ??
    null

  const { data, isLoading: loading, isError } = useQuery({
    queryKey: ['sales', currentFarm?.id, targetFlock?.id],
    queryFn: () => salesApi.listAll(targetFlock?.id).then((res) => res.data),
    enabled: !!currentFarm && !!targetFlock,
    staleTime: 60_000,
    gcTime: 10 * 60 * 1000,
  })

  const sales = data ?? []
  const error = isError ? 'تعذّر تحميل سجلات المبيعات' : null

  const handlePaymentUpdated = (updated: Sale) => {
    queryClient.setQueryData(['sales', currentFarm?.id], (old: Sale[] | undefined) =>
      old ? old.map((s) => (s.id === updated.id ? updated : s)) : []
    )
    setPaymentSale(null)
  }

  // ── Aggregates ──────────────────────────────────────────────────────────────
  const totalNet       = sales.reduce((s, sale) => s + Number(sale.net_amount), 0)
  const totalReceived  = sales.reduce((s, sale) => s + Number(sale.received_amount), 0)
  const totalRemaining = sales.reduce((s, sale) => s + Number(sale.remaining_amount), 0)

  return (
    <div className="space-y-5">

      {/* Flock indicator */}
      {targetFlock && (
        <div className="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-600">
          <Bird className="h-4 w-4 text-slate-400 shrink-0" />
          <span>المبيعات تخص الفوج:</span>
          <span className="font-semibold text-slate-800">{targetFlock.name}</span>
          <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${targetFlock.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>
            {targetFlock.status === 'active' ? 'نشط' : 'مغلق'}
          </span>
        </div>
      )}

      {/* No flock at all */}
      {!loading && !targetFlock && (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white py-20 text-center">
          <Bird className="mb-4 h-12 w-12 text-slate-300" />
          <h3 className="text-base font-bold text-slate-700">لا يوجد فوج بعد</h3>
          <p className="mt-1 text-sm text-slate-500">أنشئ فوجاً أولاً لعرض المبيعات</p>
        </div>
      )}

      {/* Error */}
      {error && (
        <div className="flex items-center gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 text-red-700">
          <AlertCircle className="h-5 w-5 shrink-0" />
          <p className="text-sm font-medium">{error}</p>
        </div>
      )}

      {/* Loading */}
      {loading && (
        <div className="h-64 animate-pulse rounded-2xl bg-slate-200/60" />
      )}

      {/* Summary cards */}
      {!loading && !error && sales.length > 0 && (
        <div className="grid grid-cols-3 gap-3">
          <SummaryCard label="إجمالي الصافي"   value={formatCurrency(totalNet)} />
          <SummaryCard label="إجمالي المستلم"  value={formatCurrency(totalReceived)} accent="emerald" />
          <SummaryCard label="إجمالي المتبقي"  value={formatCurrency(totalRemaining)} accent={totalRemaining > 0 ? 'red' : 'slate'} />
        </div>
      )}

      {/* Empty */}
      {!loading && !error && sales.length === 0 && (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white py-20 text-center" style={{ boxShadow: 'var(--shadow-card)' }}>
          <ShoppingCart className="mb-4 h-12 w-12 text-slate-300" />
          <h3 className="text-base font-bold text-slate-700">لا توجد مبيعات مسجّلة</h3>
          <p className="mt-1 text-sm font-medium text-slate-500">
            انتقل إلى صفحة الفوج لتسجيل بيعة جديدة
          </p>
        </div>
      )}

      {/* Sales: mobile cards + desktop table */}
      {!loading && !error && sales.length > 0 && (
        <>
          {/* Mobile cards */}
          <div className="space-y-2 sm:hidden">
            {sales.map((sale) => {
              const totalBirds  = sale.items.reduce((s, i) => s + i.birds_count, 0)
              const totalWeight = sale.items.reduce((s, i) => s + Number(i.total_weight_kg), 0)
              return (
                <div key={sale.id} className="rounded-2xl border border-slate-200/60 bg-white p-3.5" style={{ boxShadow: 'var(--shadow-card)' }}>
                  <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                      <p className="truncate text-sm font-bold text-slate-800">{sale.buyer_name ?? '—'}</p>
                      <p className="mt-0.5 text-[10px] text-slate-400">
                        {formatDate(sale.sale_date)} · {formatNumber(totalBirds)} طائر · {formatNumber(Number(totalWeight.toFixed(1)))} كغ
                      </p>
                    </div>
                    <PaymentStatusBadge status={sale.payment_status} />
                  </div>

                  <div className="mt-3 grid grid-cols-3 gap-2 text-center">
                    <div>
                      <p className="text-[9px] font-extrabold text-slate-400">الصافي</p>
                      <p className="text-[13px] font-black tabular-nums text-slate-900">{formatCurrency(Number(sale.net_amount))}</p>
                    </div>
                    <div>
                      <p className="text-[9px] font-extrabold text-slate-400">المستلم</p>
                      <p className="text-[13px] font-black tabular-nums text-primary-700">{formatCurrency(Number(sale.received_amount))}</p>
                    </div>
                    <div>
                      <p className="text-[9px] font-extrabold text-slate-400">المتبقي</p>
                      <p className={`text-[13px] font-black tabular-nums ${sale.remaining_amount > 0 ? 'text-red-600' : 'text-slate-400'}`}>
                        {formatCurrency(Number(sale.remaining_amount))}
                      </p>
                    </div>
                  </div>

                  {sale.payment_status !== 'paid' && (
                    <button
                      onClick={() => setPaymentSale(sale)}
                      className="mt-3 w-full rounded-xl bg-primary-50 py-2 text-xs font-bold text-primary-600 transition-colors hover:bg-primary-100"
                    >
                      تحديث الدفع
                    </button>
                  )}
                </div>
              )
            })}
          </div>

          {/* Desktop table */}
          <div className="hidden overflow-x-auto rounded-2xl border border-slate-200/60 bg-white sm:block" style={{ boxShadow: 'var(--shadow-card)' }}>
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-100 bg-slate-50 text-right text-xs font-semibold text-slate-500">
                  <th className="px-5 py-3">التاريخ</th>
                  <th className="px-5 py-3">المشتري</th>
                  <th className="px-5 py-3">الطيور</th>
                  <th className="px-5 py-3">الوزن (كغ)</th>
                  <th className="px-5 py-3">الصافي</th>
                  <th className="px-5 py-3">المستلم</th>
                  <th className="px-5 py-3">المتبقي</th>
                  <th className="px-5 py-3">الحالة</th>
                  <th className="px-5 py-3" />
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {sales.map((sale) => {
                  const totalBirds  = sale.items.reduce((s, i) => s + i.birds_count, 0)
                  const totalWeight = sale.items.reduce((s, i) => s + Number(i.total_weight_kg), 0)

                  return (
                    <tr key={sale.id} className="transition-colors hover:bg-slate-50">
                      <td className="px-5 py-3 text-slate-600">{formatDate(sale.sale_date)}</td>
                      <td className="px-5 py-3 font-medium text-slate-800">{sale.buyer_name ?? '—'}</td>
                      <td className="px-5 py-3 tabular-nums text-slate-700">{formatNumber(totalBirds)}</td>
                      <td className="px-5 py-3 tabular-nums text-slate-700">{formatNumber(Number(totalWeight.toFixed(1)))}</td>
                      <td className="px-5 py-3 tabular-nums font-semibold text-slate-900">{formatCurrency(Number(sale.net_amount))}</td>
                      <td className="px-5 py-3 tabular-nums text-primary-700 font-medium">{formatCurrency(Number(sale.received_amount))}</td>
                      <td className={`px-5 py-3 tabular-nums font-medium ${sale.remaining_amount > 0 ? 'text-red-600' : 'text-slate-400'}`}>
                        {formatCurrency(Number(sale.remaining_amount))}
                      </td>
                      <td className="px-5 py-3">
                        <PaymentStatusBadge status={sale.payment_status} />
                      </td>
                      <td className="px-5 py-3 text-start">
                        {sale.payment_status !== 'paid' && (
                          <button
                            onClick={() => setPaymentSale(sale)}
                            className="text-xs font-medium text-primary-600 hover:underline"
                          >
                            تحديث الدفع
                          </button>
                        )}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </>
      )}

      {/* Update payment dialog */}
      {paymentSale && (
        <UpdatePaymentDialog
          sale={paymentSale}
          isOpen={!!paymentSale}
          onClose={() => setPaymentSale(null)}
          onSuccess={handlePaymentUpdated}
        />
      )}
    </div>
  )
}

// ── SummaryCard ───────────────────────────────────────────────────────────────
function SummaryCard({
  label,
  value,
  accent = 'slate',
}: {
  label: string
  value: string
  accent?: 'slate' | 'emerald' | 'red'
}) {
  const valueColor = {
    slate:   'text-slate-900',
    emerald: 'text-primary-700',
    red:     'text-red-600',
  }[accent]

  return (
    <div
      className="rounded-2xl border border-slate-200/60 bg-white px-5 py-4"
      style={{ boxShadow: 'var(--shadow-card)' }}
    >
      <p className="text-[11px] font-semibold text-slate-500">{label}</p>
      <p className={`mt-1 text-lg font-bold tabular-nums ${valueColor}`}>{value}</p>
    </div>
  )
}


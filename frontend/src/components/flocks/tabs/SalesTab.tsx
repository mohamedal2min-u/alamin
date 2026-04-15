'use client'

import { useCallback, useEffect, useState } from 'react'
import { Plus, ShoppingCart } from 'lucide-react'
import { salesApi } from '@/lib/api/sales'
import { Button } from '@/components/ui/Button'
import { PaymentStatusBadge } from '@/components/sales/PaymentStatusBadge'
import { CreateSaleDialog } from '@/components/sales/CreateSaleDialog'
import { UpdatePaymentDialog } from '@/components/sales/UpdatePaymentDialog'
import { formatDate, formatNumber } from '@/lib/utils'
import type { Sale } from '@/types/sale'
import type { FlockStatus } from '@/types/flock'

interface Props {
  flockId: number
  flockStatus: FlockStatus
}

export function SalesTab({ flockId, flockStatus }: Props) {
  const [sales, setSales]           = useState<Sale[]>([])
  const [loading, setLoading]       = useState(true)
  const [fetchError, setFetchError] = useState<string | null>(null)
  const [showCreate, setShowCreate] = useState(false)
  const [paymentSale, setPaymentSale] = useState<Sale | null>(null)

  const canAdd = flockStatus === 'active'

  const fetchSales = useCallback(() => {
    setLoading(true)
    salesApi
      .listByFlock(flockId)
      .then((res) => setSales(res.data))
      .catch(() => setFetchError('تعذّر تحميل سجلات المبيعات'))
      .finally(() => setLoading(false))
  }, [flockId])

  useEffect(() => {
    fetchSales()
  }, [fetchSales])

  const handleCreated = (sale: Sale) => {
    setSales((prev) => [sale, ...prev])
  }

  const handlePaymentUpdated = (updated: Sale) => {
    setSales((prev) => prev.map((s) => (s.id === updated.id ? updated : s)))
    setPaymentSale(null)
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20 text-slate-400">
        <span className="text-sm">جارٍ التحميل...</span>
      </div>
    )
  }

  if (fetchError) {
    return (
      <div className="m-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
        {fetchError}
      </div>
    )
  }

  return (
    <div className="p-4 space-y-4">
      {/* Header */}
      {canAdd && (
        <div className="flex justify-end">
          <Button size="sm" onClick={() => setShowCreate(true)}>
            <Plus className="me-1.5 h-4 w-4" />
            بيعة جديدة
          </Button>
        </div>
      )}

      {/* Empty state */}
      {sales.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-16 text-center text-slate-400">
          <ShoppingCart className="mb-3 h-10 w-10 opacity-30" />
          <p className="text-base font-medium text-slate-600">لا توجد مبيعات مسجّلة</p>
          {canAdd && <p className="mt-1 text-sm">اضغط «بيعة جديدة» لإضافة أول سجل</p>}
        </div>
      ) : (
        <div className="space-y-3">
          {sales.map((sale) => (
            <SaleRow
              key={sale.id}
              sale={sale}
              onUpdatePayment={() => setPaymentSale(sale)}
            />
          ))}
        </div>
      )}

      {/* Dialogs */}
      {showCreate && (
        <CreateSaleDialog
          flockId={flockId}
          isOpen={showCreate}
          onClose={() => setShowCreate(false)}
          onSuccess={handleCreated}
        />
      )}

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

// ── SaleRow ───────────────────────────────────────────────────────────────────
function SaleRow({ sale, onUpdatePayment }: { sale: Sale; onUpdatePayment: () => void }) {
  const totalBirds  = sale.items.reduce((s, i) => s + i.birds_count, 0)
  const totalWeight = sale.items.reduce((s, i) => s + Number(i.total_weight_kg), 0)

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 space-y-3" style={{ boxShadow: 'var(--shadow-card)' }}>
      {/* Top row */}
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <p className="text-sm font-semibold text-slate-900">{formatDate(sale.sale_date)}</p>
          {sale.buyer_name && (
            <p className="text-xs text-slate-500 mt-0.5">{sale.buyer_name}</p>
          )}
          {sale.reference_no && (
            <p className="text-xs text-slate-400">مرجع: {sale.reference_no}</p>
          )}
        </div>
        <PaymentStatusBadge status={sale.payment_status} />
      </div>

      {/* Metrics */}
      <div className="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
        <Metric label="عدد الطيور" value={formatNumber(totalBirds)} />
        <Metric label="الوزن الكلي" value={`${formatNumber(Number(totalWeight.toFixed(1)))} كغ`} />
        <Metric label="الصافي" value={formatNumber(Number(sale.net_amount))} highlight />
        <Metric label="المتبقي" value={formatNumber(Number(sale.remaining_amount))} danger={sale.remaining_amount > 0} />
      </div>

      {/* Payment row */}
      <div className="flex items-center justify-between border-t border-slate-100 pt-2 text-xs text-slate-500">
        <span>
          مستلم: <span className="tabular-nums font-medium text-emerald-700">{formatNumber(Number(sale.received_amount))}</span>
          {sale.discount_amount > 0 && (
            <span className="ms-3">خصم: <span className="tabular-nums text-amber-600">{formatNumber(Number(sale.discount_amount))}</span></span>
          )}
        </span>
        {sale.payment_status !== 'paid' && (
          <Button size="sm" variant="outline" onClick={onUpdatePayment}>
            تحديث الدفع
          </Button>
        )}
      </div>
    </div>
  )
}

function Metric({ label, value, highlight, danger }: { label: string; value: string; highlight?: boolean; danger?: boolean }) {
  return (
    <div>
      <p className="text-xs text-slate-500">{label}</p>
      <p className={`tabular-nums font-semibold ${highlight ? 'text-slate-900' : danger ? 'text-red-600' : 'text-slate-700'}`}>
        {value}
      </p>
    </div>
  )
}

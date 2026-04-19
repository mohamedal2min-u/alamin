'use client'

import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { AlertTriangle, Package, ArrowLeftRight, XCircle, Ban, ExternalLink } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import Link from 'next/link'
import { Dialog } from '@/components/ui/Dialog'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { flocksApi } from '@/lib/api/flocks'
import { inventoryApi } from '@/lib/api/inventory'
import { accountingApi } from '@/lib/api/accounting'
import { formatNumber } from '@/lib/utils'
import type { Flock } from '@/types/flock'

const schema = z.object({
  close_date: z
    .string()
    .min(1, 'تاريخ الإغلاق مطلوب')
    .regex(/^\d{4}-\d{2}-\d{2}$/, 'صيغة التاريخ غير صحيحة'),
  notes: z.string().max(5000).optional().or(z.literal('')),
})
type FormData = z.infer<typeof schema>

interface Props {
  flock: Flock
  isOpen: boolean
  onClose: () => void
  onSuccess: (updated: Flock) => void
}

export function CloseFlockDialog({ flock, isOpen, onClose, onSuccess }: Props) {
  const [serverError, setServerError] = useState<string | null>(null)
  const [stockAction, setStockAction] = useState<'transfer' | 'settle' | null>(null)

  // ── Blocking records count ────────────────────────────────────────────────
  const { data: reviewData, isLoading: reviewLoading } = useQuery({
    queryKey: ['blocking-records', flock.id],
    queryFn: () => accountingApi.getReviewQueue({ filter: 'blocking', flock_id: flock.id, per_page: 1 }),
    enabled: isOpen,
    staleTime: 30_000,
  })
  const blockingCount = reviewData?.summary?.blocking_flock_closure_count ?? 0

  // ── Remaining stock ───────────────────────────────────────────────────────
  const { data: overviewData, isLoading: stockLoading } = useQuery({
    queryKey: ['inventory-overview-close'],
    queryFn: () => inventoryApi.overview().then((r) => r.data),
    enabled: isOpen,
    staleTime: 30_000,
  })
  const remainingStock = (overviewData?.stock ?? []).filter((i) => i.total_quantity > 0)
  const hasStock = remainingStock.length > 0

  // Submit is blocked when: debts exist OR stock exists but no action chosen
  const canClose = !reviewLoading && !stockLoading && blockingCount === 0 && (!hasStock || stockAction !== null)

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      close_date: new Date().toISOString().split('T')[0],
    },
  })

  const handleClose = () => {
    reset({ close_date: new Date().toISOString().split('T')[0] })
    setServerError(null)
    setStockAction(null)
    onClose()
  }

  const onSubmit = async (data: FormData) => {
    if (!canClose) return
    setServerError(null)
    try {
      const res = await flocksApi.update(flock.id, {
        status: 'closed',
        close_date: data.close_date,
        notes: data.notes || undefined,
        stock_action: hasStock ? (stockAction ?? 'transfer') : undefined,
      })
      onSuccess(res.data)
      handleClose()
    } catch (err: unknown) {
      const axiosErr = err as {
        response?: { data?: { message?: string; errors?: Record<string, string[]> } }
      }
      const raw = axiosErr?.response?.data?.message ?? ''
      let parsed: { message?: string } | null = null
      try { parsed = JSON.parse(raw) } catch { /* not json */ }
      const first = axiosErr?.response?.data?.errors
        ? Object.values(axiosErr.response.data.errors)[0]?.[0]
        : null
      setServerError(first ?? parsed?.message ?? (raw || 'حدث خطأ غير متوقع'))
    }
  }

  return (
    <Dialog isOpen={isOpen} onClose={handleClose} title="إغلاق الفوج">
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 pt-4" noValidate>

        {/* Warning banner */}
        <div className="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
          <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
          <div className="text-sm text-amber-800">
            <p className="font-semibold">هذا الإجراء لا يمكن التراجع عنه</p>
            <p className="mt-0.5 text-amber-700">
              سيتم إغلاق الفوج وتوزيع الأرباح/الخسائر على الشركاء تلقائياً.
            </p>
          </div>
        </div>

        {/* ── Blocking records ───────────────────────────────────────────── */}
        {reviewLoading ? (
          <div className="h-12 animate-pulse rounded-xl bg-slate-100" />
        ) : blockingCount > 0 ? (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 space-y-2">
            <div className="flex items-center gap-2 text-sm font-semibold text-red-700">
              <Ban className="h-4 w-4 shrink-0" />
              يوجد {blockingCount} سجل مالي مانع للإغلاق
            </div>
            <p className="text-xs text-red-600">
              يجب تسوية جميع الذمم والديون قبل إغلاق الفوج — مصاريف أو مبيعات غير مدفوعة أو ناقصة السعر.
            </p>
            <Link
              href={`/accounting?tab=review&filter=blocking&flock_id=${flock.id}`}
              className="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700 transition"
              onClick={handleClose}
            >
              <ExternalLink className="h-3.5 w-3.5" />
              فتح صفحة المراجعة وتسوية السجلات
            </Link>
          </div>
        ) : (
          <div className="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            <span className="text-base">✓</span>
            جميع الذمم والديون مسوّاة
          </div>
        )}

        {/* ── Remaining stock ────────────────────────────────────────────── */}
        {stockLoading ? (
          <div className="h-12 animate-pulse rounded-xl bg-slate-100" />
        ) : hasStock ? (
          <div className={`rounded-xl border p-4 space-y-3 ${stockAction === null ? 'border-orange-300 bg-orange-50' : 'border-slate-200 bg-slate-50'}`}>
            <div className="flex items-center gap-2 text-sm font-semibold text-slate-700">
              <Package className="h-4 w-4 text-slate-500" />
              مخزون متبقٍّ ({remainingStock.length} صنف)
              {stockAction === null && (
                <span className="text-xs font-medium text-orange-600 bg-orange-100 rounded-full px-2 py-0.5">مطلوب اختيار</span>
              )}
            </div>
            <div className="space-y-1.5 max-h-32 overflow-y-auto">
              {remainingStock.map((item) => (
                <div key={item.id} className="flex justify-between text-xs text-slate-600">
                  <span>{item.name}</span>
                  <span className="font-mono font-medium text-slate-800">
                    {formatNumber(item.total_quantity)} {item.content_unit}
                  </span>
                </div>
              ))}
            </div>
            <p className="text-[11px] text-slate-500 border-t border-slate-200 pt-2">
              يجب اختيار ما يحدث بالمخزون المتبقي قبل الإغلاق:
            </p>
            <div className="grid grid-cols-2 gap-2">
              <button
                type="button"
                onClick={() => setStockAction('transfer')}
                className={`flex items-center gap-2 rounded-lg border px-3 py-2.5 text-xs font-medium transition-colors ${
                  stockAction === 'transfer'
                    ? 'border-primary-500 bg-primary-50 text-primary-700 ring-2 ring-primary-200'
                    : 'border-slate-200 bg-white text-slate-600 hover:border-primary-300'
                }`}
              >
                <ArrowLeftRight className="h-3.5 w-3.5 shrink-0" />
                ترحيل للفوج التالي
              </button>
              <button
                type="button"
                onClick={() => setStockAction('settle')}
                className={`flex items-center gap-2 rounded-lg border px-3 py-2.5 text-xs font-medium transition-colors ${
                  stockAction === 'settle'
                    ? 'border-rose-400 bg-rose-50 text-rose-700 ring-2 ring-rose-200'
                    : 'border-slate-200 bg-white text-slate-600 hover:border-rose-300'
                }`}
              >
                <XCircle className="h-3.5 w-3.5 shrink-0" />
                تسوية وشطب
              </button>
            </div>
            {stockAction === 'transfer' && (
              <p className="text-[11px] text-primary-600 bg-primary-50 rounded-lg px-3 py-2">
                سيبقى المخزون في المستودع ومتاح تلقائياً للفوج التالي.
              </p>
            )}
            {stockAction === 'settle' && (
              <p className="text-[11px] text-rose-600 bg-rose-50 rounded-lg px-3 py-2">
                سيُسجَّل المخزون المتبقي كخسارة ضمن تقرير الفوج.
              </p>
            )}
          </div>
        ) : (
          <div className="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            <span className="text-base">✓</span>
            لا يوجد مخزون متبقٍّ
          </div>
        )}

        {/* Flock summary */}
        <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm space-y-2">
          <p className="font-semibold text-slate-700 mb-3">{flock.name}</p>
          <div className="flex justify-between text-slate-600">
            <span>العدد الأولي</span>
            <span className="tabular-nums font-medium text-slate-900">{formatNumber(flock.initial_count)}</span>
          </div>
          <div className="flex justify-between text-slate-600">
            <span>إجمالي المبيعات</span>
            <span className="tabular-nums font-medium text-primary-700">{formatNumber(Number(flock.total_sales))}</span>
          </div>
          <div className="flex justify-between text-slate-600">
            <span>إجمالي المصاريف</span>
            <span className="tabular-nums font-medium text-slate-900">{formatNumber(Number(flock.total_expenses))}</span>
          </div>
          <div className={`flex justify-between border-t border-slate-200 pt-2 font-semibold ${Number(flock.net_profit) >= 0 ? 'text-primary-700' : 'text-red-600'}`}>
            <span>صافي الربح/الخسارة</span>
            <span className="tabular-nums">{formatNumber(Number(flock.net_profit))}</span>
          </div>
        </div>

        {/* Close date */}
        <Input
          {...register('close_date')}
          id="close_date"
          label="تاريخ الإغلاق"
          type="date"
          max={new Date().toISOString().split('T')[0]}
          error={errors.close_date?.message}
          required
        />

        {/* Notes */}
        <div className="space-y-1">
          <label htmlFor="close_notes" className="text-sm font-medium text-slate-700">
            ملاحظات <span className="text-xs text-slate-400">(اختياري)</span>
          </label>
          <textarea
            {...register('notes')}
            id="close_notes"
            rows={2}
            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
          />
        </div>

        {serverError && (
          <p className="text-sm text-red-600">{serverError}</p>
        )}

        {/* Checklist summary */}
        {!canClose && !reviewLoading && !stockLoading && (
          <div className="rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-xs text-orange-700 space-y-1">
            <p className="font-semibold">يجب استيفاء الشروط التالية قبل الإغلاق:</p>
            {blockingCount > 0 && <p>• تسوية {blockingCount} سجل مالي مانع</p>}
            {hasStock && stockAction === null && <p>• اختيار قرار المخزون المتبقي (ترحيل أو تسوية)</p>}
          </div>
        )}

        <div className="flex gap-2 justify-end pt-1">
          <Button type="button" variant="outline" size="sm" onClick={handleClose}>
            إلغاء
          </Button>
          <Button
            type="submit"
            size="sm"
            loading={isSubmitting}
            disabled={!canClose || isSubmitting}
            className="bg-red-600 hover:bg-red-700 focus:ring-red-500/30 disabled:opacity-40 disabled:cursor-not-allowed"
          >
            تأكيد الإغلاق
          </Button>
        </div>
      </form>
    </Dialog>
  )
}

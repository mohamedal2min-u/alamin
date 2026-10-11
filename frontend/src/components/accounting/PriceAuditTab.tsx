'use client'

import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Loader2, ShieldCheck } from 'lucide-react'
import { accountingApi } from '@/lib/api/accounting'
import type { ShareAuditEntry } from '@/lib/api/accounting'
import { Card, CardContent } from '@/components/ui/Card'
import { formatNumber } from '@/lib/utils'

const TYPE_LABELS = { expense: 'مصروف', water_log: 'ماء', sale: 'بيع' } as const

/** سجل كل تعديلات الأسعار — للقراءة فقط، السعر القديم مشطوب ولا يُحذف. */
export function PriceAuditTab() {
  const [editsOnly, setEditsOnly] = useState(true)
  const [page, setPage] = useState(1)

  const { data, isLoading } = useQuery({
    queryKey: ['accounting', 'price-audit', { editsOnly, page }],
    queryFn: () => accountingApi.getPriceAudit({ edits_only: editsOnly, page, per_page: 30 }),
    staleTime: 15_000,
  })

  const shares = useQuery({
    queryKey: ['accounting', 'share-audit'],
    queryFn: () => accountingApi.getShareAudit({ per_page: 30 }),
    staleTime: 15_000,
  })

  return (
    <div className="space-y-4" dir="rtl">
      <div className="flex items-center gap-2 flex-wrap">
        <ShieldCheck className="h-5 w-5 text-primary-600" />
        <p className="text-sm text-slate-600">كل تغيير في سعر يُسجَّل باسم من عدّله ووقته، ولا يمكن حذفه.</p>
        <button
          onClick={() => { setEditsOnly((v) => !v); setPage(1) }}
          className={`rounded-full px-3 py-1 text-sm font-medium border transition-colors ${
            editsOnly ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-slate-600 border-slate-200'
          }`}
        >
          التعديلات فقط
        </button>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-12"><Loader2 className="h-6 w-6 animate-spin text-slate-400" /></div>
      ) : !data?.data.length ? (
        <p className="py-12 text-center text-sm text-slate-400">لا توجد تعديلات أسعار مسجَّلة</p>
      ) : (
        <div className="space-y-2">
          {data.data.map((e) => {
            // سعر البيع مخزَّن للكيلو ويُعرض للطن
            const f = (v: number | null) => (v == null ? '—' : formatNumber(e.record_type === 'sale' ? v * 1000 : v))
            return (
              <Card key={e.id}>
                <CardContent className="py-3 px-4 flex flex-wrap items-center justify-between gap-2 text-sm">
                  <div className="min-w-0">
                    <div className="font-medium text-slate-800">{e.description ?? '—'}</div>
                    <div className="text-xs text-slate-400">
                      {TYPE_LABELS[e.record_type]} • {e.by ?? '—'} • {e.at ? new Date(e.at).toLocaleString('ar') : ''}
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    {e.is_edit ? (
                      <s className="text-red-400 decoration-red-500">{f(e.old_price)}</s>
                    ) : (
                      <span className="text-xs text-emerald-700">أول تسعير</span>
                    )}
                    <strong className="text-slate-800">{f(e.new_price)}</strong>
                  </div>
                </CardContent>
              </Card>
            )
          })}

          {data.meta.total > data.meta.per_page && (
            <div className="flex items-center justify-center gap-3 pt-3">
              <button disabled={page === 1} onClick={() => setPage((p) => p - 1)} className="rounded px-3 py-1 text-sm border disabled:opacity-40">السابق</button>
              <span className="text-xs text-slate-500">صفحة {data.meta.current_page} من {Math.ceil(data.meta.total / data.meta.per_page)}</span>
              <button disabled={page * data.meta.per_page >= data.meta.total} onClick={() => setPage((p) => p + 1)} className="rounded px-3 py-1 text-sm border disabled:opacity-40">التالي</button>
            </div>
          )}
        </div>
      )}

      {/* ── تعديلات حصص الشركاء ── */}
      <div className="pt-2">
        <h3 className="mb-2 text-sm font-bold text-slate-700">تعديلات حصص الشركاء</h3>
        {shares.isLoading ? (
          <div className="flex justify-center py-6"><Loader2 className="h-5 w-5 animate-spin text-slate-400" /></div>
        ) : !shares.data?.data.length ? (
          <p className="py-6 text-center text-sm text-slate-400">لا توجد تعديلات حصص</p>
        ) : (
          <div className="space-y-2">
            {shares.data.data.map((e: ShareAuditEntry) => (
              <Card key={e.id}>
                <CardContent className="py-3 px-4 flex flex-wrap items-center justify-between gap-2 text-sm">
                  <div className="min-w-0">
                    <div className="font-medium text-slate-800">{e.partner ?? '—'}</div>
                    <div className="text-xs text-slate-400">{e.by ?? '—'} • {e.at ? new Date(e.at).toLocaleString('ar') : ''}</div>
                  </div>
                  <div className="flex items-center gap-2">
                    {e.is_edit ? <s className="text-red-400 decoration-red-500">{formatNumber(e.old_percent ?? 0)}%</s> : <span className="text-xs text-emerald-700">أول تحديد</span>}
                    <strong className="text-slate-800">{formatNumber(e.new_percent)}%</strong>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

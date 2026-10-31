'use client'

import { useQuery } from '@tanstack/react-query'
import { apiClient } from '@/lib/api/client'
import { formatNumber } from '@/lib/utils'

interface Outflow {
  id: number
  date: string | null
  item_name: string | null
  kg: number
  total_amount: number
  notes: string | null
}

// علف خرج من مخزن الفوج لغيره بسعر الشراء: يُطرح من المشتريات ليظهر صافي العلف على الفوج
export function FeedOutflows({ flockId, purchasesTotal }: { flockId: number; purchasesTotal: number }) {
  const { data: rows = [] } = useQuery<Outflow[]>({
    queryKey: ['feed-outflows', flockId],
    queryFn: () => apiClient.get<{ data: Outflow[] }>(`/flocks/${flockId}/feed-outflows`).then((r) => r.data.data),
    staleTime: 30_000,
  })
  if (rows.length === 0) return null

  const out = rows.reduce((n, r) => n + r.total_amount, 0)
  return (
    <div className="space-y-2">
      <h2 className="text-sm font-extrabold text-slate-700 dark:text-slate-200">
        علف خرج من المخزن <span className="text-slate-400">({rows.length})</span>
      </h2>
      <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-slate-50 text-[11px] font-extrabold text-slate-500 dark:bg-slate-700/50 dark:text-slate-300">
              <th className="px-2 py-2.5 text-right">التاريخ</th>
              <th className="px-2 py-2.5 text-right">البيان</th>
              <th className="px-2 py-2.5 text-right">الكمية</th>
              <th className="px-2 py-2.5 text-left">المبلغ</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id} className="border-t border-slate-100 align-top dark:border-slate-700">
                <td className="whitespace-nowrap px-2 py-3 text-xs text-slate-400">{r.date?.slice(5).split('-').reverse().join('-') ?? '—'}</td>
                <td className="px-2 py-3 font-bold text-slate-800 dark:text-slate-100">{r.notes ?? r.item_name}</td>
                <td className="whitespace-nowrap px-2 py-3 font-bold tabular-nums text-slate-700 dark:text-slate-200">{formatNumber(r.kg / 1000)} طن</td>
                <td className="whitespace-nowrap px-2 py-3 text-left font-black tabular-nums text-red-600">−{formatNumber(r.total_amount)}</td>
              </tr>
            ))}
          </tbody>
          <tfoot className="border-t border-slate-200 bg-slate-100 text-slate-900 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100">
            <tr>
              <td className="px-2 py-2 text-xs font-bold" colSpan={3}>المشتريات</td>
              <td className="px-2 py-2 text-left font-bold tabular-nums">{formatNumber(purchasesTotal)}</td>
            </tr>
            <tr>
              <td className="px-2 py-2 text-xs font-bold" colSpan={3}>ناقص الخارج</td>
              <td className="px-2 py-2 text-left font-bold tabular-nums text-red-600">−{formatNumber(out)}</td>
            </tr>
            <tr className="font-black">
              <td className="px-2 py-3" colSpan={3}>صافي العلف على الفوج</td>
              <td className="px-2 py-3 text-left tabular-nums">{formatNumber(purchasesTotal - out)}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  )
}

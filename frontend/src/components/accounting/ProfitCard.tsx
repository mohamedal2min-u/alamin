'use client'

import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ChevronDown, Lock, TrendingDown, TrendingUp, Users } from 'lucide-react'
import { reportsApi } from '@/lib/api/reports'
import { cn, formatNumber } from '@/lib/utils'

/** الربح والخسارة للفوج وحصة كل شريك — بنفس المعادلة التي تُسجَّل في حسابات الشركاء عند الإغلاق. */
export function ProfitCard({ flockId }: { flockId: string }) {
  const [details, setDetails] = useState(false)
  const { data, isLoading } = useQuery({
    queryKey: ['accounting', 'review-queue', 'profit', flockId],
    queryFn: () => reportsApi.getFlockProfit(flockId || undefined),
    staleTime: 15_000,
  })

  if (isLoading) return <div className="h-40 animate-pulse rounded-2xl bg-slate-100 dark:bg-slate-800" />
  if (!data?.flock) return null

  // الربح لا يظهر قبل اكتمال الحسابات: أي سجل بلا سعر يجعل الرقم مضلِّلاً
  if ((data.unpriced_count ?? 0) > 0 && !data.recorded) {
    return (
      <section className="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
        <div className="flex items-center gap-2 text-sm font-extrabold text-amber-800 dark:text-amber-300">
          <Lock className="h-4 w-4" /> الربح ونصيب الشركاء · {data.flock.name}
        </div>
        <p className="mt-1.5 text-sm text-amber-800 dark:text-amber-200">
          يظهر بعد تسجيل كل الحسابات. بقي <b className="tabular-nums">{data.unpriced_count}</b> سجل بلا سعر في قسم «الأسعار».
        </p>
      </section>
    )
  }

  const net = data.net ?? 0
  const isProfit = net >= 0
  const partners = data.partners ?? []
  const sharesOk = Math.abs((data.shares_total ?? 0) - 100) <= 0.01

  return (
    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
      {/* الصافي */}
      <div className={cn('px-4 py-4 text-white', isProfit ? 'bg-emerald-600' : 'bg-rose-600')}>
        <div className="flex items-center justify-between text-xs font-semibold opacity-90">
          <span>{isProfit ? 'صافي الربح' : 'صافي الخسارة'} · {data.flock.name}</span>
          {isProfit ? <TrendingUp className="h-4 w-4" /> : <TrendingDown className="h-4 w-4" />}
        </div>
        <div className="mt-1 text-3xl font-black tabular-nums">{formatNumber(Math.abs(net))}</div>
      </div>

      {/* المبيعات − التكاليف */}
      <div className="space-y-1.5 px-4 py-3 text-sm">
        <Row label="إجمالي المبيعات" value={data.sales ?? 0} tone="text-emerald-700 dark:text-emerald-400" sign="+" />
        <button type="button" onClick={() => setDetails((v) => !v)} className="block w-full">
          <Row
            label={<span className="flex items-center gap-1">إجمالي التكاليف <ChevronDown className={cn('h-3.5 w-3.5 transition-transform', details && 'rotate-180')} /></span>}
            value={data.total_expenses ?? 0}
            tone="text-rose-700 dark:text-rose-400"
            sign="−"
          />
        </button>
        {details && (
          <div className="space-y-1 rounded-xl bg-slate-50 px-3 py-2 text-xs dark:bg-slate-900/40">
            <Row label="علف ودواء مستهلك" value={data.inventory ?? 0} small />
            <Row label="مصاريف (كتاكيت وغيرها)" value={data.operations ?? 0} small />
            <Row label="ماء" value={data.water ?? 0} small />
          </div>
        )}
      </div>

      {/* حصص الشركاء */}
      <div className="border-t border-slate-100 px-4 py-3 dark:border-slate-700">
        <h3 className="mb-2 flex items-center gap-1.5 text-xs font-extrabold text-slate-600 dark:text-slate-300">
          <Users className="h-4 w-4" /> نصيب الشركاء
        </h3>
        {partners.length === 0 ? (
          <p className="text-xs text-slate-400">لا يوجد شركاء بحصص — أضفهم من قسم «الشركاء».</p>
        ) : (
          <ul className="space-y-1.5">
            {partners.map((p) => (
              <li key={p.partner_id} className="flex items-center justify-between text-sm">
                <span className="font-semibold text-slate-700 dark:text-slate-200">
                  {p.name ?? 'شريك'} <span className="text-xs text-slate-400">{formatNumber(p.percent)}%</span>
                </span>
                <span className={cn('font-extrabold tabular-nums', p.amount >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400')}>
                  {p.amount >= 0 ? '+' : '−'}{formatNumber(Math.abs(p.amount))}
                </span>
              </li>
            ))}
          </ul>
        )}
        {!data.recorded && partners.length > 0 && !sharesOk && (
          <p className="mt-2 text-xs font-semibold text-rose-600">مجموع النسب {formatNumber(data.shares_total ?? 0)}% — يجب أن يكون 100% قبل إغلاق الفوج.</p>
        )}
        <p className="mt-2 text-[11px] text-slate-400">
          {data.recorded
            ? 'سُجّلت هذه المبالغ في حساب كل شريك عند إغلاق الفوج.'
            : 'يتحدّث مباشرة مع كل سعر، ويُسجَّل في حساب كل شريك تلقائياً عند إغلاق الفوج.'}
        </p>
      </div>
    </section>
  )
}

function Row({ label, value, tone, sign, small }: { label: React.ReactNode; value: number; tone?: string; sign?: string; small?: boolean }) {
  return (
    <div className="flex items-center justify-between">
      <span className={cn('text-slate-500', small ? 'text-xs' : 'text-sm font-semibold')}>{label}</span>
      <span className={cn('font-extrabold tabular-nums', small ? 'text-xs text-slate-600 dark:text-slate-300' : tone)}>
        {sign}{formatNumber(value)}
      </span>
    </div>
  )
}

/** أرصدة الشركاء الفعلية (مبالغ مسجّلة): تظهر دائماً، لا تنتظر التسعير. */
export function PartnerBalances({ flockId }: { flockId: string }) {
  const { data, isLoading } = useQuery({
    queryKey: ['accounting', 'review-queue', 'profit', flockId],
    queryFn: () => reportsApi.getFlockProfit(flockId || undefined),
    staleTime: 15_000,
  })
  if (isLoading) return <div className="h-32 animate-pulse rounded-2xl bg-slate-100 dark:bg-slate-800" />
  const rows = data?.balances ?? []
  if (!data?.flock || rows.length === 0) return null

  const totalFlock = rows.reduce((n, r) => n + r.flock_balance, 0)
  const totalAll = rows.reduce((n, r) => n + r.total_balance, 0)
  const money = (v: number) => (
    <span className={cn('font-extrabold tabular-nums', v > 0 ? 'text-emerald-700 dark:text-emerald-400' : v < 0 ? 'text-rose-700 dark:text-rose-400' : 'text-slate-400')}>
      {v < 0 ? '−' : ''}{formatNumber(Math.abs(v))}
    </span>
  )

  return (
    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
      <h3 className="flex items-center gap-1.5 px-4 pt-3 text-sm font-extrabold text-slate-700 dark:text-slate-200">
        <Users className="h-4 w-4" /> أرصدة الشركاء
      </h3>
      <table className="mt-2 w-full text-sm">
        <thead>
          <tr className="text-[11px] text-slate-400">
            <th className="px-4 py-1.5 text-right font-semibold">الشريك</th>
            <th className="px-2 py-1.5 text-left font-semibold">{data.flock.name}</th>
            <th className="px-4 py-1.5 text-left font-semibold">كل الأفواج</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
          {rows.map((r) => (
            <tr key={r.partner_id}>
              <td className="px-4 py-2 font-semibold text-slate-700 dark:text-slate-200">{r.name ?? 'شريك'}</td>
              <td className="px-2 py-2 text-left">{money(r.flock_balance)}</td>
              <td className="px-4 py-2 text-left">{money(r.total_balance)}</td>
            </tr>
          ))}
        </tbody>
        <tfoot>
          <tr className="bg-slate-50 dark:bg-slate-900/40">
            <td className="px-4 py-2.5 text-xs font-extrabold text-slate-600 dark:text-slate-300">الإجمالي</td>
            <td className="px-2 py-2.5 text-left">{money(totalFlock)}</td>
            <td className="px-4 py-2.5 text-left">{money(totalAll)}</td>
          </tr>
        </tfoot>
      </table>
      <p className="px-4 pb-3 pt-2 text-[11px] text-slate-400">الرصيد = إيداعات + أرباح − سحوبات − خسائر − تسويات (المسجّل فعلاً).</p>
    </section>
  )
}

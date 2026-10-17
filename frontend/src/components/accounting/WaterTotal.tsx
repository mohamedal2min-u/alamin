'use client'

import { useState } from 'react'
import { AlertTriangle, Check, Droplets, Loader2 } from 'lucide-react'
import { accountingApi } from '@/lib/api/accounting'
import type { ReviewItem } from '@/lib/api/accounting'
import { cn, formatNumber } from '@/lib/utils'

// الماء: يُسجَّل المبلغ الإجمالي مرة واحدة ويُقسَّم على الصهاريج حسب كمية كل صهريج

/** يوزّع الإجمالي (بالقروش) على السجلات حسب الكمية، والباقي من التقريب يذهب لأكبر الكسور فيبقى المجموع مطابقاً تماماً. */
function splitTotal(total: number, rows: ReviewItem[]): number[] {
  const cents = Math.round(total * 100)
  const qty = rows.map((r) => r.quantity as number)
  const sumQty = qty.reduce((a, b) => a + b, 0)
  const exact = qty.map((q) => (cents * q) / sumQty)
  const parts = exact.map(Math.floor)
  let rest = cents - parts.reduce((a, b) => a + b, 0)
  const order = exact.map((e, i) => [e - Math.floor(e), i] as const).sort((a, b) => b[0] - a[0])
  for (const [, i] of order) {
    if (rest <= 0) break
    parts[i] += 1
    rest -= 1
  }
  return parts.map((p) => p / 100)
}

export function WaterTotal({ rows, flockSelected, onDone }: { rows: ReviewItem[]; flockSelected: boolean; onDone: () => void }) {
  const [value, setValue] = useState('')
  const [confirming, setConfirming] = useState(false)
  const [running, setRunning] = useState(false)
  const [progress, setProgress] = useState(0)
  const [result, setResult] = useState<{ ok: number; failed: number } | null>(null)

  const open = (r: ReviewItem) => r.flock_status !== 'closed' && r.flock_status !== 'cancelled'
  const targets = rows.filter((r) => r.type === 'water_log' && open(r) && (r.quantity ?? 0) > 0)
  const skipped = rows.filter((r) => r.type === 'water_log' && open(r)).length - targets.length
  const current = rows.reduce((n, r) => n + (r.total_amount ?? 0), 0)
  const total = parseFloat(value)
  const valid = Number.isFinite(total) && total > 0

  if (!flockSelected) {
    return (
      <p className="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-[13px] font-semibold text-sky-800 dark:border-sky-900/40 dark:bg-sky-900/20 dark:text-sky-300">
        لتسجيل إجمالي الماء مرة واحدة اختر الفوج من الأعلى.
      </p>
    )
  }
  if (targets.length === 0) return null

  const run = async () => {
    const shares = splitTotal(total, targets)
    setConfirming(false); setRunning(true); setProgress(0); setResult(null)
    let ok = 0, failed = 0
    for (let i = 0; i < targets.length; i++) {
      const r = targets[i]
      try {
        await accountingApi.updateReviewItem(r.type, r.record_id, { unit_price: shares[i] / (r.quantity as number) })
        ok += 1
      } catch {
        failed += 1
      }
      setProgress(ok + failed)
    }
    setRunning(false)
    setResult({ ok, failed })
    if (ok > 0) { setValue(''); onDone() }
  }

  const each = valid ? total / targets.length : 0
  const sameQty = targets.every((r) => r.quantity === targets[0].quantity)

  return (
    <section className="space-y-3 rounded-2xl border border-sky-200 bg-white p-4 dark:border-sky-900/40 dark:bg-slate-800">
      <div className="flex items-center gap-2">
        <span className="flex h-8 w-8 items-center justify-center rounded-xl bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300"><Droplets className="h-4 w-4" /></span>
        <div>
          <h2 className="text-sm font-extrabold text-slate-800 dark:text-slate-100">إجمالي الماء مرة واحدة</h2>
          <p className="text-[11px] font-semibold text-slate-400">
            {targets.length} صهريج{current > 0 ? ` · الإجمالي الحالي ${formatNumber(current)}` : ''}
          </p>
        </div>
      </div>

      <input
        type="number" inputMode="decimal" min="0" step="any" dir="ltr"
        value={value} onChange={(e) => { setValue(e.target.value); setConfirming(false); setResult(null) }}
        placeholder="المبلغ الإجمالي لكل الصهاريج"
        disabled={running}
        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-center text-lg font-bold tabular-nums outline-none focus:border-sky-600 dark:border-slate-600 dark:bg-slate-900"
      />

      {skipped > 0 && <p className="text-[11px] font-semibold text-amber-600">{skipped} سجل بلا كمية لا يدخل في التقسيم (سعّره وحده)</p>}

      {confirming && valid && (
        <div className="space-y-2 rounded-xl border border-amber-300 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
          <p className="flex items-start gap-2 text-[13px] font-bold text-amber-800 dark:text-amber-300">
            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
            <span>
              سيُقسَّم المبلغ {formatNumber(total)} على {targets.length} صهريج
              {sameQty ? <>، أي حوالي {formatNumber(Math.round(each * 100) / 100)} لكل صهريج</> : ' حسب كمية كل صهريج'}،
              وتتغيّر أسعار كل الصهاريج حتى المسعّرة منها.
            </span>
          </p>
          <div className="grid grid-cols-2 gap-2">
            <button type="button" onClick={() => void run()} className="rounded-xl bg-amber-600 py-2 text-sm font-bold text-white active:scale-95">تأكيد التقسيم</button>
            <button type="button" onClick={() => setConfirming(false)} className="rounded-xl border border-slate-300 bg-white py-2 text-sm font-bold text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">إلغاء</button>
          </div>
        </div>
      )}

      {result && (
        <p className={cn('text-[12px] font-bold', result.failed > 0 ? 'text-rose-600' : 'text-emerald-600')}>
          تم تسعير {result.ok} صهريج{result.failed > 0 ? ` · تعذّر ${result.failed}` : ''}
        </p>
      )}

      {!confirming && (
        <button
          type="button"
          onClick={() => setConfirming(true)}
          disabled={running || !valid}
          className="flex w-full items-center justify-center gap-2 rounded-xl bg-sky-600 py-2.5 text-sm font-bold text-white disabled:opacity-40"
        >
          {running ? <><Loader2 className="h-4 w-4 animate-spin" /> {progress}/{targets.length}</> : <><Check className="h-4 w-4" /> تقسيم المبلغ على الصهاريج</>}
        </button>
      )}
    </section>
  )
}

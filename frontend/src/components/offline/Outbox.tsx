'use client'

import { useEffect, useState, useSyncExternalStore } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { CloudOff, RotateCcw, Trash2, AlertCircle, Send } from 'lucide-react'
import { Dialog } from '@/components/ui/Dialog'
import { cn } from '@/lib/utils'
import type { OutboxItem } from '@/lib/offline/outbox'
import {
  EDITABLE_FIELD, KIND_LABELS, deleteOutboxItem, flushOutbox, getOutboxSnapshot,
  getServerOutboxSnapshot, retryOutboxItem, subscribeOutbox,
} from '@/lib/offline/dailyEntries'

export function useOutbox(): OutboxItem[] {
  return useSyncExternalStore(subscribeOutbox, getOutboxSnapshot, getServerOutboxSnapshot)
}

/**
 * إعادة إرسال صندوق الانتظار: عند فتح التطبيق، عودة الاتصال، وعودة التطبيق للواجهة.
 * بلا مؤقتات ولا استطلاع دوري.
 */
export function OutboxSync() {
  const queryClient = useQueryClient()

  useEffect(() => {
    const flush = () => { void flushOutbox(queryClient) }
    const onVisible = () => { if (document.visibilityState === 'visible') flush() }

    flush()
    window.addEventListener('online', flush)
    document.addEventListener('visibilitychange', onVisible)
    return () => {
      window.removeEventListener('online', flush)
      document.removeEventListener('visibilitychange', onVisible)
    }
  }, [queryClient])

  return null
}

/** شارة صغيرة في رأس الفوج بعدد الإدخالات المنتظرة؛ تفتح قائمة الصندوق */
export function OutboxBadge() {
  const items = useOutbox()
  const [open, setOpen] = useState(false)

  if (items.length === 0 && !open) return null
  const hasFailed = items.some((i) => i.status === 'failed')

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen(true)}
        title="بانتظار الإرسال"
        aria-label={`بانتظار الإرسال: ${items.length}`}
        className={cn(
          'relative flex h-6 shrink-0 items-center gap-0.5 rounded-lg px-1.5 text-[10px] font-black active:scale-95 transition-all',
          hasFailed
            ? 'bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300'
            : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
        )}
      >
        <CloudOff className="h-3 w-3" />
        <span className="tabular-nums">{items.length}</span>
      </button>
      <OutboxSheet isOpen={open} onClose={() => setOpen(false)} items={items} />
    </>
  )
}

function OutboxSheet({ isOpen, onClose, items }: { isOpen: boolean; onClose: () => void; items: OutboxItem[] }) {
  const queryClient = useQueryClient()
  const [busy, setBusy] = useState(false)
  const pendingCount = items.filter((i) => i.status === 'pending').length

  const sendAll = async () => {
    setBusy(true)
    try { await flushOutbox(queryClient) } finally { setBusy(false) }
  }

  return (
    <Dialog isOpen={isOpen} onClose={onClose} title="بانتظار الإرسال">
      <div className="space-y-3 pt-2" dir="rtl">
        {items.length === 0 ? (
          <p className="py-6 text-center text-xs font-bold text-slate-400 dark:text-slate-500">لا توجد إدخالات منتظرة</p>
        ) : (
          <>
            <p className="text-[11px] font-bold text-slate-500 dark:text-slate-400">
              تُرسَل تلقائياً عند عودة الاتصال أو عند فتح التطبيق.
            </p>
            <ul className="max-h-[55vh] space-y-2 overflow-y-auto thin-scrollbar">
              {items.map((item) => <OutboxRow key={item.id} item={item} />)}
            </ul>
            {pendingCount > 0 && (
              <button
                type="button"
                onClick={sendAll}
                disabled={busy}
                className="flex w-full items-center justify-center gap-2 rounded-xl bg-primary-600 py-3 text-sm font-bold text-white active:scale-[0.98] disabled:opacity-50"
              >
                <Send className="h-4 w-4" />
                {busy ? 'جارٍ الإرسال...' : `إرسال الآن (${pendingCount})`}
              </button>
            )}
          </>
        )}
      </div>
    </Dialog>
  )
}

function OutboxRow({ item }: { item: OutboxItem }) {
  const queryClient = useQueryClient()
  const field = EDITABLE_FIELD[item.kind]
  const original = field ? String(item.payload[field] ?? '') : ''
  const [value, setValue] = useState(original)
  const [busy, setBusy] = useState(false)
  const failed = item.status === 'failed'
  const date = (item.payload.entry_date ?? item.payload.log_date) as string | undefined

  const retry = async () => {
    setBusy(true)
    try {
      const patch = field && value !== original && value !== '' && !isNaN(Number(value))
        ? { payload: { [field]: Number(value) } }
        : undefined
      await retryOutboxItem(queryClient, item.id, patch)
    } finally {
      setBusy(false)
    }
  }

  const remove = () => {
    if (window.confirm('حذف هذا الإدخال نهائياً؟ لن يُرسَل.')) deleteOutboxItem(item.id)
  }

  return (
    <li className={cn(
      'rounded-xl border p-3 space-y-2',
      failed
        ? 'border-rose-100 bg-rose-50/50 dark:border-rose-900/40 dark:bg-rose-900/10'
        : 'border-amber-100 bg-amber-50/40 dark:border-amber-900/40 dark:bg-amber-900/10',
    )}>
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="truncate text-xs font-black text-slate-800 dark:text-slate-100">{item.label || KIND_LABELS[item.kind]}</p>
          <p className="text-[10px] font-bold text-slate-500 dark:text-slate-400" dir="ltr">
            {date ?? ''}{field && !failed ? ` · ${original}` : ''}
          </p>
        </div>
        <span className={cn(
          'shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-black',
          failed ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        )}>
          {failed ? 'فشل' : 'بانتظار الإرسال'}
        </span>
      </div>

      {failed && item.error && (
        <p className="flex items-start gap-1 text-[11px] font-bold text-rose-700 dark:text-rose-300">
          <AlertCircle className="mt-0.5 h-3 w-3 shrink-0" />
          {item.error}
        </p>
      )}

      {failed && field && (
        <input
          type="number"
          inputMode="decimal"
          value={value}
          onChange={(e) => setValue(e.target.value)}
          aria-label="تصحيح القيمة"
          dir="ltr"
          className="w-full rounded-lg border border-rose-200 bg-white px-3 py-2 text-sm font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-rose-500/20 dark:border-rose-900/50 dark:bg-slate-800 dark:text-slate-100"
        />
      )}

      <div className="flex gap-2">
        <button
          type="button"
          onClick={retry}
          disabled={busy}
          className="flex flex-1 items-center justify-center gap-1 rounded-lg bg-white py-2 text-[11px] font-bold text-primary-700 border border-primary-100 active:scale-95 disabled:opacity-50 dark:bg-slate-800 dark:text-primary-300 dark:border-slate-700"
        >
          <RotateCcw className={cn('h-3 w-3', busy && 'animate-spin')} />
          إعادة المحاولة
        </button>
        <button
          type="button"
          onClick={remove}
          disabled={busy}
          className="flex items-center justify-center gap-1 rounded-lg bg-white px-3 py-2 text-[11px] font-bold text-rose-600 border border-rose-100 active:scale-95 disabled:opacity-50 dark:bg-slate-800 dark:text-rose-300 dark:border-slate-700"
        >
          <Trash2 className="h-3 w-3" />
          حذف
        </button>
      </div>
    </li>
  )
}

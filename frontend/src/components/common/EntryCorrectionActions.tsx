'use client'

import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Pencil, Ban, Loader2 } from 'lucide-react'
import { toast } from 'sonner'
import { dailyEntriesApi, type DailyEntryType, type DailyEntryUpdatePayload } from '@/lib/api/dailyEntries'
import { cn } from '@/lib/utils'

/** القيم الحالية القابلة للتعديل لكل نوع */
export interface EntryValues {
  quantity?: number | null
  reason?: string | null
  note_text?: string | null
  temperature?: number | null
}

interface Props {
  entryType: DailyEntryType
  id: number
  canEdit?: boolean
  canVoid?: boolean
  values: EntryValues
}

// الاستعلامات التي تتأثر بتصحيح إدخال يومي (المجاميع، المخزون، التكاليف، السجل)
const AFFECTED_KEYS = new Set([
  'today-summary', 'flock-history', 'flocks', 'flock', 'inventory', 'reports', 'accounting',
  'activity-logs', 'mortalities', 'feed-logs', 'medicine-logs', 'water-logs', 'flock-notes',
  'temperature-logs', 'daily-summary',
])

const inputCls = 'w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-300'

/** أزرار «تعديل» و«إلغاء» لسجل يومي مع نموذج صغير داخل السطر */
export function EntryCorrectionActions({ entryType, id, canEdit, canVoid, values }: Props) {
  const queryClient = useQueryClient()
  const [mode, setMode] = useState<'edit' | 'void' | null>(null)
  const [form, setForm] = useState<EntryValues>(values)
  const [editReason, setEditReason] = useState('')
  const [voidReason, setVoidReason] = useState('')

  const done = (message: string) => {
    toast.success(message)
    setMode(null)
    queryClient.invalidateQueries({ predicate: (q) => AFFECTED_KEYS.has(String(q.queryKey[0])) })
  }
  const failed = (err: unknown) => {
    const e = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const first = e.response?.data?.errors ? Object.values(e.response.data.errors)[0]?.[0] : undefined
    toast.error(first ?? e.response?.data?.message ?? 'تعذّر حفظ التصحيح')
  }

  const update = useMutation({
    mutationFn: (payload: DailyEntryUpdatePayload) => dailyEntriesApi.update(entryType, id, payload),
    onSuccess: () => done('تم تعديل السجل'),
    onError: failed,
  })
  const voidM = useMutation({
    mutationFn: () => dailyEntriesApi.void(entryType, id, voidReason.trim()),
    onSuccess: () => done('تم إلغاء السجل'),
    onError: failed,
  })

  if (!canEdit && !canVoid) return null

  const submitEdit = (e: React.FormEvent) => {
    e.preventDefault()
    const payload: DailyEntryUpdatePayload = { edit_reason: editReason.trim() || null }
    if (entryType === 'note') {
      payload.note_text = (form.note_text ?? '').trim()
    } else if (entryType === 'temperature_log') {
      payload.temperature = Number(form.temperature)
    } else {
      payload.quantity = Number(form.quantity)
      if (entryType === 'mortality') payload.reason = form.reason || null
    }
    update.mutate(payload)
  }

  const busy = update.isPending || voidM.isPending

  return (
    <div className="mt-2" onClick={(e) => e.stopPropagation()}>
      {mode === null && (
        <div className="flex items-center gap-2">
          {canEdit && (
            <button
              type="button"
              onClick={() => { setForm(values); setMode('edit') }}
              className="flex items-center gap-1 rounded-lg bg-slate-100 dark:bg-slate-700 px-2.5 py-1 text-[11px] font-bold text-slate-600 dark:text-slate-200 active:scale-95"
            >
              <Pencil className="h-3 w-3" /> تعديل
            </button>
          )}
          {canVoid && (
            <button
              type="button"
              onClick={() => setMode('void')}
              className="flex items-center gap-1 rounded-lg bg-rose-50 dark:bg-rose-900/30 px-2.5 py-1 text-[11px] font-bold text-rose-600 dark:text-rose-300 active:scale-95"
            >
              <Ban className="h-3 w-3" /> إلغاء
            </button>
          )}
        </div>
      )}

      {mode === 'edit' && (
        <form onSubmit={submitEdit} className="space-y-2 rounded-xl border border-slate-100 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60 p-3">
          {entryType === 'note' ? (
            <textarea
              required
              rows={3}
              value={form.note_text ?? ''}
              onChange={(e) => setForm({ ...form, note_text: e.target.value })}
              className={inputCls}
            />
          ) : entryType === 'temperature_log' ? (
            <label className="block text-[11px] font-bold text-slate-500 dark:text-slate-400">
              درجة الحرارة
              <input type="number" inputMode="decimal" step="0.1" required value={form.temperature ?? ''} onChange={(e) => setForm({ ...form, temperature: e.target.value === '' ? null : Number(e.target.value) })} className={cn(inputCls, 'mt-1')} />
            </label>
          ) : (
            <label className="block text-[11px] font-bold text-slate-500 dark:text-slate-400">
              الكمية
              <input
                type="number"
                inputMode="decimal"
                min={entryType === 'mortality' ? 1 : 0.001}
                step={entryType === 'mortality' ? 1 : 'any'}
                required
                value={form.quantity ?? ''}
                onChange={(e) => setForm({ ...form, quantity: e.target.value === '' ? null : Number(e.target.value) })}
                className={cn(inputCls, 'mt-1')}
              />
            </label>
          )}
          {entryType === 'mortality' && (
            <input placeholder="سبب النفوق (اختياري)" value={form.reason ?? ''} onChange={(e) => setForm({ ...form, reason: e.target.value })} className={inputCls} />
          )}
          <input placeholder="سبب التعديل (اختياري)" value={editReason} onChange={(e) => setEditReason(e.target.value)} maxLength={500} className={inputCls} />
          <div className="flex gap-2">
            <button type="submit" disabled={busy} className="flex-1 flex items-center justify-center gap-1 rounded-xl bg-primary-600 py-2 text-xs font-bold text-white disabled:opacity-50">
              {update.isPending && <Loader2 className="h-3.5 w-3.5 animate-spin" />} حفظ التعديل
            </button>
            <button type="button" onClick={() => setMode(null)} className="rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-xs font-bold text-slate-500 dark:text-slate-300">رجوع</button>
          </div>
        </form>
      )}

      {mode === 'void' && (
        <form
          onSubmit={(e) => { e.preventDefault(); if (voidReason.trim().length >= 3) voidM.mutate() }}
          className="space-y-2 rounded-xl border border-rose-100 dark:border-rose-900/50 bg-rose-50/60 dark:bg-rose-900/20 p-3"
        >
          <p className="text-[11px] font-bold text-rose-600 dark:text-rose-300">سيُستثنى السجل من كل المجاميع ويبقى في سجل النشاطات.</p>
          <input
            required
            minLength={3}
            maxLength={500}
            placeholder="سبب الإلغاء (مطلوب)"
            value={voidReason}
            onChange={(e) => setVoidReason(e.target.value)}
            className={inputCls}
          />
          <div className="flex gap-2">
            <button type="submit" disabled={busy || voidReason.trim().length < 3} className="flex-1 flex items-center justify-center gap-1 rounded-xl bg-rose-600 py-2 text-xs font-bold text-white disabled:opacity-50">
              {voidM.isPending && <Loader2 className="h-3.5 w-3.5 animate-spin" />} تأكيد الإلغاء
            </button>
            <button type="button" onClick={() => setMode(null)} className="rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-xs font-bold text-slate-500 dark:text-slate-300">رجوع</button>
          </div>
        </form>
      )}
    </div>
  )
}

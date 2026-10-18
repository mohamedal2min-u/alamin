// إرسال الإدخال اليومي مع مفتاح منع التكرار، وحفظه في صندوق الانتظار عند فشل الشبكة
// إعادة الإرسال فقط عند: عودة الاتصال (online)، عودة التطبيق للواجهة، فتح التطبيق — بلا مؤقتات

import type { QueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { apiClient } from '@/lib/api/client'
import { useAuthStore } from '@/stores/auth.store'
import {
  enqueue, loadOutbox, markAttempt, markFailed, markPending, markSent, newClientId,
  outboxKey, pendingItems, saveOutbox, shouldRetry,
  type DailyEntryKind, type OutboxItem, type StorageLike,
} from './outbox'

const REQUEST_TIMEOUT_MS = 20_000

const PATHS: Record<DailyEntryKind, (flockId: number) => string> = {
  mortality:   (id) => `/flocks/${id}/mortalities`,
  feed:        (id) => `/flocks/${id}/feed-logs`,
  medicine:    (id) => `/flocks/${id}/medicine-logs`,
  water:       (id) => `/flocks/${id}/water-logs`,
  note:        (id) => `/flocks/${id}/notes`,
  temperature: (id) => `/flocks/${id}/temperature-logs`,
}

export const KIND_LABELS: Record<DailyEntryKind, string> = {
  mortality: 'نفوق', feed: 'علف', medicine: 'دواء', water: 'مياه', note: 'ملاحظة', temperature: 'حرارة',
}

/** الحقل الرقمي الأساسي الذي يمكن تصحيحه من صندوق الانتظار */
export const EDITABLE_FIELD: Partial<Record<DailyEntryKind, string>> = {
  mortality: 'quantity', feed: 'quantity', medicine: 'quantity', water: 'quantity', temperature: 'temperature',
}

function invalidateFor(qc: QueryClient, kind: DailyEntryKind, flockId: number) {
  const keys: unknown[][] = [
    ['today-summary', flockId],
    ['flock-history', flockId],
    ['flocks'],
  ]
  if (kind === 'mortality') keys.push(['mortalities', flockId])
  if (kind === 'feed') keys.push(['feed-logs', flockId], ['inventory-items'], ['inventory-overview'])
  if (kind === 'medicine') keys.push(['medicine-logs', flockId], ['inventory-items'], ['inventory-overview'])
  if (kind === 'water') keys.push(['water-logs', flockId])
  if (kind === 'note') keys.push(['flock-notes', flockId])
  keys.forEach((queryKey) => qc.invalidateQueries({ queryKey }))
}

// ── أخطاء axios ──────────────────────────────────────────────────────────────
interface AxiosLikeError {
  response?: { status?: number; data?: { message?: unknown; errors?: Record<string, string[]> } }
}

export function errorStatus(err: unknown): number | undefined {
  return (err as AxiosLikeError)?.response?.status
}

export function errorMessage(err: unknown): string {
  const data = (err as AxiosLikeError)?.response?.data
  const first = data?.errors ? Object.values(data.errors)[0]?.[0] : null
  if (first) return first
  return typeof data?.message === 'string' ? data.message : 'خطأ في البيانات'
}

// ── التخزين والاشتراك ─────────────────────────────────────────────────────────
function storage(): StorageLike | null {
  try {
    return typeof window !== 'undefined' ? window.localStorage : null
  } catch {
    return null
  }
}

interface Scope { key: string; farmId: string }

function currentScope(): Scope | null {
  const userId = useAuthStore.getState().user?.id
  let farmId: string | null = null
  try {
    farmId = storage()?.getItem('current_farm_id') ?? null
  } catch {
    farmId = null
  }
  if (!userId || !farmId) return null
  return { key: outboxKey(userId, farmId), farmId }
}

const EMPTY: OutboxItem[] = []
let cache: { key: string | null; items: OutboxItem[] } = { key: null, items: EMPTY }
const listeners = new Set<() => void>()

function readKey(key: string): OutboxItem[] {
  if (cache.key !== key) cache = { key, items: loadOutbox(storage(), key) }
  return cache.items
}

function write(key: string, fn: (list: OutboxItem[]) => OutboxItem[]) {
  const items = fn(readKey(key))
  cache = { key, items }
  saveOutbox(storage(), key, items)
  listeners.forEach((l) => l())
}

/** للاستخدام مع useSyncExternalStore */
export function subscribeOutbox(listener: () => void): () => void {
  listeners.add(listener)
  const onStorage = (e: StorageEvent) => {
    if (e.key && e.key === cache.key) {
      cache = { key: null, items: EMPTY }
      listener()
    }
  }
  if (typeof window !== 'undefined') window.addEventListener('storage', onStorage)
  return () => {
    listeners.delete(listener)
    if (typeof window !== 'undefined') window.removeEventListener('storage', onStorage)
  }
}

export function getOutboxSnapshot(): OutboxItem[] {
  const scope = currentScope()
  return scope ? readKey(scope.key) : EMPTY
}

export function getServerOutboxSnapshot(): OutboxItem[] {
  return EMPTY
}

// ── الإرسال ───────────────────────────────────────────────────────────────────
function send<T>(item: OutboxItem, farmId: string): Promise<T> {
  return apiClient
    .post<T>(PATHS[item.kind](item.flockId), item.payload, {
      headers: { 'Idempotency-Key': item.id, 'X-Farm-Id': farmId },
      timeout: REQUEST_TIMEOUT_MS,
    })
    .then((r) => r.data)
}

export type SubmitResult<T> = { queued: false; data: T } | { queued: true }

/**
 * يرسل الإدخال اليومي بمفتاح UUID. عند فشل الشبكة/مهلة/5xx يحفظه في صندوق الانتظار ويعيد { queued: true }.
 * أخطاء التحقق (4xx) تُرمى كما هي ليعرضها النموذج.
 */
export async function submitDailyEntry<T = unknown>(
  kind: DailyEntryKind,
  flockId: number,
  payload: object,
  label: string,
): Promise<SubmitResult<T>> {
  const scope = currentScope()
  const item: OutboxItem = {
    id: newClientId(),
    kind,
    flockId,
    payload: JSON.parse(JSON.stringify(payload)) as Record<string, unknown>, // يحذف undefined
    label,
    status: 'pending',
    attempts: 0,
    createdAt: Date.now(),
  }
  const offline = typeof navigator !== 'undefined' && navigator.onLine === false

  if (!offline || !scope) {
    try {
      const data = await send<T>(item, scope?.farmId ?? '')
      return { queued: false, data }
    } catch (err) {
      if (!scope || !shouldRetry(errorStatus(err))) throw err
      item.attempts = 1
    }
  }

  if (!scope) throw new Error('no outbox scope')
  write(scope.key, (l) => enqueue(l, item))
  return { queued: true }
}

let flushing = false

/** يرسل العناصر المنتظرة بالترتيب؛ يتوقف عند أول فشل شبكة */
export async function flushOutbox(qc: QueryClient): Promise<void> {
  if (flushing) return
  if (typeof navigator !== 'undefined' && navigator.onLine === false) return
  const scope = currentScope()
  if (!scope || pendingItems(readKey(scope.key)).length === 0) return

  flushing = true
  let sent = 0
  let failed = 0
  try {
    for (const item of pendingItems(readKey(scope.key))) {
      try {
        await send(item, scope.farmId)
        write(scope.key, (l) => markSent(l, item.id))
        invalidateFor(qc, item.kind, item.flockId)
        sent++
      } catch (err) {
        if (shouldRetry(errorStatus(err))) {
          write(scope.key, (l) => markAttempt(l, item.id))
          break
        }
        write(scope.key, (l) => markFailed(l, item.id, errorMessage(err)))
        failed++
      }
    }
  } finally {
    flushing = false
  }

  if (sent > 0) toast.success(`تم إرسال ${sent} من الإدخالات المنتظرة`)
  if (failed > 0) toast.error(`فشل إرسال ${failed} إدخال — راجع صندوق الانتظار`)
}

/** «إعادة المحاولة» (مع تصحيح اختياري للبيانات) */
export function retryOutboxItem(qc: QueryClient, id: string, patch?: { payload?: Record<string, unknown>; label?: string }) {
  const scope = currentScope()
  if (!scope) return Promise.resolve()
  write(scope.key, (l) => markPending(l, id, patch))
  return flushOutbox(qc)
}

/** «حذف» */
export function deleteOutboxItem(id: string) {
  const scope = currentScope()
  if (!scope) return
  write(scope.key, (l) => markSent(l, id))
}

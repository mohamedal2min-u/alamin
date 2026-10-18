// صندوق الانتظار للإدخال اليومي (عند ضعف الشبكة): منطق نقي بلا React ولا axios
// كل دالة تأخذ القائمة وتعيد قائمة جديدة، والتخزين عبر Storage مُمرَّر (localStorage في المتصفح)

export type DailyEntryKind = 'mortality' | 'feed' | 'medicine' | 'water' | 'note' | 'temperature'

export type OutboxStatus = 'pending' | 'failed'

export interface OutboxItem {
  /** UUID يُرسَل كـ Idempotency-Key — ثابت لكل إعادة محاولة */
  id: string
  kind: DailyEntryKind
  flockId: number
  payload: Record<string, unknown>
  /** وصف قصير للعرض، مثل: نفوق 5 طير */
  label: string
  status: OutboxStatus
  attempts: number
  /** رسالة الخادم عند الفشل (4xx) */
  error?: string
  createdAt: number
}

export interface StorageLike {
  getItem(key: string): string | null
  setItem(key: string, value: string): void
  removeItem(key: string): void
  readonly length: number
  key(index: number): string | null
}

/** حد أعلى ليبقى المخزَّن صغيراً */
export const OUTBOX_MAX_ITEMS = 200

const KEY_PREFIX = 'ymd_outbox:'

export function outboxKey(userId: number | string, farmId: number | string): string {
  return `${KEY_PREFIX}${userId}:${farmId}`
}

export function enqueue(list: OutboxItem[], item: OutboxItem): OutboxItem[] {
  if (list.some((i) => i.id === item.id)) return list
  const next = [...list, item]
  return next.length > OUTBOX_MAX_ITEMS ? next.slice(next.length - OUTBOX_MAX_ITEMS) : next
}

/** نجح الإرسال → يُحذف من الصندوق */
export function markSent(list: OutboxItem[], id: string): OutboxItem[] {
  return list.filter((i) => i.id !== id)
}

/** خطأ تحقق من الخادم → يبقى في الصندوق كـ «فشل» مع رسالة الخادم */
export function markFailed(list: OutboxItem[], id: string, error: string): OutboxItem[] {
  return list.map((i) => (i.id === id ? { ...i, status: 'failed', error, attempts: i.attempts + 1 } : i))
}

/** محاولة فاشلة بسبب الشبكة → يبقى «بانتظار الإرسال» */
export function markAttempt(list: OutboxItem[], id: string): OutboxItem[] {
  return list.map((i) => (i.id === id ? { ...i, attempts: i.attempts + 1 } : i))
}

/** إعادة المحاولة يدوياً (مع تصحيح اختياري للبيانات) → يعود «بانتظار الإرسال» */
export function markPending(
  list: OutboxItem[],
  id: string,
  patch?: { payload?: Record<string, unknown>; label?: string },
): OutboxItem[] {
  return list.map((i) => {
    if (i.id !== id) return i
    return {
      ...i,
      error: undefined,
      status: 'pending',
      payload: patch?.payload ? { ...i.payload, ...patch.payload } : i.payload,
      label: patch?.label ?? i.label,
    }
  })
}

export function pendingItems(list: OutboxItem[]): OutboxItem[] {
  return list.filter((i) => i.status === 'pending')
}

/**
 * هل يُعاد الإرسال لاحقاً؟ status = undefined تعني لا رد (انقطاع/مهلة).
 * 401/419: الجلسة ستُجدَّد، 408/429: مهلة/ضغط، 409: نفس الطلب قيد التنفيذ، 5xx: خطأ خادم.
 * بقية 4xx (تحقق من البيانات) لا تُعاد تلقائياً.
 */
export function shouldRetry(status: number | undefined | null): boolean {
  if (status === undefined || status === null || status === 0) return true
  if (status >= 500) return true
  return status === 401 || status === 408 || status === 409 || status === 419 || status === 429
}

// ── التخزين (مغلَّف بـ try/catch: وضع خاص أو تخزين ممتلئ) ─────────────────────

function isItem(x: unknown): x is OutboxItem {
  if (!x || typeof x !== 'object') return false
  const o = x as Record<string, unknown>
  return typeof o.id === 'string' && typeof o.kind === 'string' && typeof o.flockId === 'number'
    && (o.status === 'pending' || o.status === 'failed') && !!o.payload && typeof o.payload === 'object'
}

export function loadOutbox(storage: StorageLike | null | undefined, key: string): OutboxItem[] {
  if (!storage) return []
  try {
    const raw = storage.getItem(key)
    if (!raw) return []
    const parsed: unknown = JSON.parse(raw)
    return Array.isArray(parsed) ? parsed.filter(isItem) : []
  } catch {
    return []
  }
}

export function saveOutbox(storage: StorageLike | null | undefined, key: string, list: OutboxItem[]): void {
  if (!storage) return
  try {
    if (list.length === 0) storage.removeItem(key)
    else storage.setItem(key, JSON.stringify(list))
  } catch {
    // تجاهل: التخزين غير متاح
  }
}

/** عند تسجيل الخروج: حذف كل صناديق الانتظار من هذا الجهاز */
export function clearAllOutboxes(storage: StorageLike | null | undefined): void {
  if (!storage) return
  try {
    const keys: string[] = []
    for (let i = 0; i < storage.length; i++) {
      const k = storage.key(i)
      if (k && k.startsWith(KEY_PREFIX)) keys.push(k)
    }
    keys.forEach((k) => storage.removeItem(k))
  } catch {
    // تجاهل
  }
}

export function newClientId(): string {
  const c = (globalThis as { crypto?: Crypto }).crypto
  if (c && typeof c.randomUUID === 'function') return c.randomUUID()
  // بديل لسياق غير آمن: UUID v4
  const bytes = new Uint8Array(16)
  if (c && typeof c.getRandomValues === 'function') c.getRandomValues(bytes)
  else for (let i = 0; i < 16; i++) bytes[i] = Math.floor(Math.random() * 256)
  bytes[6] = (bytes[6] & 0x0f) | 0x40
  bytes[8] = (bytes[8] & 0x3f) | 0x80
  const h = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('')
  return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`
}

/** ترويسة مفتاح منع التكرار لطلب إدخال يومي واحد */
export function withIdempotencyKey(key: string = newClientId()): { headers: Record<string, string> } {
  return { headers: { 'Idempotency-Key': key } }
}

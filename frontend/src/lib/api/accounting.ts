import { apiClient } from './client'

export interface CategoryBreakdown {
  code: string
  name: string
  count: number
  total_amount: number
  remaining_amount: number
  // معلومات شاملة للفئة
  paid_amount?: number
  quantity?: number
  quantity_unit?: string | null
  computed_quantity?: number
  priced_count?: number
  unpriced_count?: number
  avg_unit_price?: number | null
}

export interface ReviewSummary {
  unpaid_count: number
  partial_payment_count: number
  missing_price_count: number
  missing_payment_status_count: number
  inconsistent_financial_state_count: number
  blocking_flock_closure_count: number
  category_breakdown: CategoryBreakdown[]
}

export type ReviewReason =
  | 'unpaid'
  | 'partial_payment'
  | 'missing_price'
  | 'missing_payment_status'
  | 'inconsistent_financial_state'
  | 'blocking_flock_closure'

export interface ReviewItem {
  id: string
  type: 'expense' | 'sale' | 'water_log'
  record_id: number
  flock_id: number | null
  flock_name: string | null
  flock_status: string | null
  entry_date: string | null
  description: string | null
  total_amount: number
  paid_amount: number
  remaining_amount: number
  payment_status: 'paid' | 'partial' | 'unpaid' | null
  unit_price: number | null
  quantity?: number | null
  quantity_unit?: string | null
  computed_quantity?: number | null
  item_type?: string | null
  item_id?: number | null
  item_name?: string | null
  category_name?: string | null
  category_code?: string | null
  review_reasons: ReviewReason[]
  price_history?: PriceHistoryEntry[]
}

/** old_price = null يعني أول تسعير؛ غير ذلك هو سعر قديم يُعرض مشطوباً ولا يُحذف. */
export interface PriceHistoryEntry {
  old_price: number | null
  new_price: number | null
  by: string | null
  at: string | null
}

export interface PriceAuditEntry extends PriceHistoryEntry {
  id: number
  record_type: 'expense' | 'water_log' | 'sale'
  record_id: number
  description: string | null
  is_edit: boolean
}

export interface ShareAuditEntry {
  id: number
  partner: string | null
  old_percent: number | null
  new_percent: number
  is_edit: boolean
  by: string | null
  at: string | null
}

export interface PaymentAuditEntry {
  id: number
  record_type: 'expense' | 'water_log' | 'sale'
  record_id: number
  description: string | null
  old_amount: number | null
  new_amount: number
  by: string | null
  at: string | null
}

export interface ReviewQueueResponse {
  summary: ReviewSummary
  data: ReviewItem[]
  meta: {
    total: number
    current_page: number
    per_page: number
  }
}

export interface ReviewQueueFilters {
  type?: 'expense' | 'sale' | 'water_log' | 'all'
  reason?: ReviewReason
  flock_id?: number | string
  page?: number
  per_page?: number
  filter?: string
  category?: string
  /** all = كل السجلات بما فيها المسعّرة (لتعديل سعر موضوع مسبقاً) */
  scope?: 'all'
}

// Single source of truth for Arabic badge labels
export const REASON_LABELS: Record<ReviewReason, string> = {
  unpaid:                        'غير مدفوع',
  partial_payment:               'دفع جزئي',
  missing_price:                 'ناقص السعر',
  missing_payment_status:        'ناقص حالة الدفع',
  inconsistent_financial_state:  'تناقض مالي',
  blocking_flock_closure:        'مانع إغلاق',
}

export const accountingApi = {
  getReviewQueue: async (filters: ReviewQueueFilters = {}): Promise<ReviewQueueResponse> => {
    const params = new URLSearchParams()

    if (filters.type) params.set('type', filters.type)
    if (filters.flock_id) params.set('flock_id', String(filters.flock_id))
    if (filters.page) params.set('page', String(filters.page))
    if (filters.per_page) params.set('per_page', String(filters.per_page))

    if (filters.filter === 'blocking') {
      params.set('reason', 'blocking_flock_closure')
    } else if (filters.reason) {
      params.set('reason', filters.reason)
    }

    if (filters.category) params.set('category', filters.category)
    if (filters.scope) params.set('scope', filters.scope)

    const { data } = await apiClient.get<ReviewQueueResponse>(
      `/accounting/review-queue?${params.toString()}`
    )
    return data
  },

  getPaymentAudit: async (params: { page?: number; per_page?: number } = {}) => {
    const q = new URLSearchParams()
    if (params.page) q.set('page', String(params.page))
    if (params.per_page) q.set('per_page', String(params.per_page))
    const { data } = await apiClient.get<{
      data: PaymentAuditEntry[]
      meta: { total: number; current_page: number; per_page: number }
    }>(`/accounting/payment-audit?${q.toString()}`)
    return data
  },

  getShareAudit: async (params: { page?: number; per_page?: number } = {}) => {
    const q = new URLSearchParams()
    if (params.page) q.set('page', String(params.page))
    if (params.per_page) q.set('per_page', String(params.per_page))
    const { data } = await apiClient.get<{
      data: ShareAuditEntry[]
      meta: { total: number; current_page: number; per_page: number }
    }>(`/accounting/share-audit?${q.toString()}`)
    return data
  },

  getPriceAudit: async (params: { edits_only?: boolean; page?: number; per_page?: number } = {}) => {
    const q = new URLSearchParams()
    if (params.edits_only) q.set('edits_only', '1')
    if (params.page) q.set('page', String(params.page))
    if (params.per_page) q.set('per_page', String(params.per_page))
    const { data } = await apiClient.get<{
      data: PriceAuditEntry[]
      meta: { total: number; current_page: number; per_page: number }
    }>(`/accounting/price-audit?${q.toString()}`)
    return data
  },

  updateReviewItem: async (
    type: 'expense' | 'sale' | 'water_log',
    id: number,
    payload: { paid_amount?: number; unit_price?: number }
  ): Promise<ReviewItem> => {
    const { data } = await apiClient.patch<ReviewItem>(
      `/accounting/review-queue/${type}/${id}`,
      payload
    )
    return data
  },
}

import { apiClient } from './client'

export interface ReviewSummary {
  unpaid_count: number
  partial_payment_count: number
  missing_price_count: number
  missing_payment_status_count: number
  inconsistent_financial_state_count: number
  blocking_flock_closure_count: number
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
  type: 'expense' | 'sale'
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
  quantity: number | null
  review_reasons: ReviewReason[]
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
  type?: 'expense' | 'sale' | 'all'
  reason?: ReviewReason
  flock_id?: number | string
  page?: number
  per_page?: number
  filter?: string
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

    const { data } = await apiClient.get<ReviewQueueResponse>(
      `/accounting/review-queue?${params.toString()}`
    )
    return data
  },

  updateReviewItem: async (
    type: 'expense' | 'sale',
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

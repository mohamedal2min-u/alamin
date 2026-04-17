import { apiClient } from './client'

export interface FeedLogPayload {
  item_id: number
  quantity: number
  entry_date: string
}

export interface MedicineLogPayload {
  item_id: number
  quantity: number
  entry_date: string
}

export interface ExpensePayload {
  expense_type: string
  quantity: number
  unit_price?: number
  total_amount?: number
  entry_date: string
  description?: string
  notes?: string
}

export const quickEntryApi = {
  logFeed: (flockId: number, payload: FeedLogPayload) =>
    apiClient
      .post<{ message: string }>(`/flocks/${flockId}/feed-logs`, payload)
      .then((r) => r.data),

  logMedicine: (flockId: number, payload: MedicineLogPayload) =>
    apiClient
      .post<{ message: string }>(`/flocks/${flockId}/medicine-logs`, payload)
      .then((r) => r.data),

  logExpense: (flockId: number, payload: ExpensePayload) =>
    apiClient
      .post<{ message: string }>(`/flocks/${flockId}/expenses`, payload)
      .then((r) => r.data),
}

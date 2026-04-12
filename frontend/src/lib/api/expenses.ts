import { apiClient } from './client'

export interface ExpenseItem {
  id: number
  entry_date: string
  expense_type: string
  total_amount: number
  paid_amount: number
  remaining_amount: number
  payment_status: string
  flock_name: string | null
  category_name: string | null
  description: string | null
  notes: string | null
}

export interface ExpenseListResponse {
  data: ExpenseItem[]
  meta: { total_amount: number; count: number }
}

export const expensesApi = {
  list: () =>
    apiClient.get<ExpenseListResponse>('/expenses').then((r) => r.data),
}

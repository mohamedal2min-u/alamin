import { apiClient } from './client'

export interface ExpenseItem {
  id: number
  entry_date: string
  expense_type: string | null
  total_amount: number
  paid_amount: number
  remaining_amount: number
  payment_status: string
  flock_name: string | null
  category_name: string | null
  category_code: string | null
  description: string | null
  notes: string | null
  quantity: number | null
  unit_price: number | null
}

export interface ExpenseListResponse {
  data: ExpenseItem[]
  meta: { total_amount: number; count: number; unpaid_total: number }
}

export interface ExpenseCategory {
  id: number
  name: string
  code: string | null
  is_system: boolean
}

export interface CreateExpensePayload {
  expense_category_id: number
  flock_id?: number | null
  entry_date: string
  quantity: number
  unit_price?: number | null
  total_amount: number
  paid_amount?: number
  payment_status?: 'paid' | 'partial' | 'unpaid'
  description?: string
  notes?: string
  expense_type?: string
}

export const expensesApi = {
  list: (flockId?: number) =>
    apiClient.get<ExpenseListResponse>('/expenses', { params: { flock_id: flockId } }).then((r) => r.data),

  create: (payload: CreateExpensePayload) =>
    apiClient
      .post<{ message: string; data: { id: number; entry_date: string; total_amount: number } }>('/expenses', payload)
      .then((r) => r.data),

  categories: () =>
    apiClient.get<{ data: ExpenseCategory[] }>('/expense-categories').then((r) => r.data),
}

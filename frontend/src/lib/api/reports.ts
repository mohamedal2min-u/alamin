import { apiClient } from './client'

export interface SummaryKpis {
  total_flocks_count: number
  active_flock_name: string | null
  active_flock_id: number | null
  total_sales: number
  total_expenses: number
  net_profit: number
  inventory_value: number
  currency: string
}

export interface FlockReport {
  flock_info: {
    id: number
    name: string
    start_date: string | null
    close_date: string | null
    status: string
    initial_count: number
    age_days: number
  }
  performance: {
    mortality_count: number
    mortality_rate: number
    remaining_birds: number
    total_feed_kg: number
    total_medicine_cost: number
  }
  sales_analytics: {
    birds_sold: number
    total_weight_kg: number
    avg_bird_weight_kg: number
  }
  financial: {
    total_sales: number
    total_expenses: number
    profit_loss: number
    is_profitable: boolean
    profit_status_label: string
  }
}

export interface AccountingSummary {
  summary: {
    total_sales: number
    total_expenses: number
    net_profit: number
  }
  cash_flow: {
    total_received: number
    total_paid: number
    balance: number
  }
  debts: {
    receivables: number
    payables: number
  }
  expense_breakdown: Array<{
    category: string
    amount: number
  }>
  currency: string
}

export const reportsApi = {
  getSummaryKpis: async () => {
    const { data } = await apiClient.get<SummaryKpis>('/reports/summary-kpis')
    return data
  },

  getFlockReport: async (flockId: number) => {
    const { data } = await apiClient.get<FlockReport>(`/reports/flock-report?flock_id=${flockId}`)
    return data
  },

  getAccountingSummary: async (params?: { flock_id?: number; start_date?: string; end_date?: string }) => {
    const { data } = await apiClient.get<AccountingSummary>('/reports/accounting-summary', { params })
    return data
  },

  getInventoryReport: async () => {
    const { data } = await apiClient.get<any>('/reports/inventory-report')
    return data
  },

  getPartnersReport: async () => {
    const { data } = await apiClient.get<any>('/reports/partners-report')
    return data
  },

  getWorkersReport: async () => {
    const { data } = await apiClient.get<any>('/reports/workers-report')
    return data
  },

  getDailyReport: async (date?: string) => {
    const { data } = await apiClient.get<any>('/reports/daily-report', { params: { date } })
    return data
  },
}

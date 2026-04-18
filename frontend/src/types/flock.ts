export type FlockStatus = 'active' | 'draft' | 'closed' | 'cancelled'

export interface Flock {
  id: number
  farm_id: number
  name: string
  status: FlockStatus
  start_date: string           // ISO date: "YYYY-MM-DD"
  end_date: string | null
  initial_count: number
  chick_unit_price: number | null
  total_chick_cost: number | null
  total_mortality: number
  remaining_count: number
  total_sales: number
  total_expenses: number
  net_profit: number
  current_age_days: number | null
  notes: string | null
  created_at: string
  updated_at: string
}

export interface CreateFlockPayload {
  name: string
  start_date: string
  initial_count: number
  chick_unit_price?: number | null
  chick_paid_amount?: number
  notes?: string
}

export interface FlockListResponse {
  data: Flock[]
  meta?: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

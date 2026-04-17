export interface WaterLog {
  id: number
  flock_id: number
  entry_date: string        // YYYY-MM-DD
  quantity: number | null
  unit_label: string | null
  total_amount: number | null
  paid_amount: number | null
  payment_status: 'paid' | 'partial' | 'unpaid' | null
  notes: string | null
  created_at: string
}

export interface CreateWaterLogPayload {
  quantity?: number
  entry_date?: string
  unit_label?: string
  total_amount?: number
  paid_amount?: number
  payment_status?: 'paid' | 'partial' | 'unpaid'
  notes?: string
}

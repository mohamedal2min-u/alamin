export interface WaterLog {
  id: number
  flock_id: number
  entry_date: string        // YYYY-MM-DD
  quantity: number | null
  unit_label: string | null
  notes: string | null
  created_at: string
}

export interface CreateWaterLogPayload {
  quantity?: number
  entry_date?: string
  unit_label?: string
  notes?: string
}

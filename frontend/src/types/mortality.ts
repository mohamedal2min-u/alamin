// frontend/src/types/mortality.ts

export interface Mortality {
  id: number
  flock_id: number
  entry_date: string      // "YYYY-MM-DD"
  quantity: number
  reason: string | null
  notes: string | null
  created_at: string
  updated_at: string
}

export interface CreateMortalityPayload {
  entry_date: string
  quantity: number
  reason?: string
  notes?: string
}

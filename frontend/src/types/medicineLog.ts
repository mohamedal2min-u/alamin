export interface MedicineLog {
  id: number
  flock_id: number
  item_id: number
  item_name: string | null
  item_input_unit: string | null
  entry_date: string        // YYYY-MM-DD
  quantity: number
  unit_label: string | null
  notes: string | null
  inventory_linked: boolean
  created_at: string
}

export interface CreateMedicineLogPayload {
  item_id: number
  quantity: number
  entry_date?: string
  unit_label?: string
  notes?: string
}

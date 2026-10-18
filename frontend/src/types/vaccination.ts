export type VaccineMethod = 'drinking_water' | 'spray' | 'eye_drop' | 'injection'
export type VaccinationStatus = 'due' | 'done' | 'skipped'

export const VACCINE_METHOD_LABELS: Record<VaccineMethod, string> = {
  drinking_water: 'ماء الشرب',
  spray: 'رش',
  eye_drop: 'قطرة عين',
  injection: 'حقن',
}

export interface VaccineTemplateItem {
  id?: number
  age_day: number
  title: string
  method: VaccineMethod
  notes: string | null
  withdrawal_days: number | null
}

export interface VaccineTemplate {
  id: number
  name: string
  is_active: boolean
  /** القالب الافتراضي «مثال» لم يُعدَّل بعد */
  is_example: boolean
  items: VaccineTemplateItem[]
}

export interface FlockVaccination {
  id: number
  flock_id: number
  age_day: number
  due_date: string // YYYY-MM-DD
  title: string
  method: VaccineMethod
  notes: string | null
  status: VaccinationStatus
  done_at: string | null
  done_by: { id: number; name: string } | null
  note: string | null
  withdrawal_days: number | null
}

export interface WithdrawalInfo {
  /** أول تاريخ يُسمح فيه بالبيع (YYYY-MM-DD) أو null إن لا فترة سحب */
  no_sale_before: string | null
  source: string | null
}

export interface ActiveVaccinations {
  flock: { id: number; name: string; start_date: string } | null
  data: FlockVaccination[]
}

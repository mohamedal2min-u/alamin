// frontend/src/types/sale.ts

export interface SaleItem {
  id: number
  sale_id: number
  flock_id: number
  birds_count: number
  total_weight_kg: number       // الوزن الصافي (بعد طرح وزن الأقفاص) — المستخدم في التسعير
  crates_count: number | null
  crate_weight_kg: number | null
  gross_weight_kg: number | null // الوزن القائم كما يُقرأ من الميزان (يشمل الأقفاص)
  avg_weight_kg: number | null
  unit_price_per_kg: number | null
  line_total: number
  notes: string | null
  created_at: string
  updated_at: string
}

export interface Sale {
  id: number
  farm_id: number
  flock_id: number
  sale_date: string        // "YYYY-MM-DD"
  reference_no: string | null
  buyer_name: string | null
  vehicle_weight_before_kg: number | null // وزن السيارة الأول (قبل التحميل)
  vehicle_weight_after_kg: number | null  // وزن السيارة الثاني (بعد التحميل)
  weight_deduction_kg: number | null      // خصم يدوي إضافي من الوزن الصافي
  gross_amount: number
  discount_amount: number
  net_amount: number
  received_amount: number
  remaining_amount: number
  payment_status: 'paid' | 'partial' | 'debt'
  notes: string | null
  items: SaleItem[]
  created_at: string
  updated_at: string
}

// ── Payloads ──────────────────────────────────────────────────────────────────

export interface CreateSaleItemPayload {
  birds_count: number
  total_weight_kg: number
  crates_count?: number
  crate_weight_kg?: number
  gross_weight_kg?: number
  unit_price_per_kg?: number
  notes?: string
}

export interface CreateSalePayload {
  sale_date: string
  buyer_name?: string
  reference_no?: string
  vehicle_weight_before_kg?: number
  vehicle_weight_after_kg?: number
  weight_deduction_kg?: number
  discount_amount?: number
  received_amount?: number
  notes?: string
  items: CreateSaleItemPayload[]
}

export interface UpdatePaymentPayload {
  received_amount: number
  notes?: string
}

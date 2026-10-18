import { apiClient } from './client'
import type { InventoryItem } from '@/types/dashboard'

export interface StockItem {
  id: number
  name: string
  type_code: string
  type_name: string | null
  barcode?: string | null
  input_unit: string
  content_unit: string
  unit_value: number
  minimum_stock: number
  /** فترة سحب الدواء بالأيام (للأدوية فقط) */
  withdrawal_days?: number | null
  total_quantity: number
  total_received?: number
  shipment_count?: number
  last_shipment_date?: string | null
  notes?: string | null
}

export interface InventorySummary {
  feed_quantity: number
  feed_unit: string
  feed_total_received: number
  medicine_quantity: number
  medicine_unit: string
  low_stock_count: number
  last_shipment_date: string | null
  total_value: number
  water_tanks_count: number
  water_tanks_cost: number
  today_expenses_total: number
  all_expenses_total: number
}

export interface Warehouse {
  id: number
  name: string
  location: string | null
}

export interface ItemType {
  id: number
  name: string
  code: string | null
  is_system: boolean
}

export interface InventoryTransaction {
  id: number
  transaction_date: string
  item_name: string | null
  item_type_code: string | null
  item_type_name: string | null
  transaction_type: string
  direction: string
  original_quantity: number
  computed_quantity: number
  input_unit: string | null
  content_unit: string | null
  unit_price: number | null
  total_amount: number | null
  payment_status: string | null
  supplier_name: string | null
  invoice_no: string | null
  reference_no: string | null
  flock_name: string | null
  warehouse_name: string | null
  created_by_name: string | null
  notes: string | null
  attachment_path: string | null
}

export interface AddShipmentPayload {
  item_id: number
  warehouse_id: number
  transaction_date: string
  original_quantity: number
  unit_price?: number | null
  total_amount?: number | null
  paid_amount?: number | null
  payment_status?: 'paid' | 'unpaid'
  flock_id?: number
  supplier_name?: string | null
  invoice_no?: string | null
  notes?: string | null
  attachment?: File | null
}

/** معلومات منتج مقترحة من الباركود (قواعد مفتوحة) — كلها قابلة للتعديل */
export interface BarcodeSuggestion {
  name: string
  section: 'feed' | 'medicine' | null
  weight_kg: number | null
  unit: string | null
  quantity_text: string | null
}

export interface CreateItemPayload {
  item_type_id: number
  name: string
  barcode?: string | null
  input_unit: string
  unit_value: number
  content_unit: string
  minimum_stock?: number | null
  default_cost?: number | null
  withdrawal_days?: number | null
  notes?: string | null
}

export interface InventoryOverview {
  stock: StockItem[]
  summary: InventorySummary
  warehouses: Warehouse[]
  item_types: ItemType[]
  transactions: InventoryTransaction[]
}

export const inventoryApi = {
  /** طلب واحد يرجع كل بيانات صفحة المخزون */
  overview: () =>
    apiClient
      .get<{ data: InventoryOverview }>('/inventory/overview')
      .then((r) => r.data),

  items: (type?: 'feed' | 'medicine') =>
    apiClient
      .get<{ data: InventoryItem[] }>('/inventory/items', {
        params: type ? { type } : undefined,
      })
      .then((r) => r.data),

  itemTypes: () =>
    apiClient
      .get<{ data: ItemType[] }>('/inventory/item-types')
      .then((r) => r.data),

  createItem: (payload: CreateItemPayload) =>
    apiClient
      .post<{ message: string; data: { id: number } }>('/inventory/items', payload)
      .then((r) => r.data),

  /** نتيجة مسح باركود: صنف موجود، أو اسم مقترح من قاعدة مفتوحة إن لم يوجد */
  lookupBarcode: (code: string) =>
    apiClient
      .get<{ data: { found: boolean; item_id?: number; name?: string; suggested_name?: string | null; suggestion?: BarcodeSuggestion | null } }>(
        `/inventory/barcode/${encodeURIComponent(code)}`,
      )
      .then((r) => r.data.data),

  setBarcode: (itemId: number, barcode: string) =>
    apiClient.patch(`/inventory/items/${itemId}/barcode`, { barcode }).then((r) => r.data),

  updateItem: (itemId: number, payload: Partial<CreateItemPayload>) =>
    apiClient
      .put<{ message: string; data: { id: number } }>(`/inventory/items/${itemId}`, payload)
      .then((r) => r.data),

  adjustStock: (itemId: number, quantity: number, notes?: string) =>
    apiClient
      .patch<{ message: string; data: { total_quantity: number } }>(`/inventory/items/${itemId}/adjust-stock`, { quantity, notes })
      .then((r) => r.data),

  stock: () =>
    apiClient
      .get<{ data: StockItem[] }>('/inventory/stock')
      .then((r) => r.data),

  summary: () =>
    apiClient
      .get<{ data: InventorySummary }>('/inventory/summary')
      .then((r) => r.data),

  warehouses: () =>
    apiClient
      .get<{ data: Warehouse[] }>('/inventory/warehouses')
      .then((r) => r.data),

  transactions: (flockId?: number) =>
    apiClient
      .get<{ data: InventoryTransaction[] }>('/inventory/transactions', { params: flockId ? { flock_id: flockId } : {} })
      .then((r) => r.data),

  addShipment: (payload: AddShipmentPayload) => {
    const formData = new FormData()
    Object.entries(payload).forEach(([k, v]) => {
      if (v !== null && v !== undefined) {
        if (v instanceof File) {
          formData.append(k, v)
        } else {
          formData.append(k, String(v))
        }
      }
    })

    return apiClient
      .post<{ message: string; data: { id: number } }>('/inventory/transactions', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      .then((r) => r.data)
  },
}

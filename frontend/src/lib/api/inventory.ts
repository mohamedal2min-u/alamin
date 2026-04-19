import { apiClient } from './client'
import type { InventoryItem } from '@/types/dashboard'

export interface StockItem {
  id: number
  name: string
  type_code: string
  type_name: string | null
  input_unit: string
  content_unit: string
  unit_value: number
  minimum_stock: number
  total_quantity: number
}

export interface InventorySummary {
  feed_quantity: number
  feed_unit: string
  medicine_quantity: number
  medicine_unit: string
  low_stock_count: number
  last_shipment_date: string | null
  total_value: number
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

export interface CreateItemPayload {
  item_type_id: number
  name: string
  input_unit: string
  unit_value: number
  content_unit: string
  minimum_stock?: number | null
  default_cost?: number | null
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

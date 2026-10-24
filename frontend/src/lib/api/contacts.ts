// frontend/src/lib/api/contacts.ts
// الموردون والتجار (المشترون) وأرصدتهم وكشوف حساباتهم

import { apiClient } from './client'
import type { PaymentMethod } from './accounting'

export type ContactType = 'supplier' | 'buyer' | 'both'

export const CONTACT_TYPE_LABELS: Record<ContactType, string> = {
  supplier: 'مورد',
  buyer: 'تاجر',
  both: 'مورد وتاجر',
}

export interface Contact {
  id: number
  name: string
  type: ContactType
  phone: string | null
  whatsapp: string | null
  notes: string | null
  is_active: boolean
}

export interface AgingBuckets {
  d0_30: number
  d31_60: number
  d60_plus: number
}

export interface BalanceSide {
  total: number
  paid: number
  /** الإجمالي − المدفوع (سالب = دفعة مقدمة) */
  balance: number
  open_count: number
  aging: AgingBuckets
}

export interface ContactBalance extends Contact {
  /** ما علينا للمورد */
  payable: BalanceSide
  /** ما لنا على التاجر */
  receivable: BalanceSide
}

export interface ContactBalancesResponse {
  data: ContactBalance[]
  summary: {
    payable_balance: number
    receivable_balance: number
    payable_aging: AgingBuckets
    receivable_aging: AgingBuckets
  }
}

export interface StatementEntry {
  kind: 'invoice' | 'payment'
  side: 'payable' | 'receivable'
  record_type: 'expense' | 'sale' | 'water_log' | 'shipment'
  record_id: number
  date: string
  description: string
  flock_name: string | null
  amount: number
  unpriced: boolean
  method: PaymentMethod | null
  note: string | null
  is_opening: boolean
  running_payable: number
  running_receivable: number
  /** موجب = لنا عليه، سالب = علينا له */
  running_net: number
}

export interface ContactStatement {
  contact: Contact
  from: string | null
  to: string | null
  opening: { payable: number; receivable: number }
  closing: { payable: number; receivable: number; net: number }
  totals: {
    payable: { invoiced: number; paid: number }
    receivable: { invoiced: number; paid: number }
  }
  entries: StatementEntry[]
}

export type ContactPayload = {
  name: string
  type: ContactType
  phone?: string | null
  whatsapp?: string | null
  notes?: string | null
  is_active?: boolean
}

export const contactsApi = {
  /** قائمة خفيفة للمنتقي؛ type=supplier|buyer يشمل «كلاهما» */
  list: (params: { type?: 'supplier' | 'buyer'; active?: boolean } = {}) =>
    apiClient
      .get<{ data: Contact[] }>('/contacts', {
        params: { ...(params.type ? { type: params.type } : {}), ...(params.active ? { active: 1 } : {}) },
      })
      .then((r) => r.data.data),

  create: (payload: ContactPayload) =>
    apiClient.post<{ data: Contact }>('/contacts', payload).then((r) => r.data.data),

  update: (id: number, payload: Partial<ContactPayload>) =>
    apiClient.put<{ data: Contact }>(`/contacts/${id}`, payload).then((r) => r.data.data),

  remove: (id: number) => apiClient.delete(`/contacts/${id}`).then((r) => r.data),

  balances: () => apiClient.get<ContactBalancesResponse>('/contacts/balances').then((r) => r.data),

  statement: (id: number, params: { from?: string; to?: string } = {}) =>
    apiClient
      .get<ContactStatement>(`/contacts/${id}/statement`, { params })
      .then((r) => r.data),
}

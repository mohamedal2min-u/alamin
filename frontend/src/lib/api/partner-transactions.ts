import { apiClient } from './client'

export interface PartnerTransaction {
  id: number
  farm_id: number
  partner_id: number
  flock_id?: number
  transaction_date: string
  transaction_type: 'deposit' | 'withdraw' | 'settlement' | 'profit' | 'loss' | 'adjustment'
  amount: number
  description?: string
  reference_no?: string
  notes?: string
  metadata?: any
  created_at: string
  updated_at: string
}

export const partnerTransactionsApi = {
  list: (partnerId: number) =>
    apiClient.get<PartnerTransaction[]>(`/partners/${partnerId}/transactions`).then((r) => r.data),

  create: (partnerId: number, data: {
    transaction_date: string
    transaction_type: 'deposit' | 'withdraw' | 'settlement'
    amount: number
    description?: string
  }) => apiClient.post<PartnerTransaction>(`/partners/${partnerId}/transactions`, data).then((r) => r.data),
}

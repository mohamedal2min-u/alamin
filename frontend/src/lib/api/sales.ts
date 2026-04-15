// frontend/src/lib/api/sales.ts

import { apiClient } from './client'
import type { Sale, CreateSalePayload, UpdatePaymentPayload } from '@/types/sale'

export const salesApi = {
  /** GET /api/sales — كل مبيعات المزرعة */
  listAll: () =>
    apiClient.get<{ data: Sale[] }>('/sales').then((r) => r.data),

  /** GET /api/flocks/{flock}/sales — مبيعات فوج محدد */
  listByFlock: (flockId: number) =>
    apiClient
      .get<{ data: Sale[] }>(`/flocks/${flockId}/sales`)
      .then((r) => r.data),

  /** GET /api/sales/{sale} */
  get: (saleId: number) =>
    apiClient.get<{ data: Sale }>(`/sales/${saleId}`).then((r) => r.data),

  /** POST /api/flocks/{flock}/sales */
  create: (flockId: number, payload: CreateSalePayload) =>
    apiClient
      .post<{ data: Sale; message: string }>(`/flocks/${flockId}/sales`, payload)
      .then((r) => r.data),

  /** PATCH /api/sales/{sale}/payment */
  updatePayment: (saleId: number, payload: UpdatePaymentPayload) =>
    apiClient
      .patch<{ data: Sale; message: string }>(`/sales/${saleId}/payment`, payload)
      .then((r) => r.data),
}

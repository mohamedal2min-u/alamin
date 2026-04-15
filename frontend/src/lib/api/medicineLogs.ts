import { apiClient } from './client'
import type { MedicineLog, CreateMedicineLogPayload } from '@/types/medicineLog'

export const medicineLogsApi = {
  list: (flockId: number) =>
    apiClient
      .get<{ data: MedicineLog[] }>(`/flocks/${flockId}/medicine-logs`)
      .then((r) => r.data),

  create: (flockId: number, payload: CreateMedicineLogPayload) =>
    apiClient
      .post<{ message: string; data: MedicineLog }>(`/flocks/${flockId}/medicine-logs`, payload)
      .then((r) => r.data),
}

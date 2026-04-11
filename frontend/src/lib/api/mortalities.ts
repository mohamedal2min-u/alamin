// frontend/src/lib/api/mortalities.ts

import { apiClient } from './client'
import type { CreateMortalityPayload, Mortality } from '@/types/mortality'

export const mortalitiesApi = {
  list: (flockId: number) =>
    apiClient
      .get<{ data: Mortality[] }>(`/flocks/${flockId}/mortalities`)
      .then((r) => r.data),

  create: (flockId: number, payload: CreateMortalityPayload) =>
    apiClient
      .post<{ data: Mortality; message: string }>(`/flocks/${flockId}/mortalities`, payload)
      .then((r) => r.data),
}

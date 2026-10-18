// frontend/src/lib/api/mortalities.ts

import { apiClient } from './client'
import { withIdempotencyKey } from '@/lib/offline/outbox'
import type { CreateMortalityPayload, Mortality } from '@/types/mortality'

export const mortalitiesApi = {
  list: (flockId: number) =>
    apiClient
      .get<{ data: Mortality[] }>(`/flocks/${flockId}/mortalities`)
      .then((r) => r.data),

  create: (flockId: number, payload: CreateMortalityPayload) =>
    apiClient
      .post<{ data: Mortality; message: string }>(`/flocks/${flockId}/mortalities`, payload, withIdempotencyKey())
      .then((r) => r.data),
}

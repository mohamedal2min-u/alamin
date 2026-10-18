import { apiClient } from './client'
import { withIdempotencyKey } from '@/lib/offline/outbox'
import type { WaterLog, CreateWaterLogPayload } from '@/types/waterLog'

export const waterLogsApi = {
  list: (flockId: number) =>
    apiClient
      .get<{ data: WaterLog[] }>(`/flocks/${flockId}/water-logs`)
      .then((r) => r.data),

  create: (flockId: number, payload: CreateWaterLogPayload) =>
    apiClient
      .post<{ message: string; data: WaterLog }>(`/flocks/${flockId}/water-logs`, payload, withIdempotencyKey())
      .then((r) => r.data),
}

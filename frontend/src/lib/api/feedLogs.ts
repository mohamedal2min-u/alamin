import { apiClient } from './client'
import type { FeedLog, CreateFeedLogPayload } from '@/types/feedLog'

export const feedLogsApi = {
  list: (flockId: number) =>
    apiClient
      .get<{ data: FeedLog[] }>(`/flocks/${flockId}/feed-logs`)
      .then((r) => r.data),

  create: (flockId: number, payload: CreateFeedLogPayload) =>
    apiClient
      .post<{ message: string; data: FeedLog }>(`/flocks/${flockId}/feed-logs`, payload)
      .then((r) => r.data),
}

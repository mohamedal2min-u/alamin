import { apiClient } from './client'
import type { CreateFlockPayload, Flock, FlockListResponse } from '@/types/flock'

export const flocksApi = {
  list: () =>
    apiClient.get<FlockListResponse>('/flocks').then((r) => r.data),

  get: (id: number) =>
    apiClient.get<{ data: Flock }>(`/flocks/${id}`).then((r) => r.data),

  create: (payload: CreateFlockPayload) =>
    apiClient.post<{ data: Flock; message: string }>('/flocks', payload).then((r) => r.data),

  update: (id: number, payload: Partial<CreateFlockPayload>) =>
    apiClient.put<{ data: Flock; message: string }>(`/flocks/${id}`, payload).then((r) => r.data),
}

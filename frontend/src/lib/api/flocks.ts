import { apiClient } from './client'
import type { CreateFlockPayload, Flock, FlockListResponse } from '@/types/flock'
import type { TodaySummary } from '@/types/dashboard'

export const flocksApi = {
  list: () =>
    apiClient.get<FlockListResponse>('/flocks').then((r) => r.data),

  get: (id: number) =>
    apiClient.get<{ data: Flock }>(`/flocks/${id}`).then((r) => r.data),

  create: (payload: CreateFlockPayload) =>
    apiClient.post<{ data: Flock; message: string }>('/flocks', payload).then((r) => r.data),

  update: (id: number, payload: Partial<CreateFlockPayload> & { status?: string; close_date?: string }) =>
    apiClient.put<{ data: Flock; message: string }>(`/flocks/${id}`, payload).then((r) => r.data),

  todaySummary: (id: number, date?: string) =>
    apiClient.get<{ data: TodaySummary }>(`/flocks/${id}/today-summary${date ? `?date=${date}` : ''}`).then((r) => r.data),

  getHistory: (id: number) =>
    apiClient.get<{ data: any[] }>(`/flocks/${id}/history`).then((r) => r.data),
}


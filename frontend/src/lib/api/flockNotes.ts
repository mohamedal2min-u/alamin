import { apiClient } from './client'
import type { FlockNote, CreateFlockNotePayload } from '@/types/flockNote'

export const flockNotesApi = {
  list: (flockId: number) =>
    apiClient
      .get<{ data: FlockNote[] }>(`/flocks/${flockId}/notes`)
      .then((r) => r.data),

  create: (flockId: number, payload: CreateFlockNotePayload) =>
    apiClient
      .post<{ message: string; data: FlockNote }>(`/flocks/${flockId}/notes`, payload)
      .then((r) => r.data),
}

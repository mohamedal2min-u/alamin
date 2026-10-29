import { apiClient } from './client'

export interface FlockDocument {
  id: number
  title: string | null
  url: string
  created_at: string | null
}

export const flockDocumentsApi = {
  list: (flockId: number) =>
    apiClient
      .get<{ data: FlockDocument[] }>(`/flocks/${flockId}/documents`)
      .then((r) => r.data),

  upload: (flockId: number, file: File, title?: string) => {
    const form = new FormData()
    form.append('file', file)
    if (title) form.append('title', title)
    return apiClient
      .post<{ data: FlockDocument }>(
        `/flocks/${flockId}/documents`,
        form,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      )
      .then((r) => r.data)
  },
}

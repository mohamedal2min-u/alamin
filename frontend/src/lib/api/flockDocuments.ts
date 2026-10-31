import { apiClient } from './client'

export type RecordType = 'sale' | 'expense' | 'water_log'

export interface FlockDocument {
  id: number
  title: string | null
  record_type: RecordType | null
  record_id: number | null
  url: string
  created_at: string | null
}

export const flockDocumentsApi = {
  list: (flockId: number, record?: { type: RecordType; id: number }) =>
    apiClient
      .get<{ data: FlockDocument[] }>(`/flocks/${flockId}/documents`, {
        params: record ? { record_type: record.type, record_id: record.id } : undefined,
      })
      .then((r) => r.data),

  upload: (flockId: number, file: File, title?: string, record?: { type: RecordType; id: number }) => {
    const form = new FormData()
    form.append('file', file)
    if (title) form.append('title', title)
    if (record) {
      form.append('record_type', record.type)
      form.append('record_id', String(record.id))
    }
    return apiClient
      .post<{ data: FlockDocument }>(
        `/flocks/${flockId}/documents`,
        form,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      )
      .then((r) => r.data)
  },
}

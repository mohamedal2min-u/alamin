// frontend/src/lib/api/workers.ts

import { apiClient } from './client'

export interface CreateWorkerPayload {
  name: string
  whatsapp: string
  email?: string
  password: string
  salary?: number
}

export const workersApi = {
  list: () => 
    apiClient.get('/workers'),
    
  create: (payload: CreateWorkerPayload) =>
    apiClient.post('/workers', payload),

  delete: (id: number) =>
    apiClient.delete(`/workers/${id}`),
}

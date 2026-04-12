// frontend/src/lib/api/admin.ts
import { apiClient } from './client'

export interface AdminFarm {
  id: number
  name: string
  location: string | null
  status: 'active' | 'pending_setup' | 'suspended'
  started_at: string | null
  created_at: string
  members_count: number
  admin: { id: number; name: string } | null
}

export interface AdminUser {
  id: number
  name: string
  email: string | null
  whatsapp: string | null
  status: string
}

export interface CreateFarmPayload {
  name: string
  location?: string
  started_at?: string
}

export const adminApi = {
  listFarms: () =>
    apiClient.get<{ data: AdminFarm[] }>('/admin/farms').then((r) => r.data),

  createFarm: (payload: CreateFarmPayload) =>
    apiClient
      .post<{ data: AdminFarm; message: string }>('/admin/farms', payload)
      .then((r) => r.data),

  assignAdmin: (farmId: number, userId: number) =>
    apiClient
      .put<{ message: string; data: { farm_id: number; admin: { id: number; name: string } } }>(
        `/admin/farms/${farmId}/admin`,
        { user_id: userId }
      )
      .then((r) => r.data),

  listUsers: () =>
    apiClient.get<{ data: AdminUser[] }>('/admin/users').then((r) => r.data),
}

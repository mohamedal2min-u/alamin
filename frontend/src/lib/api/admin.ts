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

export interface AdminFarmMember {
  id: number
  name: string
  email: string | null
  whatsapp: string | null
  status: 'active' | 'suspended'
  role: 'farm_admin' | 'partner' | 'worker' | null
}

export interface CreateFarmPayload {
  name: string
  location?: string
  started_at?: string
}

export interface CreateManagerPayload {
  name: string
  whatsapp: string
  email?: string
  password: string
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

  createManager: (farmId: number, payload: CreateManagerPayload) =>
    apiClient
      .post<{ message: string; data: { farm_id: number; admin: { id: number; name: string } } }>(
        `/admin/farms/${farmId}/manager`,
        payload
      )
      .then((r) => r.data),

  deleteFarm: (farmId: number) =>
    apiClient.delete<{ message: string }>(`/admin/farms/${farmId}`).then((r) => r.data),

  listUsers: () =>
    apiClient.get<{ data: AdminUser[] }>('/admin/users').then((r) => r.data),

  farmMembers: (farmId: number) =>
    apiClient.get<{ data: AdminFarmMember[] }>(`/admin/farms/${farmId}/members`).then((r) => r.data),

  resetPassword: (userId: number, password: string) =>
    apiClient.put<{ message: string }>(`/admin/users/${userId}/password`, { password }).then((r) => r.data),

  toggleUserStatus: (userId: number, status: 'active' | 'suspended') =>
    apiClient.put<{ message: string; data: { id: number; status: string } }>(`/admin/users/${userId}/status`, { status }).then((r) => r.data),

  assignMemberRole: (farmId: number, userId: number, role: string) =>
    apiClient.put<{ message: string; data: { id: number; role: string } }>(`/admin/farms/${farmId}/members/${userId}/role`, { role }).then((r) => r.data),
}

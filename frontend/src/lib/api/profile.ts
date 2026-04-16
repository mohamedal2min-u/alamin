import { apiClient } from './client'
import type { User } from '@/types/auth'

export const profileApi = {
  updateProfile: (data: { name?: string; email?: string | null; whatsapp?: string | null }) =>
    apiClient
      .put<{ message: string; data: User }>('/auth/me', data)
      .then((r) => r.data),

  changePassword: (data: {
    current_password: string
    password: string
    password_confirmation: string
  }) =>
    apiClient
      .put<{ message: string }>('/auth/password', data)
      .then((r) => r.data),

  uploadAvatar: (file: File) => {
    const form = new FormData()
    form.append('avatar', file)
    return apiClient
      .post<{ message: string; avatar_path: string; avatar_url: string }>(
        '/auth/avatar',
        form,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      )
      .then((r) => r.data)
  },
}

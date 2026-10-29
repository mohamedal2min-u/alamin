import { apiClient } from './client'

export interface NotificationChange {
  label: string
  from?: string
  to: string
}

export interface AppNotification {
  id: number
  kind: string
  title: string
  body: string
  actor: string | null
  farm: string | null
  link: string | null
  read: boolean
  created_at: string | null
  details?: {
    what?: string
    flock?: string | null
    rows?: NotificationChange[]
  } | null
}

export const notificationsApi = {
  list: async () => {
    const { data } = await apiClient.get<{ data: AppNotification[]; unread: number }>('/auth/notifications')
    return data
  },
  get: async (id: number) => {
    const { data } = await apiClient.get<{ data: AppNotification }>(`/auth/notifications/${id}`)
    return data.data
  },
  readAll: async () => {
    await apiClient.post('/auth/notifications/read-all')
  },
}

/** وقت الإشعار مختصراً: «29 أكت، 6:14 م» */
export const formatTime = (iso: string | null) => {
  if (!iso) return ''
  try {
    return new Intl.DateTimeFormat('ar', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit', numberingSystem: 'latn' }).format(new Date(iso))
  } catch {
    return iso
  }
}

import { apiClient } from './client'

/** أنواع الإدخالات اليومية القابلة للتعديل/الإلغاء */
export type DailyEntryType = 'mortality' | 'feed_log' | 'medicine_log' | 'water_log' | 'note' | 'temperature_log'

/** حقول تصحيح السجل التي يرسلها الخادم مع كل إدخال (حسب صلاحية المستخدم الحالي) */
export interface EntryCorrectionMeta {
  id?: number
  entry_type?: DailyEntryType
  can_edit?: boolean
  can_void?: boolean
  notes?: string | null
}

export interface DailyEntryUpdatePayload {
  quantity?: number
  reason?: string | null
  notes?: string | null
  note_text?: string
  temperature?: number
  edit_reason?: string | null
}

export type ActivityAction = 'created' | 'updated' | 'voided'

export interface ActivityLogEntry {
  id: number
  action: ActivityAction
  subject_type: DailyEntryType
  subject_id: number
  flock_id: number | null
  flock_name: string | null
  user_id: number | null
  user_name: string | null
  before: Record<string, unknown> | null
  after: Record<string, unknown> | null
  reason: string | null
  created_at: string | null
}

export interface ActivityLogResponse {
  data: ActivityLogEntry[]
  meta: { current_page: number; last_page: number; total: number }
  filters: { flocks: { id: number; name: string }[]; users: { id: number; name: string }[] }
}

export const dailyEntriesApi = {
  update: async (type: DailyEntryType, id: number, payload: DailyEntryUpdatePayload) => {
    const { data } = await apiClient.patch<{ message: string }>(`/daily-entries/${type}/${id}`, payload)
    return data
  },

  void: async (type: DailyEntryType, id: number, reason: string) => {
    const { data } = await apiClient.post<{ message: string }>(`/daily-entries/${type}/${id}/void`, { reason })
    return data
  },

  activity: async (params: { page?: number; flock_id?: number; user_id?: number; subject_type?: DailyEntryType }) => {
    const q = new URLSearchParams()
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null) q.set(k, String(v))
    })
    const { data } = await apiClient.get<ActivityLogResponse>(`/activity-logs?${q.toString()}`)
    return data
  },
}

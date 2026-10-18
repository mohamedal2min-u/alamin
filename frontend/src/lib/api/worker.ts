import { apiClient } from './client'
import { withIdempotencyKey } from '@/lib/offline/outbox'

export interface TemperatureLogPayload {
  log_date: string
  time_of_day: 'morning' | 'afternoon' | 'evening'
  temperature: number
  notes?: string
}

export const workerApi = {
  logTemperature: (flockId: number, payload: TemperatureLogPayload) =>
    apiClient
      .post<{ message: string }>(`/flocks/${flockId}/temperature-logs`, payload, withIdempotencyKey())
      .then((r) => r.data),
      
  closeDay: (flockId: number, date: string) =>
    // Placeholder for future backend implementation
    Promise.resolve({ message: 'Day closed locally' }),
}

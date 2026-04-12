import { apiClient } from './client'

export interface TemperatureLogPayload {
  log_date: string
  time_of_day: 'morning' | 'afternoon' | 'evening'
  temperature: number
  notes?: string
}

export const workerApi = {
  logTemperature: (flockId: number, payload: TemperatureLogPayload) =>
    apiClient
      .post<{ message: string }>(`/flocks/${flockId}/temperature-logs`, payload)
      .then((r) => r.data),
      
  closeDay: (flockId: number, date: string) =>
    // Placeholder for future backend implementation
    Promise.resolve({ message: 'Day closed locally' }),
}

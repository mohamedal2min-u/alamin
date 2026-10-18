import { apiClient } from './client'
import type {
  ActiveVaccinations,
  FlockVaccination,
  VaccinationStatus,
  VaccineTemplate,
  VaccineTemplateItem,
  WithdrawalInfo,
} from '@/types/vaccination'

export const vaccinationsApi = {
  template: () =>
    apiClient.get<{ data: VaccineTemplate }>('/vaccine-template').then((r) => r.data.data),

  saveTemplate: (payload: { name: string; items: VaccineTemplateItem[] }) =>
    apiClient.put<{ data: VaccineTemplate; message: string }>('/vaccine-template', payload).then((r) => r.data),

  applyToActive: () =>
    apiClient.post<{ data: { flock_id: number; created: number }; message: string }>('/vaccine-template/apply-active').then((r) => r.data),

  active: () =>
    apiClient.get<ActiveVaccinations>('/vaccinations/active').then((r) => r.data),

  list: (flockId: number) =>
    apiClient
      .get<{ data: FlockVaccination[]; withdrawal: WithdrawalInfo }>(`/flocks/${flockId}/vaccinations`)
      .then((r) => r.data),

  mark: (flockId: number, id: number, status: VaccinationStatus, note?: string | null) =>
    apiClient
      .patch<{ data: FlockVaccination; withdrawal: WithdrawalInfo; message: string }>(
        `/flocks/${flockId}/vaccinations/${id}`,
        { status, note: note || null },
      )
      .then((r) => r.data),

  withdrawal: (flockId: number) =>
    apiClient.get<{ data: WithdrawalInfo }>(`/flocks/${flockId}/withdrawal`).then((r) => r.data.data),
}

// مفاتيح React Query — تتضمن رقم المزرعة لأن البيانات خاصة بها
export const vaccineKeys = {
  all: (farmId: number | undefined) => ['vaccinations', farmId] as const,
  template: (farmId: number | undefined) => ['vaccinations', farmId, 'template'] as const,
  active: (farmId: number | undefined) => ['vaccinations', farmId, 'active'] as const,
  flock: (farmId: number | undefined, flockId: number) => ['vaccinations', farmId, 'flock', flockId] as const,
  withdrawal: (farmId: number | undefined, flockId: number) => ['vaccinations', farmId, 'withdrawal', flockId] as const,
}

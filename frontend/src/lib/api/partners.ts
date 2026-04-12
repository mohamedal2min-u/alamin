import { apiClient } from './client'

export interface PartnerShare {
    id: number
    share_percent: number
    is_active: boolean
    effective_from: string | null
    effective_to: string | null
}

export interface Partner {
    id: number
    name: string
    email: string | null
    whatsapp: string | null
    status: 'active' | 'inactive'
    notes: string | null
    shares?: PartnerShare[]
    created_at: string
}

export const partnersApi = {
    getAll: async () => {
        const { data } = await apiClient.get<Partner[]>('/partners')
        return data
    },

    create: async (data: Partial<Partner & { share_percent?: number, password?: string }>) => {
        const { data: response } = await apiClient.post<Partner>('/partners', data)
        return response
    },

    update: async (id: number, data: Partial<Partner>) => {
        const { data: response } = await apiClient.put<Partner>(`/partners/${id}`, data)
        return response
    },

    delete: async (id: number) => {
        await apiClient.delete(`/partners/${id}`)
    }
}

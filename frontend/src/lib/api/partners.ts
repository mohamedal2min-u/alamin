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
    farm_id: number
    user_id: number | null
    name: string
    email: string | null
    whatsapp: string | null
    status: 'active' | 'inactive'
    notes: string | null
    share_percent: number
    shares?: PartnerShare[]
    created_at: string
    updated_at: string
}

export const partnersApi = {
    getAll: async () => {
        const { data } = await apiClient.get<{ data: Partner[] }>('/partners')
        return data.data
    },

    getMyInfo: async (): Promise<Partner> => {
        const { data } = await apiClient.get<{ data: Partner }>('/partners/my-info')
        return data.data
    },

    create: async (data: Partial<Partner & { share_percent?: number, password?: string }>) => {
        const { data: response } = await apiClient.post<{ data: Partner }>('/partners', data)
        return response.data
    },

    update: async (id: number, data: Partial<Partner>) => {
        const { data: response } = await apiClient.put<{ data: Partner }>(`/partners/${id}`, data)
        return response.data
    },

    delete: async (id: number) => {
        await apiClient.delete(`/partners/${id}`)
    }
}

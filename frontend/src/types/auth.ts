// frontend/src/types/auth.ts

export type FarmRole = 'super_admin' | 'farm_admin' | 'worker' | 'partner'

export interface User {
  id: number
  name: string
  email: string | null
  whatsapp: string | null
  avatar_path: string | null
  avatar_url: string | null
  status: 'active' | 'inactive' | 'suspended'
  last_login_at: string | null
  farms: UserFarm[]
}

export interface UserFarm {
  id: number
  name: string
  status: 'active' | 'pending_setup' | 'suspended'
  role: FarmRole | null
  is_primary: boolean
}

export interface LoginResponse {
  token: string
  user: User
}

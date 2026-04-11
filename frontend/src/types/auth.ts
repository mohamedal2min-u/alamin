export interface User {
  id: number
  name: string
  email: string | null
  whatsapp: string | null
  status: 'active' | 'inactive' | 'suspended'
  last_login_at: string | null
  farms: UserFarm[]
}

export interface UserFarm {
  id: number
  name: string
  status: 'active' | 'pending_setup' | 'suspended'
  role: 'owner' | 'manager' | 'viewer'
  is_primary: boolean
}

export interface LoginResponse {
  token: string
  user: User
}

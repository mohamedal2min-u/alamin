export interface Farm {
  id: number
  name: string
  status: 'active' | 'pending_setup' | 'suspended'
  role: 'owner' | 'manager' | 'viewer'
  is_primary: boolean
}

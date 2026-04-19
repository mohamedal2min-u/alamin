// frontend/src/types/farm.ts

import type { FarmRole } from './auth'

export interface Farm {
  id: number
  name: string
  status: 'active' | 'pending_setup' | 'suspended'
  role: FarmRole | null
  is_primary: boolean
  partner_id?: number | null
}

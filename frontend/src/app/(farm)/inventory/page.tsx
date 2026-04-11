import { Package } from 'lucide-react'
import { ComingSoon } from '@/components/ui/ComingSoon'

export default function InventoryPage() {
  return (
    <ComingSoon
      title="المخزون"
      description="إدارة مخزون العلف والدواء والمستلزمات"
      icon={Package}
    />
  )
}

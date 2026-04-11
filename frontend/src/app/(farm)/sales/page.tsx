import { ShoppingCart } from 'lucide-react'
import { ComingSoon } from '@/components/ui/ComingSoon'

export default function SalesPage() {
  return (
    <ComingSoon
      title="المبيعات"
      description="تسجيل ومتابعة مبيعات الدواجن والبيض"
      icon={ShoppingCart}
    />
  )
}

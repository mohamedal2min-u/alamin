import { Receipt } from 'lucide-react'
import { ComingSoon } from '@/components/ui/ComingSoon'

export default function ExpensesPage() {
  return (
    <ComingSoon
      title="المصروفات"
      description="تتبع مصروفات المزرعة والتشغيل اليومي"
      icon={Receipt}
    />
  )
}

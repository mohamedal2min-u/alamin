import { Construction } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { Card, CardContent } from './Card'
import { Skeleton } from './Skeleton'

interface ComingSoonProps {
  title: string
  description: string
  icon: LucideIcon
}

export function ComingSoon({ title, description, icon: Icon }: ComingSoonProps) {
  return (
    <div className="space-y-6">
      {/* Page header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100">
            <Icon className="h-5 w-5 text-slate-500" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-slate-900">{title}</h1>
            <p className="mt-0.5 text-sm text-slate-500">{description}</p>
          </div>
        </div>

        {/* Placeholder button */}
        <Skeleton className="h-10 w-32 rounded-lg" />
      </div>

      {/* Under development banner */}
      <div className="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4">
        <Construction className="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
        <div>
          <p className="text-sm font-medium text-emerald-800">قيد التطوير</p>
          <p className="mt-0.5 text-xs text-emerald-700">
            هذه الصفحة سيتم إطلاقها قريباً بعد اكتمال الـ backend الخاص بها.
          </p>
        </div>
      </div>

      {/* Skeleton content preview */}
      <Card>
        <CardContent className="space-y-3 py-5">
          {Array.from({ length: 5 }).map((_, i) => (
            <div key={i} className="flex items-center gap-4">
              <Skeleton className="h-10 w-10 rounded-lg shrink-0" />
              <div className="flex-1 space-y-2">
                <Skeleton className="h-3.5 w-1/3" />
                <Skeleton className="h-3 w-1/2" />
              </div>
              <Skeleton className="h-6 w-20 rounded-full" />
            </div>
          ))}
        </CardContent>
      </Card>
    </div>
  )
}


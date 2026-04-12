import { cn } from '@/lib/utils'

interface CardProps {
  className?: string
  children: React.ReactNode
}

export function Card({ className, children }: CardProps) {
  return (
    <div className={cn(
      'rounded-2xl border border-slate-200/60 bg-white',
      className
    )} style={{ boxShadow: 'var(--shadow-card)' }}>
      {children}
    </div>
  )
}

export function CardHeader({ className, children }: CardProps) {
  return (
    <div className={cn('border-b border-slate-100 px-5 py-4', className)}>
      {children}
    </div>
  )
}

export function CardContent({ className, children }: CardProps) {
  return (
    <div className={cn('px-5 py-4', className)}>
      {children}
    </div>
  )
}

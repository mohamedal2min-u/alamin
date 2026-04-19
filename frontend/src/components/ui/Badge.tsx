import { cn } from '@/lib/utils'

interface BadgeProps {
  variant?: 'success' | 'info' | 'neutral' | 'danger' | 'warning'
  className?: string
  children: React.ReactNode
}

const variants = {
  success: 'bg-emerald-100 text-emerald-800',
  info:    'bg-blue-100 text-blue-800',
  neutral: 'bg-slate-100 text-slate-600',
  danger:  'bg-red-100 text-red-700',
  warning: 'bg-emerald-100 text-emerald-800',
}

export function Badge({ variant = 'neutral', className, children }: BadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
        variants[variant],
        className
      )}
    >
      {children}
    </span>
  )
}


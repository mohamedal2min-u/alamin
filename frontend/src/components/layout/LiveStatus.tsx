'use client'

import { cn } from '@/lib/utils'

interface LiveStatusProps {
  className?: string
}

export function LiveStatus({ className }: LiveStatusProps) {
  return (
    <div className={cn("inline-flex items-center justify-center rounded-full bg-primary-500/10 border border-primary-500/20 p-1.5 shadow-inner", className)}>
      <span className="relative flex h-2.5 w-2.5">
        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
        <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-primary-500"></span>
      </span>
    </div>
  )
}


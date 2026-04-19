'use client'

import { cn } from '@/lib/utils'

interface LiveStatusProps {
  className?: string
}

export function LiveStatus({ className }: LiveStatusProps) {
  return (
    <div className={cn("inline-flex items-center gap-1.5 rounded-full bg-primary-500/10 border border-primary-500/20 px-2.5 py-1 shadow-inner", className)}>
      <span className="relative flex h-2 w-2">
        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
        <span className="relative inline-flex rounded-full h-2 w-2 bg-primary-500"></span>
      </span>
      <span className="text-[10px] font-black text-primary-400 uppercase tracking-widest">LIVE</span>
    </div>
  )
}


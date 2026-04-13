'use client'

import { AlertTriangle } from 'lucide-react'

interface Props {
  message: string
}

export function SystemAlert({ message }: Props) {
  return (
    <div className="flex items-center gap-3 rounded-2xl bg-amber-500 p-4 text-white shadow-lg shadow-amber-500/20">
      <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/20">
        <AlertTriangle className="h-5 w-5" />
      </div>
      <div className="flex flex-col">
        <span className="text-[10px] font-black uppercase tracking-widest opacity-80">
          تنبيه تشغيلي
        </span>
        <p className="text-sm font-black leading-tight">
          {message}
        </p>
      </div>
    </div>
  )
}

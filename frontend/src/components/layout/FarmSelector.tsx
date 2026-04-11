'use client'

import { useFarmStore } from '@/stores/farm.store'
import { useAuthStore } from '@/stores/auth.store'
import { ChevronDown } from 'lucide-react'
import { useState } from 'react'
import { cn } from '@/lib/utils'
import type { Farm } from '@/types/farm'

export function FarmSelector() {
  const { farms, currentFarm, setCurrentFarm } = useFarmStore()
  const { user } = useAuthStore()
  const [open, setOpen] = useState(false)

  // Sync farms from user if store is empty (e.g. after hard refresh)
  const availableFarms: Farm[] = farms.length
    ? farms
    : (user?.farms ?? []).map((f) => ({
        id: f.id,
        name: f.name,
        status: f.status,
        role: f.role,
        is_primary: f.is_primary,
      }))

  if (!currentFarm) return null

  return (
    <div className="relative">
      <button
        onClick={() => setOpen((o) => !o)}
        className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
      >
        <span className="max-w-[140px] truncate">{currentFarm.name}</span>
        <ChevronDown className={cn('h-4 w-4 transition-transform', open && 'rotate-180')} />
      </button>

      {open && (
        <>
          {/* Backdrop */}
          <div className="fixed inset-0 z-10" onClick={() => setOpen(false)} />

          {/* Dropdown */}
          <div className="absolute start-0 top-full z-20 mt-1 min-w-[200px] rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
            {availableFarms.map((farm) => (
              <button
                key={farm.id}
                onClick={() => {
                  setCurrentFarm(farm)
                  setOpen(false)
                }}
                className={cn(
                  'flex w-full items-center gap-3 px-4 py-2.5 text-sm transition hover:bg-slate-50',
                  currentFarm.id === farm.id && 'bg-primary-50 text-primary-700 font-medium'
                )}
              >
                <span className="flex-1 truncate text-start">{farm.name}</span>
                {currentFarm.id === farm.id && (
                  <span className="h-2 w-2 rounded-full bg-primary-500" />
                )}
              </button>
            ))}
          </div>
        </>
      )}
    </div>
  )
}

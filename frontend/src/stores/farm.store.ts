import { create } from 'zustand'
import { persist, createJSONStorage } from 'zustand/middleware'
import type { Farm } from '@/types/farm'

interface FarmState {
  farms: Farm[]
  currentFarm: Farm | null

  setFarms: (farms: Farm[]) => void
  setCurrentFarm: (farm: Farm) => void
  clearFarm: () => void
}

export const useFarmStore = create<FarmState>()(
  persist(
    (set) => ({
      farms: [],
      currentFarm: null,

      setFarms: (farms) => set({ farms }),

      setCurrentFarm: (farm) => {
        if (typeof window !== 'undefined') {
          localStorage.setItem('current_farm_id', String(farm.id))
        }
        set({ currentFarm: farm })
      },

      clearFarm: () => {
        if (typeof window !== 'undefined') {
          localStorage.removeItem('current_farm_id')
        }
        set({ currentFarm: null })
      },
    }),
    {
      name: 'alamin-farm',
      storage: createJSONStorage(() =>
        typeof window !== 'undefined' ? localStorage : ({} as Storage)
      ),
      partialize: (state) => ({
        farms: state.farms,
        currentFarm: state.currentFarm,
      }),
    }
  )
)

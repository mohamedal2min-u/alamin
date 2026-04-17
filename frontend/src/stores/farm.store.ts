import { create } from 'zustand'
import { persist, createJSONStorage } from 'zustand/middleware'
import type { Farm } from '@/types/farm'
import type { Flock } from '@/types/flock'

interface FarmState {
  farms: Farm[]
  currentFarm: Farm | null
  activeFlock: Flock | null

  setFarms: (farms: Farm[]) => void
  setCurrentFarm: (farm: Farm) => void
  setActiveFlock: (flock: Flock | null) => void
  clearFarm: () => void
}

export const useFarmStore = create<FarmState>()(
  persist(
    (set) => ({
      farms: [],
      currentFarm: null,
      activeFlock: null,

      setFarms: (farms) => set({ farms }),

      setCurrentFarm: (farm) => {
        if (typeof window !== 'undefined') {
          localStorage.setItem('current_farm_id', String(farm.id))
        }
        set({ currentFarm: farm, activeFlock: null })
      },

      setActiveFlock: (flock) => set({ activeFlock: flock }),

      clearFarm: () => {
        if (typeof window !== 'undefined') {
          localStorage.removeItem('current_farm_id')
        }
        set({ currentFarm: null, activeFlock: null })
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
        activeFlock: state.activeFlock,
      }),
    }
  )
)

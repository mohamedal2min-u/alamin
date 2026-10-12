'use client'

import { create } from 'zustand'
import { persist, createJSONStorage } from 'zustand/middleware'
import type { MethodKey, PrayerKey } from './times'

export type AdhanSound = 'tone' | 'custom'

export interface PrayerState {
  enabled: boolean
  /** تشغيل كل صلاة على حدة */
  prayers: Record<PrayerKey, boolean>
  method: MethodKey
  hanafiAsr: boolean
  adjust: Record<PrayerKey, number>
  sound: AdhanSound
  volume: number // 0..1
  /** اسم الملف المرفوع (الملف نفسه في IndexedDB) */
  customName: string | null
  set: (patch: Partial<Omit<PrayerState, 'set'>>) => void
}

export const usePrayerStore = create<PrayerState>()(
  persist(
    (set) => ({
      enabled: false,
      prayers: { fajr: true, dhuhr: true, asr: true, maghrib: true, isha: true },
      method: 'MuslimWorldLeague',
      hanafiAsr: false,
      adjust: { fajr: 0, dhuhr: 0, asr: 0, maghrib: 0, isha: 0 },
      sound: 'tone',
      volume: 0.8,
      customName: null,
      set: (patch) => set(patch),
    }),
    {
      name: 'alamin-prayer',
      storage: createJSONStorage(() => (typeof window !== 'undefined' ? localStorage : ({} as Storage))),
    },
  ),
)

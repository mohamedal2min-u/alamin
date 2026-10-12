'use client'

import { create } from 'zustand'
import { persist, createJSONStorage } from 'zustand/middleware'
import { DEFAULT_VOICE } from './voices'

/** أقل مستوى صوت مسموح: لا يمكن كتم الأذان */
export const MIN_VOLUME = 0.4

export interface PrayerState {
  /** معرّف الصوت المختار من القائمة المضمّنة */
  voice: string
  volume: number // MIN_VOLUME..1
  set: (patch: Partial<Pick<PrayerState, 'voice' | 'volume'>>) => void
}

// الأذان إلزامي ومواقيته ثابتة: لا يوجد هنا تشغيل/إيقاف ولا تعديل توقيت
export const usePrayerStore = create<PrayerState>()(
  persist(
    (set) => ({
      voice: DEFAULT_VOICE,
      volume: 0.8,
      set: (patch) =>
        set({
          ...patch,
          ...(patch.volume !== undefined ? { volume: Math.max(MIN_VOLUME, Math.min(1, patch.volume)) } : {}),
        }),
    }),
    {
      name: 'alamin-prayer',
      version: 2,
      // الإعدادات القديمة (تشغيل/إيقاف، دقائق، ملف مرفوع) تُهمل
      migrate: (old) => {
        const o = (old ?? {}) as { volume?: number }
        return { voice: DEFAULT_VOICE, volume: Math.max(MIN_VOLUME, Math.min(1, o.volume ?? 0.8)) } as PrayerState
      },
      storage: createJSONStorage(() => (typeof window !== 'undefined' ? localStorage : ({} as Storage))),
      partialize: (s) => ({ voice: s.voice, volume: s.volume }) as PrayerState,
    },
  ),
)

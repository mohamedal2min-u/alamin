'use client'

import { create } from 'zustand'
import { persist, createJSONStorage } from 'zustand/middleware'
import { DEFAULT_VOICE, voiceById, type AdhanLength } from './voices'

/** أقل مستوى صوت مسموح: لا يمكن كتم الأذان */
export const MIN_VOLUME = 0.4

export interface PrayerState {
  /** معرّف الصوت المختار من القائمة المضمّنة */
  voice: string
  /** الأذان كاملاً أو مقطعه الأول فقط */
  length: AdhanLength
  volume: number // MIN_VOLUME..1
  set: (patch: Partial<Pick<PrayerState, 'voice' | 'length' | 'volume'>>) => void
}

// الأذان إلزامي ومواقيته ثابتة: لا يوجد هنا تشغيل/إيقاف ولا تعديل توقيت
export const usePrayerStore = create<PrayerState>()(
  persist(
    (set) => ({
      voice: DEFAULT_VOICE,
      length: 'long',
      volume: 1,
      set: (patch) =>
        set({
          ...patch,
          ...(patch.volume !== undefined ? { volume: Math.max(MIN_VOLUME, Math.min(1, patch.volume)) } : {}),
        }),
    }),
    {
      name: 'alamin-prayer',
      version: 3,
      // الإعدادات القديمة تُهمل؛ الصوت المختار يبقى إن كان ما زال موجوداً، والمستوى يرتفع للأعلى
      migrate: (old) => {
        const o = (old ?? {}) as { voice?: string }
        return { voice: voiceById(o.voice ?? DEFAULT_VOICE).id, length: 'long', volume: 1 } as PrayerState
      },
      storage: createJSONStorage(() => (typeof window !== 'undefined' ? localStorage : ({} as Storage))),
      partialize: (s) => ({ voice: s.voice, length: s.length, volume: s.volume }) as PrayerState,
    },
  ),
)

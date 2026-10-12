'use client'

import { useCallback, useEffect, useRef, useState } from 'react'
import { BellRing, Square, Volume2 } from 'lucide-react'
import { usePrayerStore } from '@/lib/prayer/store'
import { upcomingPrayers, REGION_NAME, type PrayerCalcSettings, type PrayerKey } from '@/lib/prayer/times'
import { playAdhan, stopAdhan, unlockAudio } from '@/lib/prayer/audio'
import { isNativeApp, syncNativeSchedule } from '@/lib/prayer/native'

/**
 * يُركَّب مرة واحدة في تخطيط المزرعة: يشغّل الأذان في وقته (توقيت إدلب — جبل الزاوية)
 * أثناء فتح التطبيق، ويجدول إشعارات الأذان على تطبيق الأندرويد ليصل والتطبيق مغلق.
 */
export function PrayerScheduler() {
  const enabled = usePrayerStore((s) => s.enabled)
  const prayers = usePrayerStore((s) => s.prayers)
  const method = usePrayerStore((s) => s.method)
  const hanafiAsr = usePrayerStore((s) => s.hanafiAsr)
  const adjust = usePrayerStore((s) => s.adjust)
  const sound = usePrayerStore((s) => s.sound)
  const volume = usePrayerStore((s) => s.volume)

  const [ringing, setRinging] = useState<{ label: string } | null>(null)
  const [blocked, setBlocked] = useState<{ label: string } | null>(null)
  const nativeOk = useRef(false)
  const lastFired = useRef(0)

  const calc: PrayerCalcSettings = { method, hanafiAsr, adjust }
  const calcKey = JSON.stringify(calc)
  const prayersKey = JSON.stringify(prayers)

  const fire = useCallback(async (label: string) => {
    const ok = await playAdhan({ sound, volume })
    if (ok) { setBlocked(null); setRinging({ label }) } else { setBlocked({ label }) }
    if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
      try { new Notification(`حان الآن وقت صلاة ${label}`, { body: `حسب توقيت ${REGION_NAME}`, tag: 'prayer' }) } catch { /* بعض الأجهزة تمنعه */ }
    }
  }, [sound, volume])

  // فتح قفل الصوت بأول لمسة في التطبيق
  useEffect(() => {
    const on = () => unlockAudio()
    window.addEventListener('pointerdown', on, { once: true })
    return () => window.removeEventListener('pointerdown', on)
  }, [])

  // جدولة الإشعارات الأصلية (أندرويد)
  useEffect(() => {
    if (!isNativeApp()) return
    const parsed = JSON.parse(calcKey) as PrayerCalcSettings
    void syncNativeSchedule(enabled, JSON.parse(prayersKey), parsed).then((r) => { nativeOk.current = r === 'ok' })
  }, [enabled, calcKey, prayersKey])

  // مؤقّت داخل التطبيق: الصلاة القادمة المفعّلة
  useEffect(() => {
    if (!enabled) return
    const parsed = JSON.parse(calcKey) as PrayerCalcSettings
    const on = JSON.parse(prayersKey) as Record<PrayerKey, boolean>
    let timer: ReturnType<typeof setTimeout> | undefined

    const arm = () => {
      clearTimeout(timer)
      const now = new Date()
      const next = upcomingPrayers(new Date(now.getTime() - 90_000), parsed, 2).find((p) => on[p.key] && p.at.getTime() > lastFired.current)
      if (!next) return
      const delay = Math.max(0, next.at.getTime() - Date.now())
      timer = setTimeout(() => {
        // على الأندرويد الإشعار الأصلي هو الأذان؛ لا نكرّره داخل التطبيق إلا إن فشلت الجدولة
        if (!(isNativeApp() && nativeOk.current)) void fire(next.label)
        lastFired.current = next.at.getTime()
        arm()
      }, Math.min(delay, 2_000_000_000))
    }

    arm()
    const onVisible = () => { if (document.visibilityState === 'visible') arm() }
    document.addEventListener('visibilitychange', onVisible)
    return () => { clearTimeout(timer); document.removeEventListener('visibilitychange', onVisible) }
  }, [enabled, calcKey, prayersKey, fire])

  if (!ringing && !blocked) return null

  const label = (ringing ?? blocked)!.label
  return (
    <div className="fixed inset-x-0 top-0 z-[200] px-3 pt-3" dir="rtl" role="alert">
      <div className="mx-auto flex max-w-2xl items-center gap-3 rounded-2xl bg-slate-900 p-3.5 text-white shadow-2xl ring-1 ring-white/10">
        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-600">
          {ringing ? <Volume2 className="h-5 w-5" /> : <BellRing className="h-5 w-5" />}
        </span>
        <div className="min-w-0 flex-1">
          <p className="text-sm font-extrabold">حان الآن وقت صلاة {label}</p>
          <p className="mt-0.5 text-[11px] text-slate-300">{blocked ? 'المتصفح منع الصوت — اضغط لتشغيل الأذان' : `حسب توقيت ${REGION_NAME}`}</p>
        </div>
        {blocked ? (
          <button
            onClick={() => { unlockAudio(); void fire(blocked.label) }}
            className="shrink-0 rounded-xl bg-primary-600 px-4 py-2.5 text-xs font-bold active:scale-95"
          >
            تشغيل
          </button>
        ) : (
          <button
            onClick={() => { stopAdhan(); setRinging(null) }}
            className="flex shrink-0 items-center gap-1.5 rounded-xl bg-white/10 px-4 py-2.5 text-xs font-bold active:scale-95"
          >
            <Square className="h-3.5 w-3.5 fill-current" /> إيقاف
          </button>
        )}
        {blocked && (
          <button onClick={() => setBlocked(null)} className="shrink-0 rounded-xl bg-white/10 px-3 py-2.5 text-xs font-bold">إغلاق</button>
        )}
      </div>
    </div>
  )
}

'use client'

import { useEffect, useState } from 'react'
import { BellOff } from 'lucide-react'
import { Capacitor } from '@capacitor/core'

type Status = 'granted' | 'prompt' | 'denied'

async function readStatus(): Promise<Status> {
  const { PushNotifications } = await import('@capacitor/push-notifications')
  const p = await PushNotifications.checkPermissions()
  if (p.receive === 'granted') return 'granted'
  return p.receive === 'denied' ? 'denied' : 'prompt'
}

/**
 * على تطبيق الأندرويد: يطلب إذن الإشعارات تلقائياً عند الفتح (الأذان + إشعارات التعديلات)،
 * وإن بقيت متوقفة يظهر شريط بزر تفعيل، أو بخطوات الإعدادات إن رفضها المستخدم سابقاً.
 */
export function NotificationPermissionBanner() {
  const [status, setStatus] = useState<Status>('granted')

  useEffect(() => {
    if (!Capacitor.isNativePlatform() || !Capacitor.isPluginAvailable('PushNotifications')) return
    let alive = true
    const check = async () => {
      try {
        let s = await readStatus()
        if (s === 'prompt') {
          const { PushNotifications } = await import('@capacitor/push-notifications')
          const r = await PushNotifications.requestPermissions()
          s = r.receive === 'granted' ? 'granted' : r.receive === 'denied' ? 'denied' : 'prompt'
        }
        if (alive) setStatus(s)
      } catch { /* نسخة قديمة بلا الإضافة */ }
    }
    void check()
    const onVisible = () => { if (document.visibilityState === 'visible') void readStatus().then((s) => alive && setStatus(s)).catch(() => {}) }
    document.addEventListener('visibilitychange', onVisible)
    return () => { alive = false; document.removeEventListener('visibilitychange', onVisible) }
  }, [])

  if (status === 'granted') return null

  const enable = async () => {
    const { PushNotifications } = await import('@capacitor/push-notifications')
    const r = await PushNotifications.requestPermissions()
    setStatus(r.receive === 'granted' ? 'granted' : 'denied')
  }

  return (
    <div className="mx-auto mt-2 flex max-w-2xl items-center gap-3 rounded-2xl border border-amber-300 bg-amber-50 px-3.5 py-3 dark:border-amber-800 dark:bg-amber-900/20" dir="rtl" role="alert">
      <BellOff className="h-5 w-5 shrink-0 text-amber-600" />
      <div className="min-w-0 flex-1 text-[12px] font-semibold text-amber-800 dark:text-amber-300">
        الإشعارات متوقفة: لن يصلك الأذان ولا إشعارات التعديلات.
        {status === 'denied' && <span className="block font-normal">افتح: الإعدادات ← التطبيقات ← YMD ← الإشعارات، وفعّلها.</span>}
      </div>
      {status === 'prompt' && (
        <button type="button" onClick={() => void enable()} className="shrink-0 rounded-xl bg-amber-600 px-3 py-2 text-xs font-bold text-white active:scale-95">
          تفعيل
        </button>
      )}
    </div>
  )
}

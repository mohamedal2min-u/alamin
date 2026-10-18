'use client'

import { useEffect, useRef } from 'react'
import { toast } from 'sonner'
import { Capacitor } from '@capacitor/core'
import { useAuthStore } from '@/stores/auth.store'
import { apiClient } from '@/lib/api/client'
import { playEditTone, type EditKind } from '@/lib/prayer/audio'

interface FeedItem { id: number; kind: EditKind; title: string; body: string }

/**
 * إشعارات التعديلات داخل التطبيق المفتوح: المحاسب عدّل ← نغمة للمدير، والمدير عدّل ← نغمة للمحاسب.
 * استعلام خفيف كل دقيقتين والتطبيق ظاهر فقط (يتوقف بالخلفية)، وفوراً عند العودة للتطبيق.
 * (والتطبيق المغلق يصله الدفع FCM بنغمة القناة نفسها).
 */
export function EditNotificationListener() {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated)
  const lastId = useRef<number | null>(null)

  useEffect(() => {
    if (!isAuthenticated) { lastId.current = null; return }
    // تطبيق الأندرويد يستقبل الإشعارات دفعاً (FCM) دون أي استعلام دوري، فلا نستهلك البطارية بالاستعلام
    if (Capacitor.isNativePlatform() && Capacitor.isPluginAvailable('PushNotifications')) return
    let timer: ReturnType<typeof setInterval> | undefined
    let cancelled = false

    const poll = async () => {
      try {
        const base = lastId.current
        const { data } = await apiClient.get<{ data: FeedItem[]; last_id: number }>('/auth/notifications/feed', { params: base == null ? {} : { after_id: base } })
        if (cancelled) return
        lastId.current = data.last_id
        if (base != null && data.data.length > 0) {
          const latest = data.data[data.data.length - 1]
          playEditTone(latest.kind)
          for (const n of data.data.slice(-3)) toast(n.title, { description: n.body })
        }
      } catch {
        /* لا بأس: نعيد المحاولة في الدورة التالية */
      }
    }

    const start = () => {
      if (timer) return
      void poll()
      timer = setInterval(poll, 120_000)
    }
    const stop = () => { if (timer) clearInterval(timer); timer = undefined }
    const onVisibility = () => (document.visibilityState === 'visible' ? start() : stop())

    if (document.visibilityState === 'visible') start()
    document.addEventListener('visibilitychange', onVisibility)
    return () => { cancelled = true; stop(); document.removeEventListener('visibilitychange', onVisibility) }
  }, [isAuthenticated])

  return null
}

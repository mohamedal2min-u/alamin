'use client'

import { Capacitor } from '@capacitor/core'
import type { FlockVaccination } from '@/types/vaccination'

// نطاق معرّفات إشعارات اللقاحات (الأذان يستخدم 7000+)
const BASE_ID = 9000
const MAX_COUNT = 100

/** 08:00 بتوقيت دمشق (UTC+3 طوال السنة) ليوم الاستحقاق */
function damascusEightAm(date: string): Date {
  return new Date(`${date}T08:00:00+03:00`)
}

/**
 * على تطبيق الأندرويد: يجدول إشعاراً محلياً الساعة 08:00 بتوقيت دمشق لكل يوم استحقاق قادم
 * للفوج النشط. يُستدعى عند فتح التطبيق وعند تغيّر البرنامج — لا استطلاع دوري.
 */
export async function syncVaccineNotifications(flockName: string | null, rows: FlockVaccination[]): Promise<void> {
  if (typeof window === 'undefined' || !Capacitor.isNativePlatform()) return
  try {
    const { LocalNotifications } = await import('@capacitor/local-notifications')

    const pending = await LocalNotifications.getPending()
    const ours = pending.notifications.filter((n) => n.id >= BASE_ID && n.id < BASE_ID + MAX_COUNT)
    if (ours.length) await LocalNotifications.cancel({ notifications: ours.map((n) => ({ id: n.id })) })

    // تجميع اللقاحات حسب يوم الاستحقاق: إشعار واحد لكل يوم
    const now = Date.now()
    const byDate = new Map<string, string[]>()
    for (const r of rows) {
      if (r.status !== 'due') continue
      if (damascusEightAm(r.due_date).getTime() <= now) continue
      byDate.set(r.due_date, [...(byDate.get(r.due_date) ?? []), r.title])
    }
    if (!flockName || byDate.size === 0) return

    let perm = await LocalNotifications.checkPermissions()
    if (perm.display === 'prompt' || perm.display === 'prompt-with-rationale') {
      perm = await LocalNotifications.requestPermissions()
    }
    if (perm.display !== 'granted') return

    await LocalNotifications.createChannel({
      id: 'vaccine',
      name: 'اللقاحات',
      description: 'تذكير بمواعيد لقاحات الفوج',
      importance: 4,
      visibility: 1,
      vibration: true,
    })

    const notifications = [...byDate.entries()]
      .sort(([a], [b]) => a.localeCompare(b))
      .slice(0, MAX_COUNT)
      .map(([date, titles], i) => ({
        id: BASE_ID + i,
        title: `لقاح اليوم — ${flockName}`,
        body: titles.join('، '),
        channelId: 'vaccine',
        schedule: { at: damascusEightAm(date), allowWhileIdle: true },
        extra: { type: 'vaccine' },
      }))

    await LocalNotifications.schedule({ notifications })
  } catch {
    // APK قديم بلا الإضافة أو رفض النظام — التذكير عبر الدفع والبطاقة يبقى
  }
}

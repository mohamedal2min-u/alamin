'use client'

import { Capacitor } from '@capacitor/core'
import { PRAYERS, REGION_NAME, upcomingPrayers, type PrayerCalcSettings, type PrayerKey } from './times'

export const isNativeApp = () => typeof window !== 'undefined' && Capacitor.isNativePlatform()

const BASE_ID = 7000

export type NativeResult = 'ok' | 'denied' | 'unavailable'

/**
 * على تطبيق الأندرويد: يجدول إشعارات الأذان لأسبوع قادم فتصل حتى لو كان التطبيق مغلقاً.
 * يُعاد استدعاؤه عند فتح التطبيق وعند تغيير الإعدادات. يحتاج APK يحتوي إضافة الإشعارات المحلية.
 */
export async function syncNativeSchedule(calc: PrayerCalcSettings): Promise<NativeResult> {
  if (!isNativeApp()) return 'unavailable'
  try {
    const { LocalNotifications } = await import('@capacitor/local-notifications')

    // ألغِ المجدول سابقاً
    const pending = await LocalNotifications.getPending()
    const ours = pending.notifications.filter((n) => n.id >= BASE_ID && n.id < BASE_ID + 1000)
    if (ours.length) await LocalNotifications.cancel({ notifications: ours.map((n) => ({ id: n.id })) })

    let perm = await LocalNotifications.checkPermissions()
    if (perm.display !== 'granted') perm = await LocalNotifications.requestPermissions()
    if (perm.display !== 'granted') return 'denied'

    // قناة جديدة بصوت الأذان المضمّن (res/raw/adhan.mp3)؛ القناة القديمة بلا صوت أذان تُترك كما هي
    await LocalNotifications.createChannel({
      id: 'adhan',
      name: 'الأذان',
      description: `تنبيه الأذان حسب توقيت ${REGION_NAME}`,
      importance: 5,
      visibility: 1,
      vibration: true,
      sound: 'adhan.mp3',
    })

    const now = new Date()
    const list = upcomingPrayers(now, calc, 8)
    const notifications = list.slice(0, 48).map((p, i) => ({
      id: BASE_ID + i,
      title: `حان الآن وقت صلاة ${p.label}`,
      body: `حسب توقيت ${REGION_NAME}`,
      channelId: 'adhan',
      sound: 'adhan.mp3',
      schedule: { at: p.at, allowWhileIdle: true },
    }))
    if (notifications.length) await LocalNotifications.schedule({ notifications })
    return 'ok'
  } catch {
    return 'unavailable'
  }
}

export const prayerLabel = (k: PrayerKey) => PRAYERS.find((p) => p.key === k)?.label ?? ''

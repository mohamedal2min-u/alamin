'use client'

import { Capacitor } from '@capacitor/core'
import { PRAYERS, REGION_NAME, upcomingPrayers, type PrayerCalcSettings, type PrayerKey } from './times'
import { voiceById, type AdhanLength } from './voices'

export const isNativeApp = () => typeof window !== 'undefined' && Capacitor.isNativePlatform()

const BASE_ID = 7000

export type NativeResult = 'ok' | 'denied' | 'unavailable'

/**
 * على تطبيق الأندرويد: يجدول إشعارات الأذان لأسبوع قادم فتصل حتى لو كان التطبيق مغلقاً.
 * يُعاد استدعاؤه عند فتح التطبيق وعند تغيير الإعدادات. يحتاج APK يحتوي إضافة الإشعارات المحلية.
 */
export async function syncNativeSchedule(calc: PrayerCalcSettings, voiceId: string, length: AdhanLength): Promise<NativeResult> {
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

    // أندرويد 12+: بدون إذن «المنبّهات والتذكيرات» قد يتأخر الأذان أو لا يصل بدقة. نطلبه مرة واحدة فقط.
    try {
      const exact = await LocalNotifications.checkExactNotificationSetting()
      if (exact.exact_alarm !== 'granted' && !localStorage.getItem('exact_alarm_asked')) {
        localStorage.setItem('exact_alarm_asked', '1')
        await LocalNotifications.changeExactNotificationSetting()
      }
    } catch { /* نسخ أندرويد القديمة لا تحتاجه */ }

    // قناة لكل صوت: res/raw/adhan_<صوت>_<طول>.mp3. التطبيق (MainActivity) ينشئها مسبقاً على صوت المنبّه،
    // وهذا الإنشاء احتياط فقط (أندرويد لا يغيّر صوت قناة موجودة)
    const v = voiceById(voiceId)
    const raw = `adhan_${v.raw}_${length}`
    const channelId = raw
    await LocalNotifications.createChannel({
      id: channelId,
      name: `الأذان — ${v.name} (${length === 'short' ? 'قصير' : 'كامل'})`,
      description: `تنبيه الأذان حسب توقيت ${REGION_NAME}`,
      importance: 5,
      visibility: 1,
      vibration: true,
      sound: `${raw}.mp3`,
    })

    const now = new Date()
    const list = upcomingPrayers(now, calc, 8)
    const notifications = list.slice(0, 48).map((p, i) => ({
      id: BASE_ID + i,
      title: `حان الآن وقت صلاة ${p.label}`,
      body: `حسب توقيت ${REGION_NAME}`,
      channelId,
      sound: `${raw}.mp3`,
      schedule: { at: p.at, allowWhileIdle: true },
    }))
    if (notifications.length) await LocalNotifications.schedule({ notifications })
    return 'ok'
  } catch {
    return 'unavailable'
  }
}

export const prayerLabel = (k: PrayerKey) => PRAYERS.find((p) => p.key === k)?.label ?? ''

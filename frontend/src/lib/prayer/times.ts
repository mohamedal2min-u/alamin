import { CalculationMethod, Coordinates, Madhab, PrayerTimes } from 'adhan'

// المنطقة ثابتة: إدلب — جبل الزاوية (لا تُعدَّل من الإعدادات)
export const REGION_NAME = 'إدلب — جبل الزاوية'
export const REGION_COORDS = { latitude: 35.64, longitude: 36.55 }
// سوريا على توقيت دائم UTC+3 منذ 2022؛ نستعمل المنطقة الزمنية لا توقيت الجهاز
export const REGION_TZ = 'Asia/Damascus'

export type PrayerKey = 'fajr' | 'dhuhr' | 'asr' | 'maghrib' | 'isha'
export const PRAYERS: { key: PrayerKey; label: string }[] = [
  { key: 'fajr', label: 'الفجر' },
  { key: 'dhuhr', label: 'الظهر' },
  { key: 'asr', label: 'العصر' },
  { key: 'maghrib', label: 'المغرب' },
  { key: 'isha', label: 'العشاء' },
]

export type MethodKey = 'MuslimWorldLeague' | 'Egyptian' | 'UmmAlQura' | 'Karachi' | 'Turkey'
export const METHODS: { key: MethodKey; label: string; hint: string }[] = [
  { key: 'MuslimWorldLeague', label: 'رابطة العالم الإسلامي', hint: 'الفجر 18° — العشاء 17°' },
  { key: 'Egyptian', label: 'الهيئة المصرية', hint: 'الفجر 19.5° — العشاء 17.5°' },
  { key: 'UmmAlQura', label: 'أم القرى', hint: 'الفجر 18.5° — العشاء 90 دقيقة بعد المغرب' },
  { key: 'Karachi', label: 'جامعة كراتشي', hint: 'الفجر 18° — العشاء 18°' },
  { key: 'Turkey', label: 'ديانت التركية', hint: 'الفجر 18° — العشاء 17°' },
]

/** الإعدادات ثابتة لكل المستخدمين ولا تُعدَّل من التطبيق (رابطة العالم الإسلامي، العصر الشافعي، بلا تعديل دقائق) */
export const FIXED_CALC: PrayerCalcSettings = {
  method: 'MuslimWorldLeague',
  hanafiAsr: false,
  adjust: { fajr: 0, dhuhr: 0, asr: 0, maghrib: 0, isha: 0 },
}

export interface PrayerCalcSettings {
  method: MethodKey
  hanafiAsr: boolean
  /** تعديل يدوي بالدقائق لكل صلاة (لمطابقة تقويم مسجد المنطقة) */
  adjust: Record<PrayerKey, number>
}

/** تاريخ اليوم في إدلب (سنة/شهر/يوم) بصرف النظر عن منطقة الجهاز */
export function regionDate(now: Date = new Date()): { y: number; m: number; d: number } {
  const parts = new Intl.DateTimeFormat('en-CA', { timeZone: REGION_TZ, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(now)
  const get = (t: string) => Number(parts.find((p) => p.type === t)?.value)
  return { y: get('year'), m: get('month'), d: get('day') }
}

/** أوقات الصلوات ليوم معيّن في إدلب (لحظات مطلقة Date) */
export function prayerTimesFor(day: { y: number; m: number; d: number }, s: PrayerCalcSettings): Record<PrayerKey | 'sunrise', Date> {
  const params = CalculationMethod[s.method]()
  params.madhab = s.hanafiAsr ? Madhab.Hanafi : Madhab.Shafi
  // adhan يقرأ يوم/شهر/سنة من مكوّنات التاريخ المحلية، فننشئه بتلك المكوّنات
  const pt = new PrayerTimes(new Coordinates(REGION_COORDS.latitude, REGION_COORDS.longitude), new Date(day.y, day.m - 1, day.d), params)
  const add = (d: Date, min: number) => new Date(d.getTime() + min * 60_000)
  return {
    fajr: add(pt.fajr, s.adjust.fajr),
    sunrise: pt.sunrise,
    dhuhr: add(pt.dhuhr, s.adjust.dhuhr),
    asr: add(pt.asr, s.adjust.asr),
    maghrib: add(pt.maghrib, s.adjust.maghrib),
    isha: add(pt.isha, s.adjust.isha),
  }
}

export interface UpcomingPrayer { key: PrayerKey; label: string; at: Date }

/** الصلوات القادمة بعد لحظة معيّنة خلال عدد أيام (اليوم + التالية) */
export function upcomingPrayers(from: Date, s: PrayerCalcSettings, days = 2): UpcomingPrayer[] {
  const out: UpcomingPrayer[] = []
  const base = regionDate(from)
  for (let i = 0; i < days; i++) {
    const day = new Date(Date.UTC(base.y, base.m - 1, base.d + i))
    const t = prayerTimesFor({ y: day.getUTCFullYear(), m: day.getUTCMonth() + 1, d: day.getUTCDate() }, s)
    for (const p of PRAYERS) {
      if (t[p.key].getTime() > from.getTime()) out.push({ key: p.key, label: p.label, at: t[p.key] })
    }
  }
  return out
}

export function formatRegionTime(d: Date): string {
  return new Intl.DateTimeFormat('ar-EG-u-nu-latn', { timeZone: REGION_TZ, hour: 'numeric', minute: '2-digit', hour12: true }).format(d)
}

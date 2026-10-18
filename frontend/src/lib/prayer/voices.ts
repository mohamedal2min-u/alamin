// أصوات الأذان المضمّنة في التطبيق. لكل صوت ملفان: كامل وقصير (المقطع الأول)، بنفس الاسم في
// public/adhan (للتشغيل داخل التطبيق) و android/app/src/main/res/raw (لإشعار الأذان والتطبيق مغلق):
//   adhan_<raw>_long.mp3 و adhan_<raw>_short.mp3
// لإضافة صوت: ضع الملفين في المجلدين وأضف سطراً هنا (ثم ابنِ نسخة APK جديدة). المصادر في public/adhan/CREDITS.md
export type AdhanLength = 'long' | 'short'

export interface AdhanVoice {
  id: string
  /** جزء اسم الملف: أحرف إنجليزية صغيرة وأرقام فقط */
  raw: string
  name: string
  note: string
  license: string
}

export const VOICES: AdhanVoice[] = [
  { id: 'voice1', raw: 'v1', name: 'المؤذن 1', note: 'صوت هادئ', license: 'CC0 — Adam-synagda (Wikimedia Commons)' },
  { id: 'voice2', raw: 'v2', name: 'المؤذن 2', note: 'تسجيل قصير', license: 'Public domain (Wikimedia Commons)' },
]

export const DEFAULT_VOICE = VOICES[0].id
export const voiceById = (id: string) => VOICES.find((v) => v.id === id) ?? VOICES[0]
export const voiceFile = (v: AdhanVoice, length: AdhanLength) => `/adhan/adhan_${v.raw}_${length}.mp3`

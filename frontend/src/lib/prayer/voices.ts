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
  { id: 'voice1', raw: 'v1', name: 'أذان هادئ', note: 'صوت هادئ وواضح', license: 'CC0 — Adam-synagda (Wikimedia Commons)' },
  { id: 'voice2', raw: 'v2', name: 'أذان مختصر', note: 'تسجيل قصير', license: 'Public domain (Wikimedia Commons)' },
  { id: 'voice3', raw: 'v3', name: 'عاقب عزيز', note: 'تسجيل نقي', license: 'CC BY-SA 4.0 — Atcovi (Wikimedia Commons)' },
  { id: 'voice4', raw: 'v4', name: 'الحرم المكي', note: 'المسجد الحرام، مكة 2013', license: 'CC BY 3.0 — Seyfula Islam (Wikimedia Commons)' },
  { id: 'voice5', raw: 'v5', name: 'مسجد شالقار', note: 'تسجيل نقي من المسجد', license: 'CC BY-SA 4.0 — Esetok (Wikimedia Commons)' },
  { id: 'voice6', raw: 'v6', name: 'مسجد الحسن الثاني', note: 'الدار البيضاء، المغرب', license: 'CC BY-SA 4.0 — Fraguando (Wikimedia Commons)' },
  { id: 'voice7', raw: 'v7', name: 'قونية', note: 'تركيا', license: 'CC BY 3.0 — Frans van der Vaart (Wikimedia Commons)' },
  { id: 'voice8', raw: 'v8', name: 'أذان مغاربي', note: 'تسجيل طويل', license: 'CC BY-SA 4.0 — Mahfoudou (Wikimedia Commons)' },
  { id: 'voice9', raw: 'v9', name: 'إسطنبول', note: 'تسجيل من الشارع', license: 'CC0 — Viceskeeni2 (Wikimedia Commons)' },
  { id: 'voice10', raw: 'v10', name: 'الحرم المكي (المغرب)', note: 'تسجيل حي فيه أصوات المصلين', license: 'CC BY 3.0 — 3omar Faruq (Wikimedia Commons)' },
]

export const DEFAULT_VOICE = VOICES[0].id
export const voiceById = (id: string) => VOICES.find((v) => v.id === id) ?? VOICES[0]
export const voiceFile = (v: AdhanVoice, length: AdhanLength) => `/adhan/adhan_${v.raw}_${length}.mp3`

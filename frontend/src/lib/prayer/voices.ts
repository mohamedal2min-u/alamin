// أصوات الأذان المضمّنة في التطبيق (ملفات في public/adhan). لإضافة صوت: انسخ الملف إلى المجلد وأضف سطراً هنا.
export interface AdhanVoice {
  id: string
  name: string
  note: string
  file: string
  license: string
}

export const VOICES: AdhanVoice[] = [
  { id: 'voice1', name: 'أذان كامل', note: 'تسجيل كامل بصوت هادئ', file: '/adhan/voice1.mp3', license: 'CC0 — Adam-synagda (Wikimedia Commons)' },
  { id: 'voice2', name: 'أذان قصير', note: 'مقطع قصير', file: '/adhan/voice2.mp3', license: 'Public domain (Wikimedia Commons)' },
]

export const DEFAULT_VOICE = VOICES[0].id
export const voiceById = (id: string) => VOICES.find((v) => v.id === id) ?? VOICES[0]

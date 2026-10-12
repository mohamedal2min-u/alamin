'use client'

import { MIN_VOLUME } from './store'
import { voiceById } from './voices'

// ── التشغيل ──────────────────────────────────────────────────────────────────
let current: { stop: () => void } | null = null
let unlocked = false

export function stopAdhan() {
  current?.stop()
  current = null
}

/** يفتح قفل الصوت في المتصفح (يلزمه ضغطة من المستخدم مرة واحدة على الأقل) */
export function unlockAudio() {
  if (unlocked || typeof window === 'undefined') return
  try {
    const Ctx = window.AudioContext ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext
    const ctx = Ctx ? new Ctx() : null
    void ctx?.resume()
    unlocked = true
  } catch {
    /* لا شيء */
  }
}

/** نغمة تنبيه هادئة مركّبة بلا ملفات (تتصاعد ثم تهدأ) */
function playTone(volume: number): { stop: () => void; done: Promise<void> } {
  const Ctx = window.AudioContext ?? (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext
  const ctx = new Ctx()
  void ctx.resume()
  const master = ctx.createGain()
  master.gain.value = Math.max(0, Math.min(1, volume)) * 0.5
  master.connect(ctx.destination)

  // نغمات متتابعة بنمط ثابت: ثلاث دورات
  const notes = [392, 494, 587, 784, 587, 494] // سلّم بسيط
  let t = ctx.currentTime + 0.05
  for (let round = 0; round < 3; round++) {
    for (const f of notes) {
      const osc = ctx.createOscillator()
      const g = ctx.createGain()
      osc.type = 'sine'
      osc.frequency.value = f
      g.gain.setValueAtTime(0.0001, t)
      g.gain.exponentialRampToValueAtTime(1, t + 0.04)
      g.gain.exponentialRampToValueAtTime(0.0001, t + 0.7)
      osc.connect(g).connect(master)
      osc.start(t)
      osc.stop(t + 0.75)
      t += 0.55
    }
    t += 0.8
  }
  const total = (t - ctx.currentTime) * 1000
  let timer: ReturnType<typeof setTimeout>
  const done = new Promise<void>((resolve) => {
    timer = setTimeout(() => { void ctx.close(); resolve() }, total)
  })
  return { stop: () => { clearTimeout(timer); void ctx.close() }, done }
}

export interface PlayOptions {
  voice: string
  volume: number
}

/** يشغّل الأذان بالصوت المختار. يرجع false إن مُنع التشغيل (سياسة المتصفح)؛ وإن تعذّر الملف تُشغَّل نغمة بدلاً منه. */
export async function playAdhan(opts: PlayOptions): Promise<boolean> {
  stopAdhan()
  const volume = Math.max(MIN_VOLUME, Math.min(1, opts.volume))
  try {
    const audio = new Audio(voiceById(opts.voice).file)
    audio.volume = volume
    current = { stop: () => audio.pause() }
    audio.addEventListener('ended', () => { current = null })
    try {
      await audio.play()
      return true
    } catch (e) {
      if ((e as DOMException)?.name === 'NotAllowedError') { current = null; return false }
      // الملف غير متاح (بلا اتصال مثلاً): نغمة التنبيه بدلاً منه
    }
    const tone = playTone(volume)
    current = { stop: tone.stop }
    void tone.done.then(() => { current = null })
    return true
  } catch {
    current = null
    return false
  }
}

// ── نغمات التعديلات: لكل طرف نغمة مختلفة ───────────────────────────────────
export type EditKind = 'accountant_edit' | 'manager_edit'

/** نغمة قصيرة عند وصول إشعار تعديل والتطبيق مفتوح */
export function playEditTone(kind: EditKind) {
  try {
    const audio = new Audio(`/adhan/${kind}.wav`)
    audio.volume = 0.9
    void audio.play().catch(() => {})
  } catch {
    /* لا شيء */
  }
}

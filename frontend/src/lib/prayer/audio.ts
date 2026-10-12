'use client'

// ── ملف الأذان المخصص: يُحفظ على الجهاز نفسه (IndexedDB) ولا يُرفع للسيرفر ───────────────
const DB = 'alamin-prayer-audio'
const STORE = 'files'
const KEY = 'custom'
export const MAX_AUDIO_BYTES = 8 * 1024 * 1024

function open(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB, 1)
    req.onupgradeneeded = () => req.result.createObjectStore(STORE)
    req.onsuccess = () => resolve(req.result)
    req.onerror = () => reject(req.error)
  })
}

export async function saveCustomAudio(file: Blob): Promise<void> {
  const db = await open()
  await new Promise<void>((resolve, reject) => {
    const tx = db.transaction(STORE, 'readwrite')
    tx.objectStore(STORE).put(file, KEY)
    tx.oncomplete = () => resolve()
    tx.onerror = () => reject(tx.error)
  })
}

export async function loadCustomAudio(): Promise<Blob | null> {
  try {
    const db = await open()
    return await new Promise((resolve, reject) => {
      const req = db.transaction(STORE).objectStore(STORE).get(KEY)
      req.onsuccess = () => resolve((req.result as Blob) ?? null)
      req.onerror = () => reject(req.error)
    })
  } catch {
    return null
  }
}

export async function clearCustomAudio(): Promise<void> {
  try {
    const db = await open()
    await new Promise<void>((resolve, reject) => {
      const tx = db.transaction(STORE, 'readwrite')
      tx.objectStore(STORE).delete(KEY)
      tx.oncomplete = () => resolve()
      tx.onerror = () => reject(tx.error)
    })
  } catch {
    /* لا شيء */
  }
}

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
  sound: 'tone' | 'custom'
  volume: number
}

/** يشغّل الأذان. يرجع false إن مُنع التشغيل (سياسة المتصفح) أو لا يوجد ملف مخصص. */
export async function playAdhan(opts: PlayOptions): Promise<boolean> {
  stopAdhan()
  try {
    if (opts.sound === 'custom') {
      const blob = await loadCustomAudio()
      if (blob) {
        const url = URL.createObjectURL(blob)
        const audio = new Audio(url)
        audio.volume = Math.max(0, Math.min(1, opts.volume))
        current = { stop: () => { audio.pause(); URL.revokeObjectURL(url) } }
        audio.addEventListener('ended', () => { URL.revokeObjectURL(url); current = null })
        await audio.play()
        return true
      }
      // لا ملف: ننتقل للنغمة
    }
    const tone = playTone(opts.volume)
    current = { stop: tone.stop }
    void tone.done.then(() => { current = null })
    return true
  } catch {
    current = null
    return false
  }
}

'use client'

import { useEffect, useRef, useState, useSyncExternalStore } from 'react'
import { toast } from 'sonner'
import { ArrowRight, BellRing, Check, Clock, MapPin, Minus, Play, Plus, Square, Trash2, Upload, Volume2 } from 'lucide-react'
import { useRouter } from 'next/navigation'
import { usePrayerStore } from '@/lib/prayer/store'
import {
  METHODS, PRAYERS, REGION_NAME, formatRegionTime, prayerTimesFor, regionDate, upcomingPrayers,
  type MethodKey, type PrayerCalcSettings, type PrayerKey,
} from '@/lib/prayer/times'
import { MAX_AUDIO_BYTES, clearCustomAudio, playAdhan, saveCustomAudio, stopAdhan, unlockAudio } from '@/lib/prayer/audio'
import { isNativeApp } from '@/lib/prayer/native'
import { cn } from '@/lib/utils'
import { useTick } from '@/hooks/useTick'

function Toggle({ on, onChange, label }: { on: boolean; onChange: (v: boolean) => void; label: string }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={on}
      aria-label={label}
      onClick={() => onChange(!on)}
      className={cn('relative h-7 w-12 shrink-0 rounded-full transition-colors', on ? 'bg-primary-600' : 'bg-slate-300 dark:bg-slate-600')}
    >
      <span className={cn('absolute top-0.5 h-6 w-6 rounded-full bg-white shadow transition-all', on ? 'right-0.5' : 'right-[1.375rem]')} />
    </button>
  )
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="rounded-2xl border border-slate-200/70 bg-white p-4 dark:border-slate-700 dark:bg-slate-800" style={{ boxShadow: 'var(--shadow-card)' }}>
      <h2 className="mb-3 text-sm font-extrabold text-slate-800 dark:text-slate-100">{title}</h2>
      {children}
    </section>
  )
}

function countdown(ms: number): string {
  const total = Math.max(0, Math.floor(ms / 1000))
  const h = Math.floor(total / 3600)
  const m = Math.floor((total % 3600) / 60)
  const s = total % 60
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${pad(h)}:${pad(m)}:${pad(s)}`
}

// الصفحة تعتمد على الوقت الحالي: لا نرسمها على الخادم لتفادي اختلاف التوقيت عند التهيئة
export default function PrayerPage() {
  const mounted = useSyncExternalStore(() => () => {}, () => true, () => false)
  return mounted ? <PrayerContent /> : <div className="min-h-screen" />
}

function PrayerContent() {
  const router = useRouter()
  const st = usePrayerStore()
  const fileRef = useRef<HTMLInputElement>(null)
  const now = useTick(1000) // ثانية واحدة، وتتوقف تلقائياً عند إخفاء الشاشة
  const [playing, setPlaying] = useState(false)
  const [notifPerm, setNotifPerm] = useState<NotificationPermission | 'unsupported'>(() => (typeof Notification === 'undefined' ? 'unsupported' : Notification.permission))

  const calc: PrayerCalcSettings = { method: st.method, hanafiAsr: st.hanafiAsr, adjust: st.adjust }
  const times = prayerTimesFor(regionDate(now), calc)
  const next = upcomingPrayers(now, calc, 2)[0]

  const dateText = new Intl.DateTimeFormat('ar-SY-u-nu-latn', { timeZone: 'Asia/Damascus', weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(now)
  const clockText = new Intl.DateTimeFormat('ar-EG-u-nu-latn', { timeZone: 'Asia/Damascus', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }).format(now)

  const enable = (v: boolean) => {
    unlockAudio()
    st.set({ enabled: v })
    if (v && typeof Notification !== 'undefined' && Notification.permission === 'default' && !isNativeApp()) {
      void Notification.requestPermission().then((p) => setNotifPerm(p))
    }
  }

  const test = async () => {
    unlockAudio()
    const ok = await playAdhan({ sound: st.sound, volume: st.volume })
    setPlaying(ok)
    if (!ok) toast.error('منع المتصفح تشغيل الصوت. جرّب الضغط مرة أخرى.')
  }
  const stop = () => { stopAdhan(); setPlaying(false) }

  const pickFile = async (file: File | undefined) => {
    if (!file) return
    if (!file.type.startsWith('audio/')) { toast.error('اختر ملفاً صوتياً (mp3 أو m4a أو ogg)'); return }
    if (file.size > MAX_AUDIO_BYTES) { toast.error('الملف كبير؛ الحد الأقصى 8 ميغابايت'); return }
    await saveCustomAudio(file)
    st.set({ customName: file.name, sound: 'custom' })
    toast.success('تم حفظ ملف الأذان على هذا الجهاز')
  }

  const removeFile = async () => {
    await clearCustomAudio()
    st.set({ customName: null, sound: 'tone' })
  }

  const setAdjust = (k: PrayerKey, delta: number) =>
    st.set({ adjust: { ...st.adjust, [k]: Math.max(-30, Math.min(30, st.adjust[k] + delta)) } })

  return (
    <div className="mx-auto min-h-screen max-w-2xl space-y-4 px-3 pb-16 pt-3" dir="rtl">
      {/* شريط علوي: صفحة عامة تعمل داخل التطبيق وخارجه */}
      <div className="flex items-center gap-3">
        <button
          onClick={() => (window.history.length > 1 ? router.back() : router.push('/'))}
          className="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 active:scale-95 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
          aria-label="رجوع"
        >
          <ArrowRight className="h-5 w-5" />
        </button>
        <div>
          <h1 className="text-lg font-extrabold text-slate-800 dark:text-slate-100">الأذان وأوقات الصلاة</h1>
          <p className="text-[11px] text-slate-500">يعمل حتى بدون تسجيل دخول</p>
        </div>
      </div>

      {/* المنطقة والوقت الحالي */}
      <div className="overflow-hidden rounded-3xl bg-gradient-to-br from-primary-700 to-primary-900 p-5 text-white shadow-lg">
        <div className="flex items-center gap-1.5 text-xs font-bold text-primary-100">
          <MapPin className="h-3.5 w-3.5" /> {REGION_NAME}
        </div>
        <div className="mt-2 text-4xl font-black tabular-nums tracking-tight">{clockText}</div>
        <div className="mt-1 text-xs font-semibold text-primary-100">{dateText}</div>
        {next && (
          <div className="mt-4 flex items-center justify-between rounded-2xl bg-white/10 px-4 py-3">
            <div>
              <p className="text-[11px] font-semibold text-primary-100">الصلاة القادمة</p>
              <p className="text-lg font-extrabold">{next.label} <span className="text-sm font-bold text-primary-100">· {formatRegionTime(next.at)}</span></p>
            </div>
            <div className="text-left">
              <p className="text-[11px] font-semibold text-primary-100">بعد</p>
              <p className="text-xl font-black tabular-nums">{countdown(next.at.getTime() - now.getTime())}</p>
            </div>
          </div>
        )}
      </div>

      {/* التفعيل */}
      <Section title="الأذان">
        <div className="flex items-center justify-between gap-3">
          <div>
            <p className="text-sm font-bold text-slate-800 dark:text-slate-100">تشغيل الأذان في وقت الصلاة</p>
            <p className="mt-0.5 text-[11px] text-slate-500">
              {isNativeApp()
                ? 'على تطبيق الأندرويد يصلك إشعار الأذان حتى لو كان التطبيق مغلقاً.'
                : 'يعمل أثناء فتح التطبيق. لتنبيه والتطبيق مغلق استعمل تطبيق الأندرويد.'}
            </p>
          </div>
          <Toggle on={st.enabled} onChange={enable} label="تشغيل الأذان" />
        </div>
        {st.enabled && notifPerm === 'denied' && (
          <p className="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-[11px] font-semibold text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
            إشعارات المتصفح مغلقة. سيعمل الصوت داخل التطبيق فقط. فعّل الإشعارات من إعدادات المتصفح إن أردت.
          </p>
        )}
      </Section>

      {/* أوقات اليوم */}
      <Section title="أوقات اليوم">
        <ul className="divide-y divide-slate-100 dark:divide-slate-700">
          <li className="flex items-center justify-between py-2.5 text-sm text-slate-400">
            <span className="flex items-center gap-2"><Clock className="h-4 w-4" /> الشروق</span>
            <span className="tabular-nums">{formatRegionTime(times.sunrise)}</span>
          </li>
          {PRAYERS.map((p) => {
            const isNext = next?.key === p.key && next.at.getTime() === times[p.key].getTime()
            return (
              <li key={p.key} className={cn('flex items-center justify-between gap-3 py-3', isNext && 'rounded-xl bg-primary-50 px-3 dark:bg-primary-900/20')}>
                <div className="flex items-center gap-2.5">
                  <span className={cn('text-[15px] font-extrabold', isNext ? 'text-primary-800 dark:text-primary-200' : 'text-slate-800 dark:text-slate-100')}>{p.label}</span>
                  {isNext && <span className="rounded-full bg-primary-600 px-2 py-0.5 text-[10px] font-bold text-white">القادمة</span>}
                </div>
                <div className="flex items-center gap-3">
                  <span className="text-base font-black tabular-nums text-slate-800 dark:text-slate-100">{formatRegionTime(times[p.key])}</span>
                  <Toggle on={st.prayers[p.key]} onChange={(v) => st.set({ prayers: { ...st.prayers, [p.key]: v } })} label={`أذان ${p.label}`} />
                </div>
              </li>
            )
          })}
        </ul>
      </Section>

      {/* الصوت */}
      <Section title="الصوت">
        <div className="grid grid-cols-2 gap-2">
          {([['tone', 'نغمة التنبيه'], ['custom', 'ملف الأذان']] as const).map(([k, label]) => (
            <button
              key={k}
              onClick={() => st.set({ sound: k })}
              disabled={k === 'custom' && !st.customName}
              className={cn(
                'rounded-xl border py-3 text-sm font-bold transition-colors disabled:opacity-40',
                st.sound === k ? 'border-primary-600 bg-primary-600 text-white' : 'border-slate-200 bg-white text-slate-600 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200',
              )}
            >
              {label}
            </button>
          ))}
        </div>

        <div className="mt-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-700/50">
          {st.customName ? (
            <div className="flex items-center justify-between gap-2">
              <span className="min-w-0 truncate text-xs font-semibold text-slate-600 dark:text-slate-200">{st.customName}</span>
              <button onClick={removeFile} className="flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-rose-600">
                <Trash2 className="h-3.5 w-3.5" /> حذف
              </button>
            </div>
          ) : (
            <p className="text-[11px] text-slate-500">لا يوجد ملف أذان. ارفع تسجيل الأذان الذي تفضّله (mp3) ويُحفظ على هذا الجهاز فقط.</p>
          )}
          <input ref={fileRef} type="file" accept="audio/*" className="hidden" onChange={(e) => { void pickFile(e.target.files?.[0]); e.target.value = '' }} />
          <button
            onClick={() => fileRef.current?.click()}
            className="mt-2 flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 py-2.5 text-xs font-bold text-slate-600 dark:border-slate-500 dark:text-slate-200"
          >
            <Upload className="h-4 w-4" /> {st.customName ? 'استبدال الملف' : 'رفع ملف الأذان'}
          </button>
        </div>

        <div className="mt-4">
          <div className="mb-1 flex items-center justify-between text-xs font-semibold text-slate-500">
            <span className="flex items-center gap-1.5"><Volume2 className="h-4 w-4" /> مستوى الصوت</span>
            <span className="tabular-nums">{Math.round(st.volume * 100)}%</span>
          </div>
          <input
            type="range" min={0} max={100} value={Math.round(st.volume * 100)}
            onChange={(e) => st.set({ volume: Number(e.target.value) / 100 })}
            className="w-full accent-primary-600"
          />
        </div>

        <div className="mt-3 flex gap-2">
          <button onClick={test} className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary-600 py-3 text-sm font-bold text-white active:scale-[0.98]">
            <Play className="h-4 w-4 fill-current" /> تجربة الصوت
          </button>
          {playing && (
            <button onClick={stop} className="flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-600 dark:border-slate-600 dark:text-slate-200">
              <Square className="h-4 w-4 fill-current" /> إيقاف
            </button>
          )}
        </div>
      </Section>

      {/* طريقة الحساب والتعديل */}
      <Section title="ضبط الأوقات">
        <label className="mb-1 block text-xs font-semibold text-slate-500">طريقة الحساب</label>
        <select
          value={st.method}
          onChange={(e) => st.set({ method: e.target.value as MethodKey })}
          className="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-800 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
        >
          {METHODS.map((m) => <option key={m.key} value={m.key}>{m.label} — {m.hint}</option>)}
        </select>

        <div className="mt-3 flex items-center justify-between gap-3">
          <div>
            <p className="text-sm font-bold text-slate-800 dark:text-slate-100">العصر على المذهب الحنفي</p>
            <p className="text-[11px] text-slate-500">الأصل الشافعي (ظل المثل)</p>
          </div>
          <Toggle on={st.hanafiAsr} onChange={(v) => st.set({ hanafiAsr: v })} label="العصر الحنفي" />
        </div>

        <p className="mb-2 mt-4 text-xs font-semibold text-slate-500">تعديل بالدقائق (لمطابقة تقويم المسجد في منطقتك)</p>
        <ul className="space-y-2">
          {PRAYERS.map((p) => (
            <li key={p.key} className="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-700/50">
              <span className="text-sm font-bold text-slate-700 dark:text-slate-100">{p.label}</span>
              <div className="flex items-center gap-2">
                <button onClick={() => setAdjust(p.key, -1)} className="flex h-8 w-8 items-center justify-center rounded-lg bg-white shadow-sm active:scale-95 dark:bg-slate-600" aria-label="تأخير"><Minus className="h-4 w-4" /></button>
                <span className="w-12 text-center text-sm font-black tabular-nums text-slate-800 dark:text-slate-100">{st.adjust[p.key] > 0 ? '+' : ''}{st.adjust[p.key]}</span>
                <button onClick={() => setAdjust(p.key, 1)} className="flex h-8 w-8 items-center justify-center rounded-lg bg-white shadow-sm active:scale-95 dark:bg-slate-600" aria-label="تقديم"><Plus className="h-4 w-4" /></button>
              </div>
            </li>
          ))}
        </ul>
        <p className="mt-3 flex items-start gap-1.5 text-[11px] text-slate-400">
          <Check className="mt-0.5 h-3.5 w-3.5 shrink-0" />
          المنطقة ثابتة على {REGION_NAME} وبتوقيت سوريا (UTC+3). الأوقات حسابية؛ قارنها بتقويم مسجدك وعدّل بالدقائق إن لزم.
        </p>
      </Section>

      <p className="flex items-center justify-center gap-1.5 text-[11px] text-slate-400">
        <BellRing className="h-3.5 w-3.5" /> أذان بتوقيت {REGION_NAME}
      </p>
    </div>
  )
}

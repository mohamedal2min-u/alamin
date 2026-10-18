'use client'

import { useState, useSyncExternalStore } from 'react'
import { useRouter } from 'next/navigation'
import { ArrowRight, BellRing, Check, Clock, Lock, MapPin, Play, Square, Volume2 } from 'lucide-react'
import { MIN_VOLUME, usePrayerStore } from '@/lib/prayer/store'
import { FIXED_CALC, PRAYERS, REGION_NAME, formatRegionTime, prayerTimesFor, regionDate, upcomingPrayers } from '@/lib/prayer/times'
import { VOICES, type AdhanLength } from '@/lib/prayer/voices'
import { playAdhan, stopAdhan, unlockAudio } from '@/lib/prayer/audio'
import { isNativeApp } from '@/lib/prayer/native'
import { useTick } from '@/hooks/useTick'
import { cn } from '@/lib/utils'

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
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${pad(Math.floor(total / 3600))}:${pad(Math.floor((total % 3600) / 60))}:${pad(total % 60)}`
}

// الصفحة تعتمد على الوقت الحالي: لا نرسمها على الخادم لتفادي اختلاف التوقيت عند التهيئة
export default function PrayerPage() {
  const mounted = useSyncExternalStore(() => () => {}, () => true, () => false)
  return mounted ? <PrayerContent /> : <div className="min-h-screen" />
}

function PrayerContent() {
  const router = useRouter()
  const voice = usePrayerStore((s) => s.voice)
  const length = usePrayerStore((s) => s.length)
  const volume = usePrayerStore((s) => s.volume)
  const setPrefs = usePrayerStore((s) => s.set)
  const now = useTick(1000) // ثانية واحدة، وتتوقف تلقائياً عند إخفاء الشاشة
  const [playing, setPlaying] = useState(false)

  const times = prayerTimesFor(regionDate(now), FIXED_CALC)
  const next = upcomingPrayers(now, FIXED_CALC, 2)[0]
  const dateText = new Intl.DateTimeFormat('ar-SY-u-nu-latn', { timeZone: 'Asia/Damascus', weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(now)
  const clockText = new Intl.DateTimeFormat('ar-EG-u-nu-latn', { timeZone: 'Asia/Damascus', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }).format(now)

  const preview = async (id: string, len: AdhanLength = length) => {
    unlockAudio()
    setPrefs({ voice: id, length: len })
    const ok = await playAdhan({ voice: id, length: len, volume })
    setPlaying(ok)
  }
  const stop = () => { stopAdhan(); setPlaying(false) }

  return (
    <div className="mx-auto min-h-screen max-w-2xl space-y-4 px-3 pb-16 pt-3" dir="rtl">
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
          <p className="text-[11px] text-slate-500">يعمل دائماً حتى بدون تسجيل دخول</p>
        </div>
      </div>

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
                <div className="flex items-center gap-2">
                  <span className="text-base font-black tabular-nums text-slate-800 dark:text-slate-100">{formatRegionTime(times[p.key])}</span>
                  <BellRing className="h-4 w-4 text-primary-600" aria-label="الأذان مفعّل" />
                </div>
              </li>
            )
          })}
        </ul>
        <p className="mt-3 flex items-start gap-1.5 rounded-xl bg-slate-50 p-3 text-[11px] font-semibold text-slate-500 dark:bg-slate-700/50 dark:text-slate-300">
          <Lock className="mt-0.5 h-3.5 w-3.5 shrink-0" />
          الأذان إلزامي لكل المستخدمين وتوقيته ثابت على {REGION_NAME} (توقيت سوريا UTC+3)، ولا يمكن إيقافه أو تغيير وقته.
          {isNativeApp() ? ' يصلك إشعار الأذان بصوته حتى لو كان التطبيق مغلقاً.' : ''}
        </p>
      </Section>

      <Section title="صوت المؤذن">
        {/* كامل أو قصير (المقطع الأول فقط) */}
        <div className="mb-3 grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-700/50">
          {([['long', 'أذان كامل'], ['short', 'أذان قصير']] as const).map(([key, label]) => (
            <button
              key={key}
              type="button"
              onClick={() => preview(voice, key)}
              className={cn('rounded-lg py-2 text-sm font-bold transition-all', length === key ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-600 dark:text-slate-100' : 'text-slate-500')}
            >
              {label}
            </button>
          ))}
        </div>
        <ul className="space-y-2">
          {VOICES.map((v) => (
            <li key={v.id}>
              <button
                onClick={() => preview(v.id)}
                className={cn(
                  'flex w-full items-center gap-3 rounded-xl border px-3.5 py-3 text-right transition-colors',
                  voice === v.id ? 'border-primary-600 bg-primary-50 dark:bg-primary-900/20' : 'border-slate-200 bg-white dark:border-slate-600 dark:bg-slate-700/50',
                )}
              >
                <span className={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-full', voice === v.id ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-500 dark:bg-slate-600')}>
                  {voice === v.id ? <Check className="h-4 w-4" /> : <Play className="h-4 w-4 fill-current" />}
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block text-sm font-extrabold text-slate-800 dark:text-slate-100">{v.name}</span>
                  <span className="block text-[11px] text-slate-500">{v.note}</span>
                </span>
                <span className="text-[11px] font-bold text-primary-700 dark:text-primary-300">{voice === v.id ? 'المختار' : 'تجربة'}</span>
              </button>
            </li>
          ))}
        </ul>

        <div className="mt-4">
          <div className="mb-1 flex items-center justify-between text-xs font-semibold text-slate-500">
            <span className="flex items-center gap-1.5"><Volume2 className="h-4 w-4" /> مستوى الصوت</span>
            <span className="tabular-nums">{Math.round(volume * 100)}%</span>
          </div>
          <input
            type="range" min={Math.round(MIN_VOLUME * 100)} max={100} value={Math.round(volume * 100)}
            onChange={(e) => setPrefs({ volume: Number(e.target.value) / 100 })}
            className="w-full accent-primary-600"
          />
          <p className="mt-1 text-[11px] text-slate-400">أقل مستوى {Math.round(MIN_VOLUME * 100)}% — لا يمكن كتم الأذان.</p>
        </div>

        {playing && (
          <button onClick={stop} className="mt-3 flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 py-3 text-sm font-bold text-slate-600 dark:border-slate-600 dark:text-slate-200">
            <Square className="h-4 w-4 fill-current" /> إيقاف التجربة
          </button>
        )}
      </Section>

      {isNativeApp() && (
        <Section title="إن لم يصلك الأذان والتطبيق مغلق">
          <ol className="list-decimal space-y-1.5 pr-5 text-[12px] leading-relaxed text-slate-600 dark:text-slate-300">
            <li>الإعدادات ← التطبيقات ← YMD ← الإشعارات: فعّلها وفعّل قناة «الأذان».</li>
            <li>الإعدادات ← التطبيقات ← الوصول الخاص ← المنبّهات والتذكيرات: اسمح لـ YMD.</li>
            <li>سامسونغ: الإعدادات ← البطارية ← حدود الاستخدام في الخلفية ← «التطبيقات التي لا تنام أبداً» ← أضف YMD.</li>
            <li>تأكد أن الهاتف ليس على وضع «عدم الإزعاج» ولا الصامت.</li>
          </ol>
        </Section>
      )}

      <p className="text-center text-[10px] leading-relaxed text-slate-400">
        تسجيلات الأذان مرخّصة للاستخدام الحر (CC0 / ملكية عامة) — مصدرها Wikimedia Commons.
      </p>
    </div>
  )
}

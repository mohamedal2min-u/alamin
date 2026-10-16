'use client'

import { useEffect, useState } from 'react'
import { Capacitor } from '@capacitor/core'
import { Download, RefreshCw } from 'lucide-react'

const LATEST_RELEASE_API = 'https://api.github.com/repos/mohamedal2min-u/alamin/releases/latest'

type Latest = { code: number; url: string }

/** آخر نسخة APK منشورة (الوسم apk-<رقم النسخة>)، أو null إن تعذّر الجلب. */
async function fetchLatest(): Promise<Latest | null> {
  try {
    const res = await fetch(LATEST_RELEASE_API, { headers: { Accept: 'application/vnd.github+json' } })
    if (!res.ok) return null
    const json = await res.json()
    const code = parseInt(String(json.tag_name ?? '').replace(/^apk-/, ''), 10)
    const asset = (json.assets ?? []).find((a: { name: string }) => a.name.endsWith('.apk'))
    if (!code || !asset) return null
    return { code, url: asset.browser_download_url as string }
  } catch {
    return null
  }
}

/** رقم نسخة التطبيق المثبّت؛ null إن كان APK قديماً لا يحتوي إضافة App (فهو يحتاج تحديثاً حتماً). */
async function installedBuild(): Promise<number | null> {
  if (!Capacitor.isPluginAvailable('App')) return null
  try {
    const { App } = await import('@capacitor/app')
    const info = await App.getInfo()
    return parseInt(info.build, 10) || 0
  } catch {
    return null
  }
}

/**
 * زر في صفحة الدخول:
 *  - داخل تطبيق الأندرويد: «تحديث التطبيق» يظهر فقط إن وُجدت نسخة أحدث.
 *  - في أي متصفح آخر (عدا آيفون): «تحميل تطبيق أندرويد» لتحميل ملف APK.
 */
export function AppInstallButton() {
  const [state, setState] = useState<{ mode: 'install' | 'update'; latest: Latest } | null>(null)

  useEffect(() => {
    let alive = true
    void (async () => {
      const native = Capacitor.isNativePlatform()
      if (!native && /iPhone|iPad|iPod/i.test(navigator.userAgent)) return // ملف APK لا يُثبَّت على آيفون
      const latest = await fetchLatest()
      if (!latest || !alive) return
      if (!native) { setState({ mode: 'install', latest }); return }
      const current = await installedBuild()
      if (alive && (current === null || current < latest.code)) setState({ mode: 'update', latest })
    })()
    return () => { alive = false }
  }, [])

  if (!state) return null

  const open = async () => {
    const url = state.latest.url
    if (Capacitor.isNativePlatform() && Capacitor.isPluginAvailable('Browser')) {
      try {
        const { Browser } = await import('@capacitor/browser')
        await Browser.open({ url })
        return
      } catch { /* نكمل بالطريقة العادية */ }
    }
    window.location.href = url
  }

  const isUpdate = state.mode === 'update'
  return (
    <div className="mt-3">
      <button
        type="button"
        onClick={() => void open()}
        className="flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-400/30 bg-emerald-500/10 py-2.5 text-xs font-bold text-emerald-300 transition-colors hover:bg-emerald-500/20"
      >
        {isUpdate ? <RefreshCw size={14} /> : <Download size={14} />}
        {isUpdate ? 'تحديث التطبيق' : 'تحميل وتثبيت تطبيق أندرويد'}
      </button>
      <p className="mt-1 text-center text-[10px] text-slate-500">
        بعد التحميل افتح الملف واسمح بالتثبيت من هذا المصدر عند الطلب.
      </p>
    </div>
  )
}

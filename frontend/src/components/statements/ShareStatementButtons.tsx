'use client'

import { useState } from 'react'
import { Capacitor } from '@capacitor/core'
import { FileText, Loader2, MessageCircle } from 'lucide-react'
import { toast } from 'sonner'
import { statementsApi, statementUrl, type StatementKind } from '@/lib/api/statements'
import { cn } from '@/lib/utils'

interface Props {
  kind: StatementKind
  id: number
  /** نص زر فتح الملف، مثل «مشاركة كشف الفوج» */
  label: string
  /** رسالة واتساب القصيرة قبل الرابط */
  message: string
  className?: string
  size?: 'sm' | 'xs'
}

/** على تطبيق الأندرويد window.print لا يعمل: نفتح PDF من الخادم عبر Browser، وعلى الويب في تبويب جديد. */
async function openExternal(url: string, win: Window | null) {
  if (Capacitor.isNativePlatform() && Capacitor.isPluginAvailable('Browser')) {
    try {
      const { Browser } = await import('@capacitor/browser')
      await Browser.open({ url })
      return
    } catch { /* نكمل بالطريقة العادية */ }
  }
  if (win) win.location.href = url
  else window.location.href = url
}

export function ShareStatementButtons({ kind, id, label, message, className, size = 'sm' }: Props) {
  const [busy, setBusy] = useState<'open' | 'whatsapp' | null>(null)

  const go = async (mode: 'open' | 'whatsapp') => {
    if (busy) return
    // على الويب نفتح التبويب فوراً داخل النقرة (قبل انتظار الخادم) كي لا يحجبه المتصفح
    const native = Capacitor.isNativePlatform()
    const win = native ? null : window.open('', '_blank')
    setBusy(mode)
    try {
      const url = statementUrl(await statementsApi.link(kind, id))
      const target = mode === 'open' ? url : `https://wa.me/?text=${encodeURIComponent(`${message}\n${url}`)}`
      await openExternal(target, win)
    } catch {
      win?.close()
      toast.error('تعذّر تجهيز الكشف، حاول مجدداً')
    } finally {
      setBusy(null)
    }
  }

  const base = cn(
    'inline-flex items-center gap-1.5 rounded-lg border font-semibold transition-colors disabled:opacity-60',
    size === 'xs' ? 'px-2 py-1 text-[11px]' : 'px-3 py-1.5 text-xs',
  )

  return (
    <div className={cn('flex flex-wrap items-center gap-2', className)}>
      <button
        type="button"
        onClick={() => go('open')}
        disabled={!!busy}
        className={cn(base, 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700')}
      >
        {busy === 'open' ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <FileText className="h-3.5 w-3.5" />}
        {label}
      </button>
      <button
        type="button"
        onClick={() => go('whatsapp')}
        disabled={!!busy}
        aria-label="مشاركة عبر واتساب"
        className={cn(base, 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 dark:hover:bg-emerald-900/50')}
      >
        {busy === 'whatsapp' ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <MessageCircle className="h-3.5 w-3.5" />}
        واتساب
      </button>
    </div>
  )
}

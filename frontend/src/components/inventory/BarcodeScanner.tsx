'use client'

import { useEffect, useRef, useState } from 'react'
import { Loader2, ScanLine } from 'lucide-react'
import { Dialog } from '@/components/ui/Dialog'

interface Props {
  isOpen: boolean
  onClose: () => void
  onDetected: (code: string) => void
}

// ماسح باركود بالكاميرا الخلفية (يعمل في المتصفح وتطبيق الأندرويد)
export function BarcodeScanner({ isOpen, onClose, onDetected }: Props) {
  const videoRef = useRef<HTMLVideoElement>(null)
  const [error, setError] = useState<string | null>(null)
  const [starting, setStarting] = useState(true)

  useEffect(() => {
    if (!isOpen) return
    let stopped = false
    let controls: { stop: () => void } | undefined

    const start = async () => {
      try {
        if (!navigator.mediaDevices?.getUserMedia) {
          setError('الكاميرا غير متاحة في هذا المتصفح')
          return
        }
        const { BrowserMultiFormatReader } = await import('@zxing/browser')
        const reader = new BrowserMultiFormatReader()
        // انتظر ظهور عنصر الفيديو داخل النافذة
        await new Promise((r) => setTimeout(r, 50))
        if (stopped || !videoRef.current) return
        controls = await reader.decodeFromConstraints(
          { video: { facingMode: { ideal: 'environment' } } },
          videoRef.current,
          (result) => {
            if (result && !stopped) {
              stopped = true
              controls?.stop()
              onDetected(result.getText())
            }
          },
        )
        if (stopped) controls.stop()
        setStarting(false)
      } catch {
        setError('تعذر فتح الكاميرا. اسمح للتطبيق باستخدام الكاميرا ثم أعد المحاولة.')
      }
    }
    setError(null)
    setStarting(true)
    start()

    return () => {
      stopped = true
      controls?.stop()
    }
  }, [isOpen, onDetected])

  return (
    <Dialog isOpen={isOpen} onClose={onClose} title="مسح الباركود">
      <div className="space-y-3 p-5" dir="rtl">
        <div className="relative overflow-hidden rounded-2xl bg-black">
          <video ref={videoRef} className="aspect-[4/3] w-full object-cover" muted playsInline />
          {!error && (
            <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
              <div className="h-1/3 w-4/5 rounded-xl border-2 border-primary-400/80" />
            </div>
          )}
          {starting && !error && (
            <div className="absolute inset-0 flex items-center justify-center bg-black/50">
              <Loader2 className="h-8 w-8 animate-spin text-white" />
            </div>
          )}
        </div>
        {error ? (
          <p className="text-sm font-semibold text-red-600">{error}</p>
        ) : (
          <p className="flex items-center justify-center gap-1.5 text-sm font-semibold text-slate-500">
            <ScanLine className="h-4 w-4" /> وجّه الكاميرا نحو الباركود
          </p>
        )}
      </div>
    </Dialog>
  )
}

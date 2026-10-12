import { useEffect, useState } from 'react'

/**
 * ساعة بسيطة تتحدّث كل `ms` لكنها تتوقف تماماً عندما تكون الصفحة مخفية (شاشة مطفأة/تطبيق بالخلفية)
 * وتتحدّث فوراً عند العودة — فلا تستهلك بطارية ولا تسخّن الهاتف.
 */
export function useTick(ms: number): Date {
  const [now, setNow] = useState(() => new Date())

  useEffect(() => {
    let timer: ReturnType<typeof setInterval> | undefined

    const start = () => {
      if (timer) return
      setNow(new Date())
      timer = setInterval(() => setNow(new Date()), ms)
    }
    const stop = () => {
      if (timer) clearInterval(timer)
      timer = undefined
    }
    const onVisibility = () => (document.visibilityState === 'visible' ? start() : stop())

    if (document.visibilityState === 'visible') start()
    document.addEventListener('visibilitychange', onVisibility)
    return () => {
      stop()
      document.removeEventListener('visibilitychange', onVisibility)
    }
  }, [ms])

  return now
}

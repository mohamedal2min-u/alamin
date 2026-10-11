'use client'

import { Moon, Sun } from 'lucide-react'
import { useThemeStore } from '@/stores/theme.store'
import { cn } from '@/lib/utils'

/** زر ليلي/نهاري — يُحفظ الاختيار في المتصفح لكل مستخدم. */
export function ThemeToggle({ className }: { className?: string }) {
  const theme = useThemeStore((s) => s.theme)
  const toggle = useThemeStore((s) => s.toggle)
  const isDark = theme === 'dark'

  return (
    <button
      type="button"
      onClick={toggle}
      aria-label={isDark ? 'التحويل إلى الوضع النهاري' : 'التحويل إلى الوضع الليلي'}
      title={isDark ? 'الوضع النهاري' : 'الوضع الليلي'}
      className={cn(
        'flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-slate-50 text-slate-500 transition-all active:scale-95 dark:bg-slate-700 dark:text-amber-300',
        className
      )}
    >
      {isDark ? <Sun className="h-3.5 w-3.5" /> : <Moon className="h-3.5 w-3.5" />}
    </button>
  )
}

'use client'

import { useEffect } from 'react'
import { useThemeStore } from '@/stores/theme.store'

/**
 * يطبّق class="dark" على <html> بناءً على تفضيل المستخدم المحفوظ.
 * يُضاف مرة واحدة في RootLayout.
 */
export function ThemeApplier() {
  const theme = useThemeStore((s) => s.theme)

  useEffect(() => {
    document.documentElement.classList.toggle('dark', theme === 'dark')
  }, [theme])

  return null
}

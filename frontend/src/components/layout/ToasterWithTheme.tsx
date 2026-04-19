'use client'

import { Toaster } from 'sonner'
import { useThemeStore } from '@/stores/theme.store'

export function ToasterWithTheme() {
  const theme = useThemeStore((s) => s.theme)
  return <Toaster richColors position="top-center" theme={theme} />
}


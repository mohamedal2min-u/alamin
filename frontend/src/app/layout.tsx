import type { Metadata } from 'next'
import './globals.css'

export const metadata: Metadata = {
  title: 'الياسين — إدارة المداجن',
  description: 'نظام تشغيل ومحاسبة وتحليل لمداجن دجاج اللحم',
  manifest: '/manifest.json',
}

import type { Viewport } from 'next'

export const viewport: Viewport = {
  width: 'device-width',
  initialScale: 1,
  maximumScale: 1,
  userScalable: false,
}

import { QueryProvider } from '@/components/providers/QueryProvider'
import { ThemeApplier } from '@/components/layout/ThemeApplier'
import { ToasterWithTheme } from '@/components/layout/ToasterWithTheme'

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="ar" dir="rtl">
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        <link
          href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap"
          rel="stylesheet"
          precedence="default"
        />
      </head>
      <body
        suppressHydrationWarning
        className="min-h-screen bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-50 antialiased"
      >
        <QueryProvider>
          <ThemeApplier />
          {children}
          <ToasterWithTheme />
        </QueryProvider>
      </body>
    </html>
  )
}

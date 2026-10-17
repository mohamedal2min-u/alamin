import type { Metadata } from 'next'
import './globals.css'

export const metadata: Metadata = {
  title: 'YMD — Yasin Modern Digital',
  description: 'نظام الياسين المتقدم لإدارة ومتابعة المداجن والدواجن',
  manifest: '/manifest.json',
  icons: {
    icon: '/icon.png',
    apple: '/apple-icon.png',
  }
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
import { PushNotificationHandler } from '@/components/layout/PushNotificationHandler'
import { ArabicDigitsSanitizer } from '@/components/ArabicDigitsSanitizer'
import { PrayerScheduler } from '@/components/layout/PrayerScheduler'
import { EditNotificationListener } from '@/components/layout/EditNotificationListener'

const THEME_SCRIPT = `try{if(JSON.parse(localStorage.getItem('alamin-theme')||'{}').state?.theme==='dark')document.documentElement.classList.add('dark')}catch(e){}`

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="ar" dir="rtl" suppressHydrationWarning>
      <head>
        {/* الوضع الليلي قبل أول رسم للصفحة، بدل وميض الوضع الفاتح حتى يعمل ThemeApplier */}
        <script dangerouslySetInnerHTML={{ __html: THEME_SCRIPT }} />
      </head>
      <body
        suppressHydrationWarning
        className="min-h-screen bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-50 antialiased overscroll-y-none"
      >
        <QueryProvider>
          <ThemeApplier />
          <ArabicDigitsSanitizer />
          <PushNotificationHandler />
          {/* الأذان يعمل في كل الصفحات حتى بعد تسجيل الخروج وفي صفحة الدخول */}
          <PrayerScheduler />
          <EditNotificationListener />
          {children}
          <ToasterWithTheme />
        </QueryProvider>
      </body>
    </html>
  )
}



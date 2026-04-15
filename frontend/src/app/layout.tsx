import type { Metadata } from 'next'
import './globals.css'

export const metadata: Metadata = {
  title: 'الياسين — إدارة المداجن',
  description: 'نظام تشغيل ومحاسبة وتحليل لمداجن دجاج اللحم',
}

import { QueryProvider } from '@/components/providers/QueryProvider'
import { Toaster } from 'sonner'

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
        className="min-h-screen bg-slate-50 text-slate-900 antialiased"
      >
        <QueryProvider>
          {children}
        </QueryProvider>
        <Toaster richColors position="top-center" />
      </body>
    </html>
  )
}

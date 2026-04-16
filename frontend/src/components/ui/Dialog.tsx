'use client'

import { useEffect, useState } from 'react'
import { createPortal } from 'react-dom'
import { X } from 'lucide-react'
import { cn } from '@/lib/utils'

interface DialogProps {
  isOpen: boolean
  onClose: () => void
  title?: string
  children: React.ReactNode
  className?: string
}

export function Dialog({ isOpen, onClose, title, children, className }: DialogProps) {
  const [mounted, setMounted] = useState(false)

  useEffect(() => {
    setMounted(true)
    if (isOpen) {
      document.body.style.overflow = 'hidden'
    } else {
      document.body.style.overflow = 'unset'
    }
    return () => {
      document.body.style.overflow = 'unset'
    }
  }, [isOpen])

  if (!mounted || !isOpen) return null

  return createPortal(
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 translate-z-0">
      {/* Backdrop */}
      <div 
        className="absolute inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity duration-200 animate-in fade-in"
        onClick={onClose}
      />
      
      {/* Dialog Content */}
      <div
        className={cn(
          "relative w-full max-w-lg overflow-hidden rounded-2xl bg-white dark:bg-slate-800 transition-all duration-200 animate-in fade-in zoom-in-95",
          className
        )}
        style={{ boxShadow: 'var(--shadow-float)' }}
      >
        {/* Header */}
        {(title || typeof onClose === 'function') && (
          <div className="relative flex items-center justify-between border-b border-slate-100 dark:border-slate-700 px-6 py-4">
            {title && (
              <h2 className="text-lg font-bold tracking-tight text-slate-900 dark:text-slate-100">
                {title}
              </h2>
            )}
            <button
              onClick={onClose}
              className="group rounded-xl bg-slate-50 dark:bg-slate-700 p-2 text-slate-400 transition-all duration-200 hover:bg-slate-100 dark:hover:bg-slate-600 hover:text-slate-900 dark:hover:text-slate-100 active:scale-95"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        )}

        {/* Body */}
        <div className="max-h-[80vh] overflow-y-auto px-6 pb-6 thin-scrollbar">
          {children}
        </div>
      </div>
    </div>,
    document.body
  )
}

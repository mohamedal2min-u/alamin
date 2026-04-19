'use client'

import { cn } from '@/lib/utils'
import { type ButtonHTMLAttributes, forwardRef } from 'react'
import { Spinner } from './Spinner'

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'outline' | 'ghost' | 'danger'
  size?: 'sm' | 'md' | 'lg'
  loading?: boolean
  asChild?: boolean
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant = 'primary', size = 'md', loading, disabled, asChild, children, ...props }, ref) => {
    const base =
      'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-600/10 focus-visible:border-primary-600 disabled:pointer-events-none disabled:opacity-50'

    const variants = {
      primary: 'bg-primary-600 text-white hover:bg-primary-700 active:bg-primary-800 shadow-sm border border-primary-700',
      outline: 'border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700',
      ghost:   'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700',
      danger:  'bg-red-600 text-white hover:bg-red-700',
    }

    const sizes = {
      sm: 'h-8 px-3 text-sm',
      md: 'h-10 px-4 text-sm',
      lg: 'h-12 px-6 text-base',
    }

    const allClasses = cn(base, variants[variant], sizes[size], className)

    // When asChild, clone the child and apply button classes
    if (asChild && children && typeof children === 'object' && 'type' in (children as object)) {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const child = children as React.ReactElement<any>
      const childProps = child.props as Record<string, unknown>
      return (
        <child.type
          {...childProps}
          className={cn(allClasses, childProps.className as string | undefined)}
          ref={ref}
        />
      )
    }

    return (
      <button
        ref={ref}
        disabled={disabled || loading}
        className={allClasses}
        {...props}
      >
        {loading && <Spinner size="sm" />}
        {children}
      </button>
    )
  }
)
Button.displayName = 'Button'


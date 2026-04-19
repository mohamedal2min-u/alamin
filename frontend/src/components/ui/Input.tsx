import { cn } from '@/lib/utils'
import { forwardRef, type InputHTMLAttributes } from 'react'

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string
  error?: string
  startIcon?: React.ReactNode
  endIcon?: React.ReactNode
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ className, label, error, id, required, startIcon, endIcon, ...props }, ref) => {
    return (
      <div className="flex flex-col gap-1.5">
        {label && (
          <label htmlFor={id} className="text-sm font-medium text-slate-700 dark:text-slate-200">
            {label}
            {required && <span className="ms-1 text-red-500">*</span>}
          </label>
        )}
        <div className="relative group/input">
          {startIcon && (
            <div className="absolute top-1/2 -translate-y-1/2 flex items-center justify-center text-slate-400 group-focus-within/input:text-emerald-500 transition-colors pointer-events-none start-3">
              {startIcon}
            </div>
          )}
          <input
            ref={ref}
            id={id}
            className={cn(
              'w-full rounded-xl border bg-white dark:bg-[#1a1c21] py-2.5 text-sm ring-1 ring-white/[0.03]',
              'text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500',
              'transition-all duration-200',
              'focus:outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500',
              'disabled:bg-slate-50 dark:disabled:bg-slate-900 disabled:text-slate-500 disabled:cursor-not-allowed disabled:opacity-50',
              startIcon ? 'ps-10' : 'ps-3.5',
              endIcon ? 'pe-10' : 'pe-3.5',
              error
                ? 'border-red-400 focus:border-red-400 focus:ring-red-400/20'
                : 'border-slate-300 dark:border-white/[0.08] dark:hover:border-white/[0.15]',
              className
            )}
            {...props}
          />
          {endIcon && (
            <div className="absolute top-1/2 -translate-y-1/2 flex items-center justify-center text-slate-400 group-focus-within/input:text-emerald-500 transition-colors end-3">
              {endIcon}
            </div>
          )}
        </div>
        {error && <p className="text-xs text-red-600 dark:text-red-400">{error}</p>}
      </div>
    )
  }
)
Input.displayName = 'Input'


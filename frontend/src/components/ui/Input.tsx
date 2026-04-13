import { cn } from '@/lib/utils'
import { forwardRef, type InputHTMLAttributes } from 'react'

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string
  error?: string
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ className, label, error, id, required, ...props }, ref) => {
    return (
      <div className="flex flex-col gap-1.5">
        {label && (
          <label htmlFor={id} className="text-sm font-medium text-slate-700">
            {label}
            {required && <span className="ms-1 text-red-500">*</span>}
          </label>
        )}
        <input
          ref={ref}
          id={id}
          className={cn(
            'w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400',
            'transition-colors duration-200',
            'focus:outline-none focus:ring-4 focus:ring-primary-600/10 focus:border-primary-600',
            'disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed disabled:opacity-50',
            error ? 'border-red-400 focus:border-red-400 focus:ring-red-400/20' : 'border-slate-400',
            className
          )}
          {...props}
        />
        {error && <p className="text-xs text-red-600">{error}</p>}
      </div>
    )
  }
)
Input.displayName = 'Input'

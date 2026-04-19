import { cn } from '@/lib/utils'
import { forwardRef, type InputHTMLAttributes, useState, useEffect, useRef } from 'react'

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string
  error?: string
  startIcon?: React.ReactNode
  endIcon?: React.ReactNode
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ className, label, error, id, required, startIcon, endIcon, onFocus, onBlur, onChange, ...props }, ref) => {
    const [isFocused, setIsFocused] = useState(false)
    const [hasValue, setHasValue] = useState(false)
    const localRef = useRef<HTMLInputElement>(null)

    // Sync initial value and changes
    useEffect(() => {
      const input = (localRef.current || (ref as any)?.current) as HTMLInputElement
      if (input) {
        setHasValue(input.value.length > 0)
      }
    }, [props.value, ref])

    const handleFocus = (e: React.FocusEvent<HTMLInputElement>) => {
      setIsFocused(true)
      onFocus?.(e)
    }

    const handleBlur = (e: React.FocusEvent<HTMLInputElement>) => {
      setIsFocused(false)
      setHasValue(e.target.value.length > 0)
      onBlur?.(e)
    }

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
      setHasValue(e.target.value.length > 0)
      onChange?.(e)
    }

    const getIconColor = () => {
      if (isFocused) return 'text-amber-500'
      if (hasValue) return 'text-emerald-500'
      return 'text-slate-400'
    }

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
            <div className={cn(
              "absolute top-1/2 -translate-y-1/2 flex items-center justify-center transition-colors duration-300 pointer-events-none start-3",
              getIconColor()
            )}>
              {startIcon}
            </div>
          )}
          <input
            {...props}
            ref={(node) => {
              // Handle both forwarded ref and local ref
              (localRef as any).current = node
              if (typeof ref === 'function') ref(node)
              else if (ref) (ref as any).current = node
            }}
            id={id}
            onFocus={handleFocus}
            onBlur={handleBlur}
            onChange={handleChange}
            className={cn(
              'w-full rounded-xl border bg-white dark:bg-[#1a1c21] py-2.5 text-sm ring-1 ring-white/[0.03]',
              'text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500',
              'transition-all duration-200',
              'focus:outline-none focus:ring-4 focus:ring-amber-500/10 focus:border-amber-500',
              'disabled:bg-slate-50 dark:disabled:bg-slate-900 disabled:text-slate-500 disabled:cursor-not-allowed disabled:opacity-50',
              startIcon ? 'ps-10' : 'ps-3.5',
              endIcon ? 'pe-10' : 'pe-3.5',
              error
                ? 'border-red-400 focus:border-red-400 focus:ring-red-400/20'
                : 'border-slate-300 dark:border-white/[0.08] dark:hover:border-white/[0.15]',
              className
            )}
          />
          {endIcon && (
            <div className={cn(
              "absolute top-1/2 -translate-y-1/2 flex items-center justify-center transition-colors duration-300 end-3",
              getIconColor()
            )}>
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


import { cn } from '@/lib/utils'

export function ChickenLogo({ className }: { className?: string }) {
  // Remove brightness-0 and invert if they were passed, so the logo retains its original colors
  const cleanClassName = className?.replace(/brightness-0/g, '')?.replace(/invert/g, '')

  return (
    <img 
      src="/ymd-logo.png" 
      alt="YMD"
      className={cn("h-8 w-8 object-contain", cleanClassName)}
    />
  )
}



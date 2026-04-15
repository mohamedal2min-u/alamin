import { cn } from '@/lib/utils'

export function ChickenLogo({ className }: { className?: string }) {
  return (
    <img 
      src="/logo.png" 
      alt="الياسين"
      className={cn("h-8 w-8 object-contain", className)}
    />
  )
}

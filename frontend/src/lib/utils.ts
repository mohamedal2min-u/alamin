import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function formatDate(dateStr: string): string {
  try {
    return new Intl.DateTimeFormat('ar-SA', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      numberingSystem: 'latn'
    }).format(new Date(dateStr))
  } catch {
    return dateStr
  }
}

export function formatNumber(n: number): string {
  return new Intl.NumberFormat('en-US').format(n)
}

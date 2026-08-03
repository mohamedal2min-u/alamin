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

/**
 * Money formatter used everywhere a $ amount is displayed, so the same figure never renders
 * with a different number of decimals/format depending on which screen shows it.
 */
export function formatCurrency(n: number): string {
  return '$' + new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)
}

/**
 * Today's date as YYYY-MM-DD in the user's LOCAL timezone. Never use
 * `new Date().toISOString().split('T')[0]` for this — toISOString() converts to UTC, so during
 * the first hours after local midnight (any UTC+ timezone) it silently returns yesterday's date.
 */
export function getTodayLocalISO(): string {
  const now = new Date()
  return now.getFullYear() + '-' +
         String(now.getMonth() + 1).padStart(2, '0') + '-' +
         String(now.getDate()).padStart(2, '0')
}

export function toEnglishDigits(str: string): string {
  if (!str) return str;
  const arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
  const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
  
  let res = str;
  for (let i = 0; i < 10; i++) {
    res = res.replaceAll(arabicNumbers[i], i.toString());
    res = res.replaceAll(persianNumbers[i], i.toString());
  }
  return res;
}

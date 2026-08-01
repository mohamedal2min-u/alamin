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

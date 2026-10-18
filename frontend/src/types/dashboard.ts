export interface InventoryItem {
  id: number
  name: string
  input_unit: string
  content_unit: string
  unit_value: number
  type_code: string
  total_quantity: number
}

import type { EntryCorrectionMeta } from '@/lib/api/dailyEntries'

export interface TodayMortalityEntry extends EntryCorrectionMeta {
  quantity: number
  reason: string | null
  worker_name?: string
  time?: string
}

export interface TodayFeedEntry extends EntryCorrectionMeta {
  item_name: string | null
  quantity: number
  unit_label: string | null
  worker_name?: string
  time?: string
}

export interface TodayWaterEntry extends EntryCorrectionMeta {
  quantity: number
  unit_label: string
  worker_name?: string
  time?: string
}

export interface TodayExpenseEntry {
  type: string
  total_amount: number
  worker_name?: string
  time?: string
}

export interface TodaySummary {
  date: string
  mortalities: {
    entries: TodayMortalityEntry[]
    total: number
  }
  feed: {
    entries: TodayFeedEntry[]
    total: number
  }
  medicines: {
    entries: TodayFeedEntry[]
    total: number
  }
  water: {
    entries: TodayWaterEntry[]
    total: number
  }
  expenses: {
    entries: TodayExpenseEntry[]
    total: number
  }
  temperatures: {
    entries: {
      time_of_day: 'morning' | 'afternoon' | 'evening'
      temperature: number
      worker_name?: string
      time?: string
    }[]
  }
}

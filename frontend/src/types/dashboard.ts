export interface InventoryItem {
  id: number
  name: string
  input_unit: string
  content_unit: string
  unit_value: number
  type_code: string
}

export interface TodayMortalityEntry {
  quantity: number
  reason: string | null
  worker_name?: string
  time?: string
}

export interface TodayFeedEntry {
  item_name: string | null
  quantity: number
  unit_label: string | null
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
    entries: {
      quantity: number
      unit_label: string
      worker_name?: string
      time?: string
    }[]
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

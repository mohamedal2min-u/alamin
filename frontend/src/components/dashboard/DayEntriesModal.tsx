'use client'

import { X, Skull, Wheat, Syringe, Receipt, FileText } from 'lucide-react'
import { cn } from '@/lib/utils'
import type {
  TodayMortalityEntry,
  TodayFeedEntry,
  TodayExpenseEntry,
} from '@/types/dashboard'

export type DayEntryType = 'mortality' | 'feed' | 'medicine' | 'expense'

interface Props {
  type: DayEntryType | null
  date: string
  summary: {
    mortalities: { entries: TodayMortalityEntry[]; total: number }
    feed:        { entries: TodayFeedEntry[];      total: number }
    medicines:   { entries: TodayFeedEntry[];      total: number }
    expenses:    { entries: TodayExpenseEntry[];   total: number }
  }
  onClose: () => void
}

const CONFIG: Record<DayEntryType, {
  label: string
  icon: React.ElementType
  color: string
  iconBg: string
  totalLabel: string
  unit: string
}> = {
  mortality: {
    label: 'نفوق اليوم',
    icon: Skull,
    color: 'text-rose-600',
    iconBg: 'bg-rose-100',
    totalLabel: 'إجمالي النفوق',
    unit: 'طائر',
  },
  feed: {
    label: 'استهلاك العلف',
    icon: Wheat,
    color: 'text-amber-600',
    iconBg: 'bg-amber-100',
    totalLabel: 'إجمالي العلف',
    unit: 'كجم',
  },
  medicine: {
    label: 'أدوية / إضافات',
    icon: Syringe,
    color: 'text-indigo-600',
    iconBg: 'bg-indigo-100',
    totalLabel: 'إجمالي الأدوية',
    unit: 'وحدة',
  },
  expense: {
    label: 'المصروفات اليومية',
    icon: Receipt,
    color: 'text-emerald-600',
    iconBg: 'bg-emerald-100',
    totalLabel: 'إجمالي المصاريف',
    unit: '$',
  },
}

const EXPENSE_TYPE_LABELS: Record<string, string> = {
  feed:         'علف',
  medicine:     'دواء',
  chick:        'كتاكيت',
  labor:        'عمالة',
  utilities:    'مرافق',
  bedding:      'نشارة',
  farm_wash:    'غسيل مدجنة',
  disinfectant: 'معقم',
  other:        'أخرى',
}

export function DayEntriesModal({ type, date, summary, onClose }: Props) {
  if (!type) return null

  const cfg = CONFIG[type]
  const Icon = cfg.icon

  const entries =
    type === 'mortality' ? summary.mortalities.entries :
    type === 'feed'      ? summary.feed.entries :
    type === 'medicine'  ? summary.medicines.entries :
                           summary.expenses.entries

  const total =
    type === 'mortality' ? summary.mortalities.total :
    type === 'feed'      ? summary.feed.total :
    type === 'medicine'  ? summary.medicines.total :
                           summary.expenses.total

  return (
    <div
      className="fixed inset-0 z-[70] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/50 backdrop-blur-sm"
      onClick={(e) => { if (e.target === e.currentTarget) onClose() }}
      dir="rtl"
    >
      <div className="w-full sm:max-w-md bg-white sm:rounded-2xl rounded-t-3xl shadow-2xl overflow-hidden max-h-[85vh] flex flex-col">

        {/* Handle (mobile) */}
        <div className="flex justify-center pt-3 pb-1 sm:hidden">
          <div className="h-1 w-10 rounded-full bg-slate-200" />
        </div>

        {/* Header */}
        <div className="flex items-center gap-3 px-5 py-4 border-b border-slate-100 shrink-0">
          <div className={cn('w-10 h-10 rounded-xl flex items-center justify-center shrink-0', cfg.iconBg)}>
            <Icon className={cn('w-5 h-5', cfg.color)} />
          </div>
          <div className="flex-1 min-w-0">
            <h3 className="text-base font-black text-slate-900">{cfg.label}</h3>
            <p className="text-[11px] text-slate-400 font-medium">{date}</p>
          </div>
          <button
            onClick={onClose}
            className="w-8 h-8 flex items-center justify-center rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-400 transition-colors"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Total */}
        <div className="px-5 py-3 bg-slate-50/60 border-b border-slate-100 shrink-0 flex items-center justify-between">
          <span className="text-xs font-bold text-slate-400">{cfg.totalLabel}</span>
          <span className={cn('text-xl font-black tabular-nums', cfg.color)}>
            {type === 'expense'
              ? `$${Number(total).toFixed(2)}`
              : `${total} ${cfg.unit}`}
          </span>
        </div>

        {/* Entries */}
        <div className="flex-1 overflow-y-auto">
          {entries.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-14 text-slate-300">
              <FileText className="w-10 h-10 mb-3 opacity-30" />
              <p className="text-sm font-bold text-slate-400">لا توجد إدخالات لهذا اليوم</p>
            </div>
          ) : (
            <div className="divide-y divide-slate-50">
              {(entries as any[]).map((entry, i) => (
                <div key={i} className="px-5 py-3.5 flex items-start justify-between gap-3">
                  <div className="flex-1 min-w-0 space-y-0.5">
                    {/* Mortality */}
                    {type === 'mortality' && (
                      <>
                        <p className="text-sm font-bold text-slate-800">
                          {(entry as TodayMortalityEntry).quantity} طائر
                        </p>
                        {(entry as TodayMortalityEntry).reason && (
                          <p className="text-xs text-slate-400 font-medium">
                            السبب: {(entry as TodayMortalityEntry).reason}
                          </p>
                        )}
                      </>
                    )}

                    {/* Feed / Medicine */}
                    {(type === 'feed' || type === 'medicine') && (
                      <>
                        <p className="text-sm font-bold text-slate-800">
                          {(entry as TodayFeedEntry).item_name || 'غير محدد'}
                        </p>
                        <p className="text-xs text-slate-400 font-medium">
                          {(entry as TodayFeedEntry).quantity}{' '}
                          {(entry as TodayFeedEntry).unit_label || 'وحدة'}
                        </p>
                      </>
                    )}

                    {/* Expense */}
                    {type === 'expense' && (
                      <>
                        <p className="text-sm font-bold text-slate-800">
                          {EXPENSE_TYPE_LABELS[(entry as TodayExpenseEntry).type] || (entry as TodayExpenseEntry).type}
                        </p>
                      </>
                    )}

                    {/* Worker + Time */}
                    {(entry.worker_name || entry.time) && (
                      <p className="text-[11px] text-slate-300 font-medium">
                        {entry.worker_name && `بواسطة ${entry.worker_name}`}
                        {entry.worker_name && entry.time && ' · '}
                        {entry.time}
                      </p>
                    )}
                  </div>

                  {/* Value badge */}
                  <div className={cn(
                    'shrink-0 px-2.5 py-1 rounded-lg text-xs font-black tabular-nums',
                    cfg.iconBg, cfg.color
                  )}>
                    {type === 'expense'
                      ? `$${Number((entry as TodayExpenseEntry).total_amount).toFixed(2)}`
                      : type === 'mortality'
                      ? `${(entry as TodayMortalityEntry).quantity}`
                      : `${(entry as TodayFeedEntry).quantity}`}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Safe area */}
        <div style={{ height: 'env(safe-area-inset-bottom, 8px)' }} />
      </div>
    </div>
  )
}

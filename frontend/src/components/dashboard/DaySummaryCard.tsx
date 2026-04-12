'use client'

import { useState } from 'react'
import { ClipboardList, ChevronDown, ChevronUp, Skull, Wheat, Syringe, Calendar, ThermometerSun, User, Clock, Droplets } from 'lucide-react'
import type { TodaySummary } from '@/types/dashboard'
import { formatNumber, cn } from '@/lib/utils'

interface Props {
  summary: TodaySummary
  loading?: boolean
}

export function DaySummaryCard({ summary, loading }: Props) {
  const [expanded, setExpanded] = useState(false)

  if (loading) {
    return (
      <div className="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        <div className="mb-4 h-10 w-32 animate-pulse rounded-xl bg-slate-100" />
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-24 animate-pulse rounded-2xl bg-slate-50" />
          ))}
        </div>
      </div>
    )
  }

  const mortalityTotal = summary.mortalities.total
  const feedTotal      = summary.feed.total
  const medicineTotal  = summary.medicines.total
  const waterTotal     = summary?.water?.total ?? 0

  const feedExpr  = buildExpr(summary.feed.entries.map((e) => `${formatNumber(e.quantity)}${e.unit_label ? ' ' + e.unit_label : ''}`))
  const medExpr   = buildExpr(summary.medicines.entries.map((e) => `${formatNumber(e.quantity)}${e.unit_label ? ' ' + e.unit_label : ''}`))
  const waterExpr = buildExpr(summary.water?.entries?.map((e) => `${formatNumber(e.quantity)} ${e.unit_label}`) ?? [])

  return (
    <div className="group overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-xl shadow-slate-100 transition-all duration-300 hover:shadow-2xl hover:shadow-slate-200/50">
      {/* ── Header ── */}
      <div className="flex items-center justify-between border-b border-slate-50 bg-slate-50/30 px-6 py-4">
        <div className="flex items-center gap-3">
          <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 text-sky-600 shadow-sm">
            <ClipboardList className="h-5 w-5" />
          </div>
          <h2 className="text-sm font-black text-slate-900 tracking-tight">إحصائيات اليوم</h2>
        </div>
        <div className="flex items-center gap-2 rounded-full bg-white px-3 py-1 text-[10px] font-black text-slate-400 shadow-sm">
          <Calendar className="h-3 w-3" />
          <span className="tabular-nums">{summary.date}</span>
        </div>
      </div>

      {/* ── KPIs Grid ── */}
      <div className="grid grid-cols-2 gap-px bg-slate-100 sm:grid-cols-4">
        <SummaryKpi 
          label="النفوق" 
          value={formatNumber(mortalityTotal)} 
          unit="طير"
          icon={<Skull className="w-4 h-4" />}
          color="text-rose-600" 
          bgColor="bg-rose-50/30"
        />
        <SummaryKpi 
          label="استهلاك العلف" 
          value={feedTotal > 0 ? formatNumber(feedTotal) : '—'} 
          unit={feedTotal > 0 ? (summary.feed.entries[0]?.unit_label || 'كيس') : ''}
          icon={<Wheat className="w-4 h-4" />}
          color="text-amber-600" 
          bgColor="bg-amber-50/30"
        />
        <SummaryKpi 
          label="الأدوية" 
          value={medicineTotal > 0 ? formatNumber(medicineTotal) : '—'} 
          unit={medicineTotal > 0 ? 'وحدة' : ''}
          icon={<Syringe className="w-4 h-4" />}
          color="text-sky-600" 
          bgColor="bg-sky-50/30"
        />
        <SummaryKpi 
          label="الماء" 
          value={waterTotal > 0 ? formatNumber(waterTotal) : '—'} 
          unit={waterTotal > 0 ? (summary.water?.entries?.[0]?.unit_label || 'لتر') : ''}
          icon={<Droplets className="w-4 h-4" />}
          color="text-blue-500" 
          bgColor="bg-blue-50/30"
        />
      </div>

      {/* ── Toggle Actions ── */}
      <button
        onClick={() => setExpanded((p) => !p)}
        className="flex w-full items-center justify-center gap-2 py-4 text-[11px] font-black uppercase tracking-wider text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-900 active:bg-slate-100"
      >
        {expanded ? (
          <><ChevronUp className="h-4 w-4" /> إغفاء التفاصيل</>
        ) : (
          <><ChevronDown className="h-4 w-4" /> عرض تفاصيل سجل العمليات</>
        )}
      </button>

      {/* ── Expanded Content ── */}
      {expanded && (
        <div className="animate-in slide-in-from-top-2 border-t border-slate-50 bg-slate-50/20 duration-300">
          <div className="space-y-4 p-6">
            {summary.mortalities.entries.length > 0 && (
              <DetailSection title="سجل النفوق اليومي" colorClass="text-rose-600" icon={<Skull className="h-3 w-3"/>}>
                {summary.mortalities.entries.map((e, i) => (
                  <DetailRow 
                    key={i} 
                    left={formatNumber(e.quantity) + ' طير'} 
                    right={e.reason ?? 'بدون سبب محدد'} 
                    workerName={e.worker_name}
                    time={e.time}
                  />
                ))}
              </DetailSection>
            )}

            {summary.feed.entries.length > 0 && (
              <DetailSection title={`سجل استهلاك العلف (المجموع: ${feedExpr})`} colorClass="text-amber-700" icon={<Wheat className="h-3 w-3"/>}>
                {summary.feed.entries.map((e, i) => (
                  <DetailRow 
                    key={i} 
                    left={e.item_name ?? 'صنف غير معروف'} 
                    right={`${formatNumber(e.quantity)} ${e.unit_label ?? ''}`} 
                    workerName={e.worker_name}
                    time={e.time}
                  />
                ))}
              </DetailSection>
            )}

            {summary.medicines.entries.length > 0 && (
              <DetailSection title={`سجل الأدوية والتحصينات (المجموع: ${medExpr})`} colorClass="text-sky-600" icon={<Syringe className="h-3 w-3"/>}>
                {summary.medicines.entries.map((e, i) => (
                  <DetailRow 
                    key={i} 
                    left={e.item_name ?? 'صنف غير معروف'} 
                    right={`${formatNumber(e.quantity)} ${e.unit_label ?? ''}`} 
                    workerName={e.worker_name}
                    time={e.time}
                  />
                ))}
              </DetailSection>
            )}

            {summary.water?.entries?.length > 0 && (
              <DetailSection title={`سجل استهلاك الماء (المجموع: ${waterExpr})`} colorClass="text-blue-600" icon={<Droplets className="h-3 w-3"/>}>
                {summary.water.entries.map((e, i) => (
                  <DetailRow 
                    key={i} 
                    left="استهلاك ماء" 
                    right={`${formatNumber(e.quantity)} ${e.unit_label}`} 
                    workerName={e.worker_name}
                    time={e.time}
                  />
                ))}
              </DetailSection>
            )}

            {summary.temperatures?.entries.length > 0 && (
              <DetailSection title="سجل درجات الحرارة اليومي" colorClass="text-amber-500" icon={<ThermometerSun className="h-3 w-3"/>}>
                <div className="grid grid-cols-1 gap-2">
                  {summary.temperatures.entries.map((e, i) => (
                    <DetailRow 
                      key={i} 
                      left={e.time_of_day === 'morning' ? 'الصباح' : e.time_of_day === 'afternoon' ? 'الظهيرة' : 'المساء'} 
                      right={`${formatNumber(e.temperature)}°C`} 
                      workerName={e.worker_name}
                      time={e.time}
                    />
                  ))}
                </div>
              </DetailSection>
            )}

            {!loading && 
             summary.mortalities.entries.length === 0 &&
             summary.feed.entries.length === 0 &&
             summary.medicines.entries.length === 0 &&
             (!summary.water || summary.water.entries.length === 0) && (
              <div className="flex flex-col items-center justify-center py-10 opacity-30">
                <ClipboardList className="h-10 w-10 text-slate-300" />
                <p className="mt-2 text-xs font-black text-slate-400">لا توجد عمليات مسجلة لهذا اليوم</p>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  )
}

// ── Shared Sub-Components ──

function buildExpr(parts: string[]): string {
  if (parts.length === 0) return '—'
  return parts.join(' + ')
}

function SummaryKpi({ label, value, unit, color, icon, bgColor }: {
  label: string; value: string; unit?: string; color: string; icon: React.ReactNode; bgColor: string
}) {
  return (
    <div className={cn("flex flex-col items-center justify-center border-none p-6 text-center transition-all duration-300 hover:z-10 bg-white hover:shadow-inner", bgColor)}>
      <div className="mb-2 flex items-center gap-1.5 opacity-60">
        <span className={color}>{icon}</span>
        <span className="text-[10px] font-black uppercase tracking-wider text-slate-400">{label}</span>
      </div>
      <div className="flex items-baseline gap-1">
        <span className={cn("text-2xl font-black tabular-nums leading-none", color)}>{value}</span>
        {unit && <span className="text-[10px] font-bold text-slate-400">{unit}</span>}
      </div>
    </div>
  )
}

function DetailSection({ title, colorClass, icon, children }: {
  title: string; colorClass: string; icon: React.ReactNode; children: React.ReactNode
}) {
  return (
    <div className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
      <div className={cn("mb-3 flex items-center gap-2 font-black text-[11px]", colorClass)}>
        {icon}
        <span>{title}</span>
      </div>
      <div className="space-y-2">{children}</div>
    </div>
  )
}

function DetailRow({ left, right, workerName, time }: { left: string; right: string; workerName?: string; time?: string }) {
  return (
    <div className="flex flex-col gap-1 rounded-xl bg-slate-50/50 px-4 py-2.5 transition-colors hover:bg-slate-50">
      <div className="flex items-center justify-between">
        <span className="text-xs font-bold text-slate-600">{left}</span>
        <span className="text-xs font-black tabular-nums text-slate-900 tracking-tight">{right}</span>
      </div>
      {(workerName || time) && (
        <div className="flex items-center gap-3 border-t border-slate-100/50 pt-1 opacity-60">
          {workerName && (
            <div className="flex items-center gap-1 text-[9px] font-bold text-slate-400">
              <User className="h-2.5 w-2.5" />
              <span>بواسطة: {workerName}</span>
            </div>
          )}
          {time && (
            <div className="flex items-center gap-1 text-[9px] font-bold text-slate-400">
              <Clock className="h-2.5 w-2.5" />
              <span className="tabular-nums">{time}</span>
            </div>
          )}
        </div>
      )}
    </div>
  )
}

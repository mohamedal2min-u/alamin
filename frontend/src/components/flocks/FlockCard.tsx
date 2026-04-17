// frontend/src/components/flocks/FlockCard.tsx
import Link from 'next/link'
import { Card, CardContent } from '@/components/ui/Card'
import { FlockStatusBadge } from './FlockStatusBadge'
import { formatDate, formatNumber } from '@/lib/utils'
import { Bird, Calendar, ChevronLeft, TrendingUp, TrendingDown, Skull, Target } from 'lucide-react'
import type { Flock } from '@/types/flock'
import { cn } from '@/lib/utils'

export function FlockCard({ flock }: { flock: Flock }) {
  const isProfit = flock.net_profit >= 0

  return (
    <Link href={`/flocks/${flock.id}`} className="block group">
      <Card className="relative overflow-hidden border-slate-100 bg-white shadow-sm transition-all duration-300 hover:border-indigo-200 hover:shadow-md">
        
        {/* Subtle Status Bar (Top or Side) - Optional, using a colored edge for emphasis */}
        <div className={cn(
          "absolute top-0 bottom-0 start-0 w-1",
          isProfit ? "bg-emerald-100" : "bg-rose-100"
        )} />

        <CardContent className="p-0">
          <div className="flex flex-col md:flex-row md:items-stretch">
            
            {/* ── Section 1: Flock Identity ── */}
            <div className="flex-[1.2] p-5 border-b border-slate-50 md:border-b-0 md:border-e md:max-w-[280px]">
              <div className="flex items-center gap-3 mb-3">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-400 transition-colors group-hover:bg-indigo-50 group-hover:text-indigo-500">
                  <Bird className="h-4.5 w-4.5" />
                </div>
                <div className="min-w-0">
                  <h3 className="truncate font-bold text-slate-800 text-sm Arabic-font">{flock.name}</h3>
                  <div className="flex items-center gap-1.5 text-[10px] text-slate-400 mt-0.5 font-medium">
                    <Calendar className="h-3 w-3" />
                    <span>{formatDate(flock.start_date)}</span>
                  </div>
                </div>
              </div>
              
              <div className="flex items-center gap-2">
                <FlockStatusBadge status={flock.status} />
                {flock.current_age_days !== null && (
                  <span className="text-[10px] font-bold text-slate-400/80 Arabic-font bg-slate-50/80 px-2 py-0.5 rounded-md">
                    يوم {flock.current_age_days}
                  </span>
                )}
              </div>
            </div>

            {/* ── Section 2: Production Stats ── */}
            <div className="flex-[2.5] grid grid-cols-3 p-5 gap-2 items-center">
              <MinimalStatItem 
                label="العدد الكلي" 
                value={formatNumber(flock.initial_count)} 
                icon={<Target className="w-3.5 h-3.5" />}
              />
              <MinimalStatItem 
                label="المنفوق" 
                value={formatNumber(flock.total_mortality)} 
                icon={<Skull className="w-3.5 h-3.5" />}
                isAccent={true}
                accentColor="text-rose-500"
              />
              <MinimalStatItem 
                label="المتبقي" 
                value={formatNumber(flock.remaining_count)} 
                icon={<Bird className="w-3.5 h-3.5" />}
                isAccent={true}
                accentColor="text-emerald-600"
              />
            </div>

            {/* ── Section 3: Profit/Loss ── */}
            <div className="flex-1 p-5 md:p-6 bg-slate-50/40 flex flex-col items-center md:items-end justify-center">
              <div className="flex flex-col items-center md:items-end">
                <span className="text-[9px] font-bold text-slate-400 uppercase tracking-tight Arabic-font mb-1">صافي القيمة</span>
                <div className={cn(
                  "flex items-center gap-1.5 text-base font-bold tabular-nums Arabic-font",
                  isProfit ? "text-emerald-700" : "text-rose-600"
                )}>
                  {isProfit ? <TrendingUp className="w-4 h-4" /> : <TrendingDown className="w-4 h-4" />}
                  <span className="text-[10px] opacity-60">$</span>
                  {formatNumber(Math.abs(flock.net_profit))}
                </div>
                <p className="text-[9px] font-medium text-slate-400 mt-0.5 Arabic-font">
                  {isProfit ? 'تقدير أرباح' : 'تقدير خسارة'}
                </p>
              </div>
            </div>

            {/* Desktop Action Indicator */}
            <div className="hidden md:flex pr-4 items-center justify-center text-slate-300 group-hover:text-indigo-400 transition-colors">
              <ChevronLeft className="w-5 h-5" />
            </div>

          </div>
        </CardContent>
      </Card>
    </Link>
  )
}

function MinimalStatItem({ 
  label, value, icon, isAccent = false, accentColor = "" 
}: { 
  label: string, value: string, icon: React.ReactNode, isAccent?: boolean, accentColor?: string 
}) {
  return (
    <div className="flex flex-col items-center text-center px-1">
      <div className="flex items-center gap-1 mb-1.5 text-slate-400/80">
        <div className="opacity-70">{icon}</div>
        <span className="text-[9px] font-bold Arabic-font uppercase">{label}</span>
      </div>
      <span className={cn(
        "text-base font-bold tabular-nums Arabic-font", 
        isAccent ? accentColor : "text-slate-700"
      )}>
        {value}
      </span>
    </div>
  )
}

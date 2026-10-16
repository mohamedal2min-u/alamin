'use client'

import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { RefreshCw } from 'lucide-react'
import { flocksApi } from '@/lib/api/flocks'
import { useAuthStore } from '@/stores/auth.store'
import { useFarmStore } from '@/stores/farm.store'
import { cn } from '@/lib/utils'
import { AccountantPrices } from './AccountantPrices'
import { AccountantPartners } from './AccountantPartners'
import { ProfitCard } from './ProfitCard'
import type { Flock } from '@/types/flock'

type Section = 'prices' | 'partners'

/** لوحة المحاسب: قسمان (الأسعار / الشركاء) واختيار الفوج. الأفواج المغلقة للعرض فقط. */
export function AccountantBoard() {
  const qc = useQueryClient()
  const user = useAuthStore((s) => s.user)
  const farmId = useFarmStore((s) => s.currentFarm?.id)

  const [section, setSection] = useState<Section>('prices')
  const [flockId, setFlockId] = useState<string>('') // '' = كل الأفواج
  const [flockTouched, setFlockTouched] = useState(false)

  const { data: flocks = [], isFetching } = useQuery<Flock[]>({
    queryKey: ['flocks', farmId],
    queryFn: () => flocksApi.list().then((r) => r.data),
    enabled: !!farmId,
    staleTime: 5000,
  })

  // الافتراضي: الفوج النشط إن وُجد، وإلا كل الأفواج (دون effect: يُشتق عند العرض)
  const activeFlock = flocks.find((f) => f.status === 'active')
  const effectiveFlockId = flockTouched ? flockId : activeFlock ? String(activeFlock.id) : ''

  const selected = flocks.find((f) => String(f.id) === effectiveFlockId) ?? null

  return (
    <div className="space-y-4 pb-6 pt-3" dir="rtl">
      {/* رأس بسيط */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-extrabold text-slate-800 dark:text-slate-100">المحاسبة</h1>
          <p className="mt-0.5 text-xs text-slate-500">أهلاً {user?.name ?? ''}</p>
        </div>
        <button
          onClick={() => qc.invalidateQueries()}
          className="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 active:scale-95 dark:border-slate-700 dark:bg-slate-800"
          aria-label="تحديث"
        >
          <RefreshCw className={cn('h-4 w-4', isFetching && 'animate-spin')} />
        </button>
      </div>

      {/* القسمان */}
      <div className="grid grid-cols-2 gap-1 rounded-2xl bg-slate-100 p-1 dark:bg-slate-800">
        {([['prices', 'الأسعار'], ['partners', 'الشركاء']] as const).map(([key, label]) => (
          <button
            key={key}
            onClick={() => setSection(key)}
            className={cn(
              'rounded-xl py-2.5 text-sm font-bold transition-all',
              section === key ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-700 dark:text-slate-100' : 'text-slate-500'
            )}
          >
            {label}
          </button>
        ))}
      </div>

      {/* اختيار الفوج: أي فوج، والمغلق للعرض فقط */}
      <div>
        <label className="mb-1 block text-xs font-semibold text-slate-500">الفوج</label>
        <select
          value={effectiveFlockId}
          onChange={(e) => { setFlockTouched(true); setFlockId(e.target.value) }}
          className="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-800 outline-none focus:border-primary-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
        >
          <option value="">كل الأفواج</option>
          {flocks.map((f) => (
            <option key={f.id} value={f.id}>
              {f.name}{f.status === 'closed' ? ' — مغلق' : f.status === 'active' ? ' — نشط' : ''}
            </option>
          ))}
        </select>
        {selected && selected.status !== 'active' && (
          <p className="mt-1.5 rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
            هذا الفوج {selected.status === 'closed' ? 'مغلق' : 'غير نشط'} — للعرض فقط، لا يمكن التعديل عليه.
          </p>
        )}
      </div>

      {section === 'prices' ? (
        <AccountantPrices flockId={effectiveFlockId} />
      ) : (
        <div className="space-y-4">
          {/* الربح والخسارة ونصيب كل شريك — يظهر بعد تسعير كل السجلات */}
          <ProfitCard flockId={effectiveFlockId} />
          <AccountantPartners flock={selected} sharesLocked={!!activeFlock} />
        </div>
      )}
    </div>
  )
}

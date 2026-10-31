'use client'

// حسابات الموردين والتجار: الأرصدة وأعمار الديون، وكشف حساب لكل جهة

import { Suspense, useMemo, useState } from 'react'
import { useRouter, useSearchParams } from 'next/navigation'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowRight, Loader2, MessageCircle, Pencil, Phone, Plus, Search, Users } from 'lucide-react'
import { contactsApi, CONTACT_TYPE_LABELS } from '@/lib/api/contacts'
import type { ContactBalance, ContactPayload, ContactType, StatementEntry } from '@/lib/api/contacts'
import { PAYMENT_METHOD_LABELS } from '@/lib/api/accounting'
import { Dialog } from '@/components/ui/Dialog'
import { useFarmStore } from '@/stores/farm.store'
import { useIsReadOnly } from '@/lib/roles'
import { cn, formatCurrency } from '@/lib/utils'

type Filter = 'all' | 'supplier' | 'buyer'

function errorMessage(e: unknown, fallback: string): string {
  const r = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })?.response?.data
  return (r?.errors ? Object.values(r.errors)[0]?.[0] : null) ?? r?.message ?? fallback
}

function waLink(num: string): string {
  return `https://wa.me/${num.replace(/[^\d]/g, '')}`
}

function ContactsPageInner() {
  const params = useSearchParams()
  const router = useRouter()
  const selected = params.get('id')

  return selected
    ? <StatementView contactId={Number(selected)} onBack={() => router.push('/accounting/contacts')} />
    : <BalancesView onOpen={(id) => router.push(`/accounting/contacts?id=${id}`)} />
}

export default function ContactsPage() {
  return (
    <Suspense fallback={<div className="flex justify-center py-16"><Loader2 className="h-6 w-6 animate-spin text-slate-400" /></div>}>
      <ContactsPageInner />
    </Suspense>
  )
}

// ── القائمة ─────────────────────────────────────────────────────────────────

function BalancesView({ onOpen }: { onOpen: (id: number) => void }) {
  const farmId = useFarmStore((s) => s.currentFarm?.id)
  const readOnly = useIsReadOnly()
  const [filter, setFilter] = useState<Filter>('all')
  const [q, setQ] = useState('')
  const [editing, setEditing] = useState<ContactBalance | 'new' | null>(null)

  const balances = useQuery({
    queryKey: ['contacts', farmId, 'balances'],
    queryFn: () => contactsApi.balances(),
    enabled: !!farmId,
    staleTime: 30_000,
  })

  const rows = useMemo(() => {
    const list = balances.data?.data ?? []
    return list
      .filter((c) => filter === 'all' || c.type === filter || c.type === 'both')
      .filter((c) => !q.trim() || c.name.includes(q.trim()))
      // الأكبر رصيداً أولاً
      .sort((a, b) => (Math.abs(b.payable.balance) + Math.abs(b.receivable.balance)) - (Math.abs(a.payable.balance) + Math.abs(a.receivable.balance)))
  }, [balances.data, filter, q])

  const summary = balances.data?.summary

  return (
    <div className="space-y-4 pb-20 sm:pb-8" dir="rtl">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-slate-800 dark:text-slate-100">حسابات الموردين والتجار</h1>
          <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">ما علينا للموردين وما لنا على التجار</p>
        </div>
        {!readOnly && (
          <button
            type="button"
            onClick={() => setEditing('new')}
            className="inline-flex shrink-0 items-center gap-1 rounded-xl bg-primary-600 px-3 py-2 text-sm font-bold text-white active:scale-95"
          >
            <Plus className="h-4 w-4" /> إضافة
          </button>
        )}
      </div>

      {/* الملخص */}
      <div className="grid grid-cols-2 gap-2">
        <SummaryCard tone="red" title="علينا للموردين" amount={summary?.payable_balance} aging={summary?.payable_aging} loading={balances.isLoading} />
        <SummaryCard tone="emerald" title="لنا على التجار" amount={summary?.receivable_balance} aging={summary?.receivable_aging} loading={balances.isLoading} />
      </div>

      {/* الفلترة والبحث */}
      <div className="flex flex-wrap items-center gap-2">
        {(['all', 'supplier', 'buyer'] as Filter[]).map((f) => (
          <button
            key={f}
            type="button"
            onClick={() => setFilter(f)}
            className={cn(
              'rounded-full border px-3 py-1.5 text-xs font-bold',
              filter === f ? 'border-slate-800 bg-slate-800 text-white dark:border-slate-200 dark:bg-slate-200 dark:text-slate-900' : 'border-slate-200 bg-white text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300'
            )}
          >
            {f === 'all' ? 'الكل' : f === 'supplier' ? 'الموردون' : 'التجار'}
          </button>
        ))}
        <div className="relative min-w-0 flex-1 basis-40">
          <Search className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="بحث بالاسم"
            className="h-9 w-full rounded-full border border-slate-200 bg-white ps-9 pe-3 text-sm text-slate-800 outline-none focus:border-primary-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
          />
        </div>
      </div>

      {balances.isLoading ? (
        <div className="flex justify-center py-16"><Loader2 className="h-6 w-6 animate-spin text-slate-400" /></div>
      ) : balances.isError ? (
        <p className="py-10 text-center text-sm text-red-600">تعذر تحميل الأرصدة</p>
      ) : rows.length === 0 ? (
        <div className="flex flex-col items-center gap-2 py-16 text-slate-400">
          <Users className="h-12 w-12 opacity-40" />
          <p className="text-sm font-semibold">لا توجد جهات بعد</p>
          <p className="text-xs">أضف مورداً أو تاجراً، أو اختره عند إدخال مصروف أو بيع</p>
        </div>
      ) : (
        <ul className="space-y-2">
          {rows.map((c) => (
            <li key={c.id}>
              <ContactRow contact={c} onOpen={() => onOpen(c.id)} onEdit={readOnly ? undefined : () => setEditing(c)} />
            </li>
          ))}
        </ul>
      )}

      {editing && (
        <ContactFormDialog contact={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />
      )}
    </div>
  )
}

function SummaryCard({ tone, title, amount, aging, loading }: {
  tone: 'red' | 'emerald'; title: string; amount?: number; loading: boolean
  aging?: { d0_30: number; d31_60: number; d60_plus: number }
}) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-800">
      <p className="text-xs font-semibold text-slate-500 dark:text-slate-400">{title}</p>
      {loading ? (
        <div className="mt-2 h-6 animate-pulse rounded bg-slate-100 dark:bg-slate-700" />
      ) : (
        <>
          <p className={cn('mt-1 text-lg font-black tabular-nums', tone === 'red' ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400')}>
            {formatCurrency(amount ?? 0)}
          </p>
          {aging && <AgingChips aging={aging} />}
        </>
      )}
    </div>
  )
}

function AgingChips({ aging }: { aging: { d0_30: number; d31_60: number; d60_plus: number } }) {
  const chips = [
    { label: '0-30 يوم', v: aging.d0_30, cls: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' },
    { label: '31-60', v: aging.d31_60, cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' },
    { label: '+60', v: aging.d60_plus, cls: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' },
  ].filter((c) => c.v > 0)
  if (chips.length === 0) return null
  return (
    <div className="mt-1.5 flex flex-wrap gap-1">
      {chips.map((c) => (
        <span key={c.label} className={cn('rounded-full px-2 py-0.5 text-[10px] font-bold tabular-nums', c.cls)}>
          {c.label}: {formatCurrency(c.v)}
        </span>
      ))}
    </div>
  )
}

function ContactRow({ contact, onOpen, onEdit }: { contact: ContactBalance; onOpen: () => void; onEdit?: () => void }) {
  const showPayable = contact.type !== 'buyer' || contact.payable.total > 0
  const showReceivable = contact.type !== 'supplier' || contact.receivable.total > 0

  return (
    <div className={cn('rounded-2xl border bg-white p-3 dark:bg-slate-800', contact.is_active ? 'border-slate-200 dark:border-slate-700' : 'border-dashed border-slate-300 opacity-70 dark:border-slate-600')}>
      <div className="flex items-start justify-between gap-2">
        <button type="button" onClick={onOpen} className="min-w-0 flex-1 text-start">
          <p className="truncate text-[15px] font-bold text-slate-800 dark:text-slate-100">{contact.name}</p>
          <p className="text-xs text-slate-400">
            {CONTACT_TYPE_LABELS[contact.type]}
            {!contact.is_active && ' • معطّل'}
            {contact.phone && <> • <span dir="ltr">{contact.phone}</span></>}
          </p>
        </button>
        {onEdit && (
          <button type="button" onClick={onEdit} aria-label="تعديل" className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
            <Pencil className="h-4 w-4" />
          </button>
        )}
      </div>

      <button type="button" onClick={onOpen} className="mt-2 block w-full space-y-1.5 text-start">
        {showPayable && (
          <div>
            <div className="flex items-center justify-between text-sm">
              <span className="text-slate-500 dark:text-slate-400">علينا له</span>
              <span className={cn('font-black tabular-nums', contact.payable.balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-500')}>
                {formatCurrency(contact.payable.balance)}
              </span>
            </div>
            <AgingChips aging={contact.payable.aging} />
          </div>
        )}
        {showReceivable && (
          <div>
            <div className="flex items-center justify-between text-sm">
              <span className="text-slate-500 dark:text-slate-400">لنا عليه</span>
              <span className={cn('font-black tabular-nums', contact.receivable.balance > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-500')}>
                {formatCurrency(contact.receivable.balance)}
              </span>
            </div>
            <AgingChips aging={contact.receivable.aging} />
          </div>
        )}
      </button>
    </div>
  )
}

// ── نموذج الإضافة/التعديل ────────────────────────────────────────────────────

function ContactFormDialog({ contact, onClose }: { contact: ContactBalance | null; onClose: () => void }) {
  const qc = useQueryClient()
  const farmId = useFarmStore((s) => s.currentFarm?.id)
  const [form, setForm] = useState<ContactPayload>({
    name: contact?.name ?? '',
    type: contact?.type ?? 'supplier',
    phone: contact?.phone ?? '',
    whatsapp: contact?.whatsapp ?? '',
    notes: contact?.notes ?? '',
    is_active: contact?.is_active ?? true,
  })
  const [error, setError] = useState<string | null>(null)

  const done = () => {
    qc.invalidateQueries({ queryKey: ['contacts', farmId] })
    onClose()
  }

  const save = useMutation({
    mutationFn: () => {
      const payload = { ...form, phone: form.phone || null, whatsapp: form.whatsapp || null, notes: form.notes || null }
      return contact ? contactsApi.update(contact.id, payload) : contactsApi.create(payload)
    },
    onSuccess: done,
    onError: (e) => setError(errorMessage(e, 'تعذر الحفظ')),
  })

  const remove = useMutation({
    mutationFn: () => contactsApi.remove(contact!.id),
    onSuccess: done,
    onError: (e) => setError(errorMessage(e, 'تعذر الحذف')),
  })

  const inputCls = 'h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none focus:border-primary-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100'

  return (
    <Dialog isOpen onClose={onClose} title={contact ? 'تعديل الجهة' : 'إضافة مورد أو تاجر'}>
      <form
        className="space-y-3 pt-3"
        dir="rtl"
        onSubmit={(e) => { e.preventDefault(); setError(null); save.mutate() }}
      >
        <label className="block space-y-1">
          <span className="text-sm font-medium text-slate-700 dark:text-slate-200">الاسم *</span>
          <input required minLength={2} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className={inputCls} />
        </label>

        <div className="space-y-1">
          <span className="text-sm font-medium text-slate-700 dark:text-slate-200">النوع</span>
          <div className="grid grid-cols-3 gap-2">
            {(Object.keys(CONTACT_TYPE_LABELS) as ContactType[]).map((t) => (
              <button
                key={t}
                type="button"
                onClick={() => setForm({ ...form, type: t })}
                className={cn('h-10 rounded-xl border text-sm font-bold', form.type === t ? 'border-primary-600 bg-primary-600 text-white' : 'border-slate-200 bg-white text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300')}
              >
                {CONTACT_TYPE_LABELS[t]}
              </button>
            ))}
          </div>
        </div>

        <div className="grid grid-cols-2 gap-2">
          <label className="block space-y-1">
            <span className="text-sm font-medium text-slate-700 dark:text-slate-200">الهاتف</span>
            <input dir="ltr" inputMode="tel" value={form.phone ?? ''} onChange={(e) => setForm({ ...form, phone: e.target.value })} className={inputCls} />
          </label>
          <label className="block space-y-1">
            <span className="text-sm font-medium text-slate-700 dark:text-slate-200">واتساب</span>
            <input dir="ltr" inputMode="tel" value={form.whatsapp ?? ''} onChange={(e) => setForm({ ...form, whatsapp: e.target.value })} className={inputCls} />
          </label>
        </div>

        <label className="block space-y-1">
          <span className="text-sm font-medium text-slate-700 dark:text-slate-200">ملاحظات</span>
          <textarea rows={2} value={form.notes ?? ''} onChange={(e) => setForm({ ...form, notes: e.target.value })} className={cn(inputCls, 'h-auto py-2')} />
        </label>

        {contact && (
          <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
            <input type="checkbox" checked={form.is_active ?? true} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} className="h-4 w-4" />
            نشط (يظهر في قوائم الاختيار)
          </label>
        )}

        {error && <p className="text-xs font-semibold text-red-600">{error}</p>}

        <div className="flex items-center justify-between gap-2 pt-1">
          {contact ? (
            <button
              type="button"
              disabled={remove.isPending}
              onClick={() => { if (window.confirm('حذف هذه الجهة؟')) remove.mutate() }}
              className="text-sm font-bold text-red-600 disabled:opacity-60"
            >
              حذف
            </button>
          ) : <span />}
          <div className="flex gap-2">
            <button type="button" onClick={onClose} className="h-10 rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-600 dark:border-slate-600 dark:text-slate-300">إلغاء</button>
            <button type="submit" disabled={save.isPending} className="h-10 rounded-xl bg-primary-600 px-5 text-sm font-bold text-white disabled:opacity-60">
              {save.isPending ? '...' : 'حفظ'}
            </button>
          </div>
        </div>
      </form>
    </Dialog>
  )
}

// ── كشف الحساب ──────────────────────────────────────────────────────────────

function StatementView({ contactId, onBack }: { contactId: number; onBack: () => void }) {
  const farmId = useFarmStore((s) => s.currentFarm?.id)
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')

  const statement = useQuery({
    queryKey: ['contacts', farmId, 'statement', contactId, from, to],
    queryFn: () => contactsApi.statement(contactId, { ...(from ? { from } : {}), ...(to ? { to } : {}) }),
    enabled: !!farmId && Number.isFinite(contactId),
    staleTime: 30_000,
  })

  const s = statement.data
  const hasPayable = !!s && (s.contact.type !== 'buyer' || s.totals.payable.invoiced > 0 || s.opening.payable !== 0)
  const hasReceivable = !!s && (s.contact.type !== 'supplier' || s.totals.receivable.invoiced > 0 || s.opening.receivable !== 0)

  return (
    <div className="space-y-4 pb-20 sm:pb-8" dir="rtl">
      <button type="button" onClick={onBack} className="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 dark:text-slate-400">
        <ArrowRight className="h-4 w-4" /> كل الجهات
      </button>

      {statement.isLoading ? (
        <div className="flex justify-center py-16"><Loader2 className="h-6 w-6 animate-spin text-slate-400" /></div>
      ) : statement.isError || !s ? (
        <p className="py-10 text-center text-sm text-red-600">تعذر تحميل كشف الحساب</p>
      ) : (
        <>
          <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0">
                <h1 className="truncate text-lg font-bold text-slate-800 dark:text-slate-100">كشف حساب: {s.contact.name}</h1>
                <p className="text-xs text-slate-400">{CONTACT_TYPE_LABELS[s.contact.type]}</p>
              </div>
              <div className="flex shrink-0 gap-1.5">
                {s.contact.phone && (
                  <a href={`tel:${s.contact.phone}`} aria-label="اتصال" className="rounded-xl bg-slate-100 p-2 text-slate-600 dark:bg-slate-700 dark:text-slate-200"><Phone className="h-4 w-4" /></a>
                )}
                {s.contact.whatsapp && (
                  <a href={waLink(s.contact.whatsapp)} target="_blank" rel="noopener noreferrer" aria-label="واتساب" className="rounded-xl bg-emerald-50 p-2 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"><MessageCircle className="h-4 w-4" /></a>
                )}
              </div>
            </div>

            <div className="mt-3 grid grid-cols-2 gap-2 text-sm">
              {hasPayable && (
                <div className="rounded-xl bg-red-50 p-2.5 dark:bg-red-900/20">
                  <p className="text-xs text-slate-500 dark:text-slate-400">علينا له</p>
                  <p className="font-black tabular-nums text-red-600 dark:text-red-400">{formatCurrency(s.closing.payable)}</p>
                  <p className="text-[10px] text-slate-400">فواتير {formatCurrency(s.totals.payable.invoiced)} • <span className="font-bold text-rose-700 dark:text-rose-400">مدفوع {formatCurrency(s.totals.payable.paid)}</span></p>
                </div>
              )}
              {hasReceivable && (
                <div className="rounded-xl bg-emerald-50 p-2.5 dark:bg-emerald-900/20">
                  <p className="text-xs text-slate-500 dark:text-slate-400">لنا عليه</p>
                  <p className="font-black tabular-nums text-emerald-700 dark:text-emerald-400">{formatCurrency(s.closing.receivable)}</p>
                  <p className="text-[10px] text-slate-400">مبيعات {formatCurrency(s.totals.receivable.invoiced)} • <span className="font-bold text-emerald-700 dark:text-emerald-400">مقبوض {formatCurrency(s.totals.receivable.paid)}</span></p>
                </div>
              )}
            </div>
          </div>

          {/* نطاق التاريخ */}
          <div className="grid grid-cols-2 gap-2">
            <label className="block space-y-1">
              <span className="text-xs font-semibold text-slate-500 dark:text-slate-400">من</span>
              <input type="date" lang="en" value={from} onChange={(e) => setFrom(e.target.value)} className="h-10 w-full rounded-xl border border-slate-300 bg-white px-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
            </label>
            <label className="block space-y-1">
              <span className="text-xs font-semibold text-slate-500 dark:text-slate-400">إلى</span>
              <input type="date" lang="en" value={to} onChange={(e) => setTo(e.target.value)} className="h-10 w-full rounded-xl border border-slate-300 bg-white px-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
            </label>
          </div>

          <ul className="space-y-2">
            {from && (hasPayable || hasReceivable) && (
              <li className="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                رصيد افتتاحي قبل {from}:
                {hasPayable && <> علينا {formatCurrency(s.opening.payable)}</>}
                {hasReceivable && <> • لنا {formatCurrency(s.opening.receivable)}</>}
              </li>
            )}
            {s.entries.length === 0 ? (
              <li className="py-10 text-center text-sm text-slate-400">لا توجد حركات</li>
            ) : (
              s.entries.map((e, i) => <EntryRow key={`${e.kind}-${e.record_type}-${e.record_id}-${i}`} entry={e} />)
            )}
          </ul>
        </>
      )}
    </div>
  )
}

function EntryRow({ entry }: { entry: StatementEntry }) {
  const isInvoice = entry.kind === 'invoice'
  const running = entry.side === 'payable' ? entry.running_payable : entry.running_receivable

  return (
    <li className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-800">
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="truncate text-sm font-bold text-slate-800 dark:text-slate-100">
            {isInvoice ? entry.description : entry.side === 'receivable' ? 'قبض' : 'دفع'}
            {!isInvoice && entry.method && !entry.is_opening && <span className="font-normal text-slate-400"> • {PAYMENT_METHOD_LABELS[entry.method]}</span>}
            {entry.is_opening && <span className="font-normal text-slate-400"> • رصيد افتتاحي</span>}
          </p>
          <p className="text-[11px] text-slate-400">
            <span className="tabular-nums">{entry.date}</span>
            {entry.flock_name && <> • {entry.flock_name}</>}
            {!isInvoice && entry.note && !entry.is_opening && <> • {entry.note}</>}
          </p>
        </div>
        <div className="shrink-0 text-end">
          {entry.unpriced ? (
            <span className="text-xs font-bold text-amber-600">بانتظار السعر</span>
          ) : (
            <p className={cn('text-sm font-black tabular-nums', isInvoice ? 'text-slate-800 dark:text-slate-100' : 'text-emerald-700 dark:text-emerald-400')}>
              {isInvoice ? '' : entry.amount < 0 ? '+' : '−'}{formatCurrency(Math.abs(entry.amount))}
            </p>
          )}
          <p className="text-[10px] tabular-nums text-slate-400">الرصيد {formatCurrency(running)}</p>
        </div>
      </div>
    </li>
  )
}

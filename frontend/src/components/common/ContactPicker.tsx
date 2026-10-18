'use client'

// منتقي المورد/التاجر: حقل نصي قابل للبحث. اختيار جهة يضع اسمها في النص (للتوافق مع الحقل القديم)
// ويحفظ contact_id؛ الكتابة بعد الاختيار تفكّ الربط ويبقى النص حراً كما كان. «إضافة جديد» ينشئ الجهة فوراً.

import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Check, Loader2, Plus, UserRound } from 'lucide-react'
import { contactsApi } from '@/lib/api/contacts'
import type { Contact } from '@/lib/api/contacts'
import { useFarmStore } from '@/stores/farm.store'
import { cn } from '@/lib/utils'

interface Props {
  id: string
  label: string
  /** نوع الجهات المعروضة (يشمل «مورد وتاجر») ونوع الجهة الجديدة */
  kind: 'supplier' | 'buyer'
  text: string
  contactId: number | null
  onChange: (text: string, contactId: number | null) => void
  placeholder?: string
  error?: string
}

export function ContactPicker({ id, label, kind, text, contactId, onChange, placeholder = 'اختياري', error }: Props) {
  const qc = useQueryClient()
  const farmId = useFarmStore((s) => s.currentFarm?.id)
  const [open, setOpen] = useState(false)
  const [addError, setAddError] = useState<string | null>(null)

  // قائمة واحدة لكل مزرعة ونوع؛ البحث محلي فلا طلب مع كل حرف
  const contacts = useQuery({
    queryKey: ['contacts', farmId, 'picker', kind],
    queryFn: () => contactsApi.list({ type: kind, active: true }),
    enabled: !!farmId && open,
    staleTime: 5 * 60_000,
  })

  const q = text.trim()
  const matches = useMemo(() => {
    const list = contacts.data ?? []
    if (!q) return list.slice(0, 30)
    return list.filter((c) => c.name.includes(q)).slice(0, 30)
  }, [contacts.data, q])
  const exact = (contacts.data ?? []).some((c) => c.name === q)

  const add = useMutation({
    mutationFn: () => contactsApi.create({ name: q, type: kind }),
    onSuccess: (c: Contact) => {
      qc.invalidateQueries({ queryKey: ['contacts', farmId] })
      onChange(c.name, c.id)
      setOpen(false)
      setAddError(null)
    },
    onError: (e: unknown) => {
      const r = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })?.response?.data
      setAddError(r?.errors ? Object.values(r.errors)[0]?.[0] ?? null : r?.message ?? 'تعذرت الإضافة')
    },
  })

  return (
    <div className="relative flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-medium text-slate-700 dark:text-slate-200">{label}</label>
      <div className="relative">
        <UserRound className={cn('pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2', contactId ? 'text-emerald-500' : 'text-slate-400')} />
        <input
          id={id}
          type="text"
          autoComplete="off"
          value={text}
          placeholder={placeholder}
          onFocus={() => setOpen(true)}
          // تأخير بسيط كي يصل النقر على عنصر القائمة قبل إغلاقها
          onBlur={() => setTimeout(() => setOpen(false), 150)}
          onChange={(e) => { onChange(e.target.value, null); setOpen(true); setAddError(null) }}
          className={cn(
            'w-full rounded-xl border bg-white py-2.5 ps-9 pe-3.5 text-sm text-slate-900 placeholder:text-slate-400 dark:bg-[#1a1c21] dark:text-slate-100 dark:placeholder:text-slate-500',
            'focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/10',
            error ? 'border-red-400' : 'border-slate-300 dark:border-white/[0.08]'
          )}
        />
      </div>

      {open && (
        <div className="absolute inset-x-0 top-full z-30 mt-1 max-h-60 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-800">
          {contacts.isLoading ? (
            <div className="flex justify-center py-3"><Loader2 className="h-4 w-4 animate-spin text-slate-400" /></div>
          ) : (
            <>
              {matches.map((c) => (
                <button
                  key={c.id}
                  type="button"
                  onMouseDown={(e) => e.preventDefault()}
                  onClick={() => { onChange(c.name, c.id); setOpen(false) }}
                  className="flex w-full items-center justify-between gap-2 px-3 py-2 text-start text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                  <span className="truncate">{c.name}</span>
                  {contactId === c.id && <Check className="h-4 w-4 shrink-0 text-emerald-500" />}
                </button>
              ))}
              {matches.length === 0 && !q && (
                <p className="px-3 py-2 text-xs text-slate-400">لا توجد جهات بعد — اكتب الاسم ثم «إضافة جديد»</p>
              )}
              {q.length >= 2 && !exact && (
                <button
                  type="button"
                  onMouseDown={(e) => e.preventDefault()}
                  onClick={() => add.mutate()}
                  disabled={add.isPending}
                  className="flex w-full items-center gap-2 border-t border-slate-100 px-3 py-2 text-start text-sm font-bold text-primary-700 hover:bg-primary-50 disabled:opacity-60 dark:border-slate-700 dark:text-primary-300 dark:hover:bg-slate-700"
                >
                  {add.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
                  إضافة جديد «{q}»
                </button>
              )}
              {addError && <p className="px-3 py-1.5 text-xs text-red-600">{addError}</p>}
            </>
          )}
        </div>
      )}
      {error && <p className="text-xs text-red-600 dark:text-red-400">{error}</p>}
    </div>
  )
}

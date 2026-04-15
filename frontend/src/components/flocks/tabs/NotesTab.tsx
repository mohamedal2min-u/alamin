'use client'

import { useCallback, useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Plus, StickyNote } from 'lucide-react'
import { flockNotesApi } from '@/lib/api/flockNotes'
import { Button } from '@/components/ui/Button'
import { formatDate, cn } from '@/lib/utils'
import type { FlockNote, NoteType } from '@/types/flockNote'
import type { FlockStatus } from '@/types/flock'

// ── Note type labels & colours ────────────────────────────────────────────────
const NOTE_TYPES: { value: NoteType; label: string; className: string }[] = [
  { value: 'general',     label: 'عامة',        className: 'bg-slate-100 text-slate-700' },
  { value: 'instruction', label: 'تعليمات',     className: 'bg-blue-100 text-blue-700' },
  { value: 'operational', label: 'تشغيلية',     className: 'bg-emerald-100 text-emerald-700' },
  { value: 'alert',       label: 'تنبيه',        className: 'bg-red-100 text-red-700' },
]

function getNoteTypeInfo(type: NoteType) {
  return NOTE_TYPES.find((t) => t.value === type) ?? NOTE_TYPES[0]
}

// ── Schema ────────────────────────────────────────────────────────────────────
const schema = z.object({
  note_text:  z.string().min(1, 'نص الملاحظة مطلوب').max(10000, 'النص طويل جداً'),
  note_type:  z.enum(['general', 'instruction', 'operational', 'alert']),
  entry_date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'صيغة التاريخ غير صحيحة').optional().or(z.literal('')),
})
type FormData = z.infer<typeof schema>

// ── Props ─────────────────────────────────────────────────────────────────────
interface Props {
  flockId: number
  flockStatus: FlockStatus
}

// ── Component ─────────────────────────────────────────────────────────────────
export function NotesTab({ flockId, flockStatus }: Props) {
  const [notes, setNotes]             = useState<FlockNote[]>([])
  const [loading, setLoading]         = useState(true)
  const [fetchError, setFetchError]   = useState<string | null>(null)
  const [showForm, setShowForm]       = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)

  const canAdd = flockStatus === 'active'

  // ── Fetch notes ──────────────────────────────────────────────────────────
  const fetchNotes = useCallback(() => {
    setLoading(true)
    flockNotesApi
      .list(flockId)
      .then((res) => setNotes(res.data))
      .catch(() => setFetchError('تعذّر تحميل الملاحظات'))
      .finally(() => setLoading(false))
  }, [flockId])

  useEffect(() => {
    fetchNotes()
  }, [fetchNotes])

  // ── Form ─────────────────────────────────────────────────────────────────
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      note_type:  'general',
      entry_date: new Date().toISOString().split('T')[0],
    },
  })

  const handleCancel = () => {
    reset({ note_type: 'general', entry_date: new Date().toISOString().split('T')[0] })
    setServerError(null)
    setShowForm(false)
  }

  const onSubmit = async (data: FormData) => {
    setServerError(null)
    try {
      const res = await flockNotesApi.create(flockId, {
        note_text:  data.note_text,
        note_type:  data.note_type,
        entry_date: data.entry_date || undefined,
      })
      setNotes((prev) => [res.data, ...prev])
      handleCancel()
    } catch (err: unknown) {
      const axiosErr = err as {
        response?: { data?: { message?: string; errors?: Record<string, string[]> } }
      }
      const first = axiosErr?.response?.data?.errors
        ? Object.values(axiosErr.response.data.errors)[0]?.[0]
        : null
      setServerError(first ?? axiosErr?.response?.data?.message ?? 'حدث خطأ غير متوقع')
    }
  }

  // ── Render ────────────────────────────────────────────────────────────────
  if (loading) {
    return (
      <div className="flex items-center justify-center py-20 text-slate-400">
        <span className="text-sm">جارٍ التحميل...</span>
      </div>
    )
  }

  if (fetchError) {
    return (
      <div className="m-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
        {fetchError}
      </div>
    )
  }

  return (
    <div className="p-4 space-y-4">

      {/* Add button */}
      {canAdd && !showForm && (
        <div className="flex justify-end">
          <Button size="sm" onClick={() => setShowForm(true)}>
            <Plus className="me-1.5 h-4 w-4" />
            إضافة ملاحظة
          </Button>
        </div>
      )}

      {/* Inline form */}
      {canAdd && showForm && (
        <form
          onSubmit={handleSubmit(onSubmit)}
          className="rounded-xl border border-primary-200 bg-primary-50/40 p-4 space-y-3"
          noValidate
        >
          <p className="text-sm font-semibold text-slate-700">إضافة ملاحظة جديدة</p>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {/* Note type */}
            <div className="space-y-1">
              <label htmlFor="note_type" className="text-sm font-medium text-slate-700">
                نوع الملاحظة
              </label>
              <select
                {...register('note_type')}
                id="note_type"
                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
              >
                {NOTE_TYPES.map((t) => (
                  <option key={t.value} value={t.value}>{t.label}</option>
                ))}
              </select>
              {errors.note_type && (
                <p className="text-xs text-red-500">{errors.note_type.message}</p>
              )}
            </div>

            {/* Entry date */}
            <div className="space-y-1">
              <label htmlFor="note_entry_date" className="text-sm font-medium text-slate-700">
                التاريخ <span className="text-xs text-slate-400">(اختياري)</span>
              </label>
              <input
                {...register('entry_date')}
                id="note_entry_date"
                type="date"
                max={new Date().toISOString().split('T')[0]}
                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
              />
              {errors.entry_date && (
                <p className="text-xs text-red-500">{errors.entry_date.message}</p>
              )}
            </div>
          </div>

          {/* Note text */}
          <div className="space-y-1">
            <label htmlFor="note_text" className="text-sm font-medium text-slate-700">
              نص الملاحظة <span className="text-red-500">*</span>
            </label>
            <textarea
              {...register('note_text')}
              id="note_text"
              rows={3}
              placeholder="اكتب الملاحظة هنا..."
              className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 resize-none"
            />
            {errors.note_text && (
              <p className="text-xs text-red-500">{errors.note_text.message}</p>
            )}
          </div>

          {serverError && (
            <p className="text-sm text-red-600">{serverError}</p>
          )}

          <div className="flex gap-2 justify-end">
            <Button type="button" variant="outline" size="sm" onClick={handleCancel}>
              إلغاء
            </Button>
            <Button type="submit" size="sm" loading={isSubmitting}>
              حفظ
            </Button>
          </div>
        </form>
      )}

      {/* Empty state */}
      {notes.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-16 text-center text-slate-400">
          <StickyNote className="mb-3 h-10 w-10 opacity-30" />
          <p className="text-base font-medium text-slate-600">لا توجد ملاحظات</p>
          {canAdd && !showForm && (
            <p className="mt-1 text-sm">اضغط «إضافة ملاحظة» لتسجيل أول ملاحظة</p>
          )}
        </div>
      ) : (
        <div className="space-y-2">
          {notes.map((note) => (
            <NoteRow key={note.id} note={note} />
          ))}
        </div>
      )}

    </div>
  )
}

// ── NoteRow ───────────────────────────────────────────────────────────────────
function NoteRow({ note }: { note: FlockNote }) {
  const typeInfo = getNoteTypeInfo(note.note_type)

  return (
    <div className="rounded-xl border border-slate-200 bg-white px-4 py-3 space-y-1.5" style={{ boxShadow: 'var(--shadow-card)' }}>
      <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
        <span className={cn('inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', typeInfo.className)}>
          {typeInfo.label}
        </span>
        {note.entry_date && (
          <span className="text-xs text-slate-400">{formatDate(note.entry_date)}</span>
        )}
      </div>
      <p className="text-sm text-slate-800 whitespace-pre-wrap">{note.note_text}</p>
    </div>
  )
}

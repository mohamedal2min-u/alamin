'use client'

import { useState } from 'react'
import { Dialog } from '@/components/ui/Dialog'
import { Button } from '@/components/ui/Button'
import { quickEntryApi } from '@/lib/api/quick-entry'

interface NoteSheetProps {
  isOpen: boolean
  onClose: () => void
  flockId: number
  onSaved: (note: string) => void
}

export function NoteSheet({
  isOpen,
  onClose,
  flockId,
  onSaved,
}: NoteSheetProps) {
  const [note, setNote] = useState<string>('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!note.trim()) return

    setIsSubmitting(true)
    setError('')

    try {
      // Store note as an 'other' expense with 0 amount for now, 
      // or we can use another API if available.
      await quickEntryApi.logExpense(flockId, {
        expense_type: 'other',
        quantity: 1,
        total_amount: 0,
        entry_date: new Date().toISOString().split('T')[0],
        notes: `ملاحظة العامل: ${note}`,
      })
      onSaved(note)
      setNote('')
      onClose()
    } catch (err: any) {
      setError(err.response?.data?.message || 'حدث خطأ أثناء الحفظ')
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Dialog isOpen={isOpen} onClose={onClose} title="ملاحظة طارئة">
      <form onSubmit={handleSubmit} className="space-y-4 pt-4">
        {error && (
          <div className="p-3 bg-red-50 text-red-600 rounded-lg text-sm">
            {error}
          </div>
        )}
        
        <div>
          <label className="block text-sm font-medium text-slate-700 mb-1">
            الملاحظة
          </label>
          <textarea
            required
            rows={4}
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder="اكتب ملاحظتك هنا (حالة طيور، عطل فني...)"
            disabled={isSubmitting}
            className="w-full px-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-shadow bg-white text-slate-900 resize-none"
          />
        </div>

        <div className="pt-2">
          <Button 
            type="submit" 
            className="w-full" 
            disabled={!note.trim() || isSubmitting}
          >
            {isSubmitting ? 'جاري الحفظ...' : 'حفظ'}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}


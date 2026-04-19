'use client'

import { useState } from 'react'
import { Dialog } from '@/components/ui/Dialog'
import { Input } from '@/components/ui/Input'
import { Button } from '@/components/ui/Button'
import { mortalitiesApi } from '@/lib/api/mortalities'

interface MortalitySheetProps {
  isOpen: boolean
  onClose: () => void
  flockId: number
  onSaved: (quantity: number) => void
}

export function MortalitySheet({
  isOpen,
  onClose,
  flockId,
  onSaved,
}: MortalitySheetProps) {
  const [quantity, setQuantity] = useState<string>('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!quantity) return

    setIsSubmitting(true)
    setError('')

    try {
      const qtyNum = parseInt(quantity, 10)
      if (qtyNum === 0) {
        // Special case: if worker enters 0, we might just mark it as completed without API call
        // Or call an API if the backend accepts 0. 
        // We'll assume the backend accepts it or we handle it locally.
      }
      
      await mortalitiesApi.create(flockId, {
        entry_date: new Date().toISOString().split('T')[0],
        quantity: qtyNum,
        reason: 'تسجيل الوردية - الياسين',
      })
      onSaved(qtyNum)
      setQuantity('')
      onClose()
    } catch (err: any) {
      setError(err.response?.data?.message || 'حدث خطأ أثناء الحفظ')
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Dialog isOpen={isOpen} onClose={onClose} title="تسجيل النفوق اليومي">
      <form onSubmit={handleSubmit} className="space-y-4 pt-4">
        {error && (
          <div className="p-3 bg-red-50 text-red-600 rounded-lg text-sm">
            {error}
          </div>
        )}
        
        <div>
          <label className="block text-sm font-medium text-slate-700 mb-1">
            العدد (طير)
          </label>
          <Input
            type="number"
            step="1"
            min="0"
            required
            value={quantity}
            onChange={(e) => setQuantity(e.target.value)}
            placeholder="أدخل عدد الطيور النافقة"
            disabled={isSubmitting}
            dir="ltr"
          />
          <p className="mt-1 flex text-xs text-slate-500">
            أدخل 0 إذا لم يكن هناك نفوق.
          </p>
        </div>

        <div className="pt-2">
          <Button 
            type="submit" 
            className="w-full" 
            disabled={!quantity || isSubmitting}
          >
            {isSubmitting ? 'جاري الحفظ...' : 'حفظ'}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}


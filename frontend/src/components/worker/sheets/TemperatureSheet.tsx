'use client'

import { useState } from 'react'
import { Dialog } from '@/components/ui/Dialog'
import { Input } from '@/components/ui/Input'
import { Button } from '@/components/ui/Button'
import { workerApi } from '@/lib/api/worker'

interface TemperatureSheetProps {
  isOpen: boolean
  onClose: () => void
  flockId: number
  timeOfDay: 'morning' | 'afternoon' | 'evening'
  title: string
  onSaved: (temperature: number) => void
}

export function TemperatureSheet({
  isOpen,
  onClose,
  flockId,
  timeOfDay,
  title,
  onSaved,
}: TemperatureSheetProps) {
  const [temperature, setTemperature] = useState<string>('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!temperature) return

    setIsSubmitting(true)
    setError('')

    try {
      const tempNum = parseFloat(temperature)
      await workerApi.logTemperature(flockId, {
        log_date: new Date().toISOString().split('T')[0],
        time_of_day: timeOfDay,
        temperature: tempNum,
      })
      onSaved(tempNum)
      setTemperature('')
      onClose()
    } catch (err: any) {
      setError(err.response?.data?.message || 'حدث خطأ أثناء الحفظ')
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Dialog isOpen={isOpen} onClose={onClose} title={title}>
      <form onSubmit={handleSubmit} className="space-y-4 pt-4">
        {error && (
          <div className="p-3 bg-red-50 text-red-600 rounded-lg text-sm">
            {error}
          </div>
        )}
        
        <div>
          <label className="block text-sm font-medium text-slate-700 mb-1">
            درجة الحرارة (°C)
          </label>
          <Input
            type="number"
            step="0.1"
            required
            value={temperature}
            onChange={(e) => setTemperature(e.target.value)}
            placeholder="مثال: 28.5"
            disabled={isSubmitting}
            dir="ltr"
          />
        </div>

        <div className="pt-2">
          <Button 
            type="submit" 
            className="w-full" 
            disabled={!temperature || isSubmitting}
          >
            {isSubmitting ? 'جاري الحفظ...' : 'حفظ'}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}

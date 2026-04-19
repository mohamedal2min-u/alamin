'use client'

import { useState, useEffect } from 'react'
import { Dialog } from '@/components/ui/Dialog'
import { Input } from '@/components/ui/Input'
import { Button } from '@/components/ui/Button'
import { inventoryApi } from '@/lib/api/inventory'
import { quickEntryApi } from '@/lib/api/quick-entry'
import type { InventoryItem } from '@/types/dashboard'

interface MedicineSheetProps {
  isOpen: boolean
  onClose: () => void
  flockId: number
  onSaved: (hasMedicine: boolean, label?: string) => void
}

export function MedicineSheet({
  isOpen,
  onClose,
  flockId,
  onSaved,
}: MedicineSheetProps) {
  const [hasMedicine, setHasMedicine] = useState<boolean | null>(null)
  const [quantity, setQuantity] = useState<string>('')
  const [itemId, setItemId] = useState<number | ''>('')
  
  const [medicineItems, setMedicineItems] = useState<InventoryItem[]>([])
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [isLoadingItems, setIsLoadingItems] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    if (isOpen) {
      setIsLoadingItems(true)
      inventoryApi.items()
        .then((res) => {
          const meds = res.data.filter(i => i.type_code === 'medicine' || i.type_code === 'vaccine')
          setMedicineItems(meds)
          if (meds.length === 1) {
            setItemId(meds[0].id)
          }
        })
        .catch(() => setError('فشل جلب أصناف الأدوية/اللقاحات'))
        .finally(() => setIsLoadingItems(false))
    } else {
      setHasMedicine(null)
      setQuantity('')
      setItemId('')
      setError('')
    }
  }, [isOpen])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    if (hasMedicine === false) {
      onSaved(false)
      onClose()
      return
    }

    if (!quantity || !itemId) return

    setIsSubmitting(true)
    setError('')

    try {
      const qtyNum = parseFloat(quantity)
      
      await quickEntryApi.logMedicine(flockId, {
        entry_date: new Date().toISOString().split('T')[0],
        item_id: Number(itemId),
        quantity: qtyNum,
      })
      
      const itemLabel = medicineItems.find(i => i.id === Number(itemId))?.name
      onSaved(true, itemLabel ? `${itemLabel} (${qtyNum})` : 'تم تسجيل الدواء')
      onClose()
      
    } catch (err: any) {
      setError(err.response?.data?.message || 'حدث خطأ أثناء الحفظ')
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Dialog isOpen={isOpen} onClose={onClose} title="دواء اليوم">
      <form onSubmit={handleSubmit} className="space-y-4 pt-4">
        {error && (
          <div className="p-3 bg-red-50 text-red-600 rounded-lg text-sm">
            {error}
          </div>
        )}
        
        {hasMedicine === null && (
          <div className="grid grid-cols-2 gap-3">
            <Button type="button" variant="outline" onClick={() => setHasMedicine(true)}>
              يوجد دواء
            </Button>
            <Button type="button" onClick={handleSubmit} className="bg-slate-800 hover:bg-slate-900" onClickCapture={() => setHasMedicine(false)}>
              لا يوجد
            </Button>
          </div>
        )}

        {hasMedicine === true && (
          <>
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">
                صنف الدواء/اللقاح
              </label>
              <select
                className="w-full h-11 px-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-shadow bg-white text-slate-900"
                required
                value={itemId}
                onChange={(e) => setItemId(Number(e.target.value))}
                disabled={isSubmitting || isLoadingItems}
              >
                <option value="" disabled>اختر الصنف...</option>
                {medicineItems.map(item => (
                  <option key={item.id} value={item.id}>{item.name}</option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">
                الكمية المطلوبة
              </label>
              <Input
                type="number"
                step="0.1"
                min="0"
                required
                value={quantity}
                onChange={(e) => setQuantity(e.target.value)}
                placeholder="مثال: 2.5"
                disabled={isSubmitting}
                dir="ltr"
              />
            </div>

            <div className="pt-2 flex gap-3">
              <Button 
                type="button" 
                variant="outline" 
                className="flex-1"
                onClick={() => setHasMedicine(null)}
                disabled={isSubmitting}
              >
                تراجع
              </Button>
              <Button 
                type="submit" 
                className="flex-1" 
                disabled={!quantity || !itemId || isSubmitting}
              >
                {isSubmitting ? 'جاري الحفظ...' : 'حفظ'}
              </Button>
            </div>
          </>
        )}
      </form>
    </Dialog>
  )
}


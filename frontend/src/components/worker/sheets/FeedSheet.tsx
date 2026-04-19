'use client'

import { useState, useEffect } from 'react'
import { Dialog } from '@/components/ui/Dialog'
import { Input } from '@/components/ui/Input'
import { Button } from '@/components/ui/Button'
import { inventoryApi } from '@/lib/api/inventory'
import { quickEntryApi } from '@/lib/api/quick-entry'
import type { InventoryItem } from '@/types/dashboard'

interface FeedSheetProps {
  isOpen: boolean
  onClose: () => void
  flockId: number
  onSaved: (quantity: number) => void
}

export function FeedSheet({
  isOpen,
  onClose,
  flockId,
  onSaved,
}: FeedSheetProps) {
  const [quantity, setQuantity] = useState<string>('')
  const [bags, setBags] = useState<string>('')
  const [extraKg, setExtraKg] = useState<string>('')
  const [itemId, setItemId] = useState<number | ''>('')
  const [feedItems, setFeedItems] = useState<InventoryItem[]>([])
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [isLoadingItems, setIsLoadingItems] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    if (isOpen) {
      setIsLoadingItems(true)
      inventoryApi.items()
        .then((res) => {
          const feed = res.data.filter(i => i.type_code === 'feed')
          setFeedItems(feed)
          if (feed.length === 1) {
            setItemId(feed[0].id)
          }
        })
        .catch(() => setError('فشل جلب أصناف العلف'))
        .finally(() => setIsLoadingItems(false))
    } else {
      setQuantity('')
      setBags('')
      setExtraKg('')
      setItemId('')
      setError('')
    }
  }, [isOpen])

  const handleSubmit = async (e: React.FormEvent) => {
    if (!itemId) return
    const item = feedItems.find(i => i.id === itemId)
    const isUnitBased = item && item.unit_value > 1
    
    if (isUnitBased) {
      if (!bags && !extraKg) return
    } else {
      if (!quantity) return
    }

    setIsSubmitting(true)
    setError('')

    try {
      let qtyNum = 0
      if (item && item.unit_value > 1) {
        qtyNum = Number(bags) + (Number(extraKg) / item.unit_value)
      } else {
        qtyNum = parseFloat(quantity)
      }
      
      await quickEntryApi.logFeed(flockId, {
        entry_date: new Date().toISOString().split('T')[0],
        item_id: Number(itemId),
        quantity: qtyNum,
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
    <Dialog isOpen={isOpen} onClose={onClose} title="تسجيل العلف المصروف">
      <form onSubmit={handleSubmit} className="space-y-4 pt-4">
        {error && (
          <div className="p-3 bg-red-50 text-red-600 rounded-lg text-sm">
            {error}
          </div>
        )}
        
        <div>
          <label className="block text-sm font-medium text-slate-700 mb-1">
            صنف العلف
          </label>
          <select
            className="w-full h-11 px-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-shadow bg-white text-slate-900"
            required
            value={itemId}
            onChange={(e) => setItemId(Number(e.target.value))}
            disabled={isSubmitting || isLoadingItems}
          >
            <option value="" disabled>اختر الصنف...</option>
            {feedItems.map(item => (
              <option key={item.id} value={item.id}>{item.name}</option>
            ))}
          </select>
        </div>

        {(() => {
          const item = feedItems.find(i => i.id === itemId)
          if (item && item.unit_value > 1) {
            return (
              <div className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">
                      عدد الأكياس ({item.input_unit})
                    </label>
                    <Input
                      type="number"
                      min="0"
                      value={bags}
                      onChange={(e) => setBags(e.target.value)}
                      placeholder="0"
                      disabled={isSubmitting}
                      dir="ltr"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">
                      وزن إضافي (كيلو)
                    </label>
                    <Input
                      type="number"
                      step="0.01"
                      min="0"
                      value={extraKg}
                      onChange={(e) => setExtraKg(e.target.value)}
                      placeholder="0.00"
                      disabled={isSubmitting}
                      dir="ltr"
                    />
                  </div>
                </div>
                {(Number(bags) > 0 || Number(extraKg) > 0) && (
                  <div className="p-3 bg-primary-50 text-primary-700 rounded-xl text-xs font-bold border border-primary-100 italic">
                    الإجمالي المستهلك: {(Number(bags) * item.unit_value + Number(extraKg)).toFixed(2)} كجم
                  </div>
                )}
              </div>
            )
          }
          return (
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">
                الكمية (كجم)
              </label>
              <Input
                type="number"
                step="1"
                min="0"
                required
                value={quantity}
                onChange={(e) => setQuantity(e.target.value)}
                placeholder="مثال: 450"
                disabled={isSubmitting}
                dir="ltr"
              />
            </div>
          )
        })()}

        <div className="pt-2">
          <Button 
            type="submit" 
            className="w-full" 
            disabled={!quantity || !itemId || isSubmitting}
          >
            {isSubmitting ? 'جاري الحفظ...' : 'حفظ'}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}


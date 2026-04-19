'use client'

import React, { useEffect, useState } from 'react'
import { Partner } from '@/lib/api/partners'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { X, User, Mail, MessageSquare, Percent, Info, Lock, Phone } from 'lucide-react'

interface PartnerModalProps {
  isOpen: boolean
  onClose: () => void
  onSave: (data: any) => Promise<void>
  initialData?: Partner | null
  hasActiveFlock?: boolean
}

export const PartnerModal = ({ isOpen, onClose, onSave, initialData, hasActiveFlock = false }: PartnerModalProps) => {
  const [formData, setFormData] = useState<any>({
    name: '',
    email: '',
    whatsapp: '',
    password: '',
    notes: '',
    share_percent: 0,
    status: 'active'
  })
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    if (initialData) {
      setFormData({
        name: initialData.name,
        email: initialData.email || '',
        whatsapp: initialData.whatsapp || '',
        password: '',
        notes: initialData.notes || '',
        status: initialData.status,
        share_percent: initialData.shares?.[0]?.share_percent || 0
      })
    } else {
      setFormData({
        name: '',
        email: '',
        whatsapp: '',
        password: '',
        notes: '',
        share_percent: 0,
        status: 'active'
      })
    }
  }, [initialData, isOpen])

  if (!isOpen) return null

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsSubmitting(true)
    try {
      await onSave(formData)
      onClose()
    } catch (error: any) {
      console.error('Failed to save partner:', error)
      const msg = error?.response?.data?.message || 'حدث خطأ. تأكد من أن نسبة الحصة المتبقية لمدير المزرعة تكفي، وأن رقم الواتساب غير مسجل مسبقاً.'
      alert(msg)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div 
      className="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-4 bg-black/50 backdrop-blur-sm"
      onClick={(e) => { if (e.target === e.currentTarget) onClose() }}
      dir="rtl"
    >
      <div className="w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden max-h-[92vh] flex flex-col">
        
        {/* ─── Header ─── */}
        <div className="flex items-center justify-between px-6 py-5 border-b border-slate-100 shrink-0">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-slate-900 flex items-center justify-center">
              <User className="w-5 h-5 text-white" />
            </div>
            <div>
              <h3 className="text-lg font-black text-slate-900">
                {initialData ? 'تعديل بيانات الشريك' : 'إضافة شريك جديد'}
              </h3>
              <p className="text-[11px] font-medium text-slate-400">إدارة الصلاحيات والحصص المالية</p>
            </div>
          </div>
          <button 
            onClick={onClose} 
            className="w-9 h-9 flex items-center justify-center hover:bg-slate-100 rounded-xl transition-colors text-slate-400 hover:text-slate-700"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* ─── Form ─── */}
        <form onSubmit={handleSubmit} className="flex-1 overflow-y-auto p-6 space-y-5">
          
          {/* Info Banner */}
          <div className="flex items-start gap-3 p-3.5 bg-sky-50 border border-sky-100 rounded-xl text-xs text-sky-700">
            <Info className="w-4 h-4 shrink-0 mt-0.5" />
            <p className="font-medium leading-relaxed">
              رقم الواتساب هو اسم المستخدم للدخول. الشريك يحصل على صلاحية "قراءة فقط".
            </p>
          </div>

          {/* Name */}
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-slate-500 mr-1">اسم الشريك</label>
            <div className="relative">
              <User className="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-300" />
              <Input
                required
                className="pr-10 h-12 rounded-xl border-slate-200 font-medium"
                placeholder="الاسم الكامل"
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              />
            </div>
          </div>

          {/* Whatsapp + Password */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-rose-500 mr-1">رقم الواتساب *</label>
              <div className="relative">
                <Phone className="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-300" />
                <Input
                  required
                  className="pr-10 h-12 rounded-xl border-slate-200 font-medium"
                  placeholder="05xxxxxxxx"
                  value={formData.whatsapp}
                  onChange={(e) => setFormData({ ...formData, whatsapp: e.target.value })}
                />
              </div>
            </div>
            
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-slate-500 mr-1">
                {initialData ? 'تحديث كلمة المرور' : 'كلمة المرور'}
              </label>
              <div className="relative">
                <Lock className="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-300" />
                <Input
                  type="password"
                  required={!initialData}
                  className="pr-10 h-12 rounded-xl border-slate-200 font-medium"
                  placeholder={initialData ? 'اتركه فارغاً إن لم تريد التغيير' : '********'}
                  value={formData.password}
                  onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                />
              </div>
            </div>
          </div>

          {/* Share + Email */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <div className="flex items-center justify-between px-1">
                <label className="text-xs font-bold text-slate-500">الحصة الربحية (%)</label>
                {Number(formData.share_percent) <= 0 && (
                  <span className="text-[9px] font-bold text-rose-500 bg-rose-50 px-2 py-0.5 rounded-full">سيصبح غير نشط</span>
                )}
              </div>
              <div className="relative">
                <Percent className={`absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 ${hasActiveFlock ? 'text-slate-200' : 'text-primary-400'}`} />
                <Input
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  required
                  disabled={hasActiveFlock}
                  className={`pr-10 h-12 rounded-xl font-bold text-lg border-slate-200 ${
                    hasActiveFlock ? 'bg-slate-50 text-slate-400 cursor-not-allowed' : ''
                  }`}
                  value={formData.share_percent}
                  onChange={(e) => setFormData({ ...formData, share_percent: Number(e.target.value) })}
                />
              </div>
              {hasActiveFlock && (
                <p className="text-[10px] text-amber-600 font-bold flex items-center gap-1 mr-1">
                  <Info className="w-3 h-3" />
                  لا يمكن تعديل الحصص أثناء وجود فوج نشط
                </p>
              )}
              {!hasActiveFlock && (
                <p className="text-[10px] text-slate-400 font-medium mr-1">
                  يتم خصم النسبة تلقائياً من حصة مدير المزرعة
                </p>
              )}
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-bold text-slate-500 mr-1">البريد الإلكتروني</label>
              <div className="relative">
                <Mail className="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-300" />
                <Input
                  type="email"
                  className="pr-10 h-12 rounded-xl border-slate-200 font-medium"
                  placeholder="اختياري"
                  value={formData.email}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                />
              </div>
            </div>
          </div>

          {/* Notes */}
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-slate-500 mr-1">ملاحظات</label>
            <textarea
              className="w-full min-h-[80px] p-3.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-900/10 focus:border-slate-400 transition-all font-medium placeholder:text-slate-300 resize-none"
              placeholder="ملاحظات إضافية حول الشريك..."
              value={formData.notes}
              onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
            />
          </div>

          {/* Actions */}
          <div className="flex gap-3 pt-2">
            <Button
              type="submit"
              className="flex-1 bg-slate-900 hover:bg-slate-800 text-white font-bold h-12 rounded-xl transition-all active:scale-[0.98]"
              disabled={isSubmitting}
            >
              {isSubmitting ? 'جاري الحفظ...' : initialData ? 'حفظ التعديلات' : 'إنشاء الشريك'}
            </Button>
            <Button
              type="button"
              variant="outline"
              onClick={onClose}
              className="px-6 h-12 rounded-xl font-bold text-slate-500 border-slate-200 hover:bg-slate-50"
            >
              إلغاء
            </Button>
          </div>
        </form>
      </div>
    </div>
  )
}


'use client'

import React, { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Card, CardHeader } from '@/components/ui/Card'
import { Input } from '@/components/ui/Input'
import { Button } from '@/components/ui/Button'
import { workersApi } from '@/lib/api/workers'
import { ArrowRight, UserPlus, Loader2, CheckCircle } from 'lucide-react'

export default function NewWorkerPage() {
  const router = useRouter()
  
  const [loading, setLoading] = useState(false)
  const [successMsg, setSuccessMsg] = useState('')
  const [errorMsg, setErrorMsg] = useState('')
  const [formData, setFormData] = useState({
    name: '',
    whatsapp: '',
    email: '',
    password: '',
    salary: ''
  })

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setFormData(prev => ({ ...prev, [e.target.name]: e.target.value }))
    setErrorMsg('')
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setErrorMsg('')
    setSuccessMsg('')
    
    if (!formData.name || !formData.whatsapp || !formData.password) {
      setErrorMsg('يرجى تعبئة الحقول الإلزامية (الاسم، رقم الواتساب، كلمة المرور).')
      return
    }

    setLoading(true)
    try {
      await workersApi.create({
        name: formData.name,
        whatsapp: formData.whatsapp,
        email: formData.email,
        password: formData.password,
        salary: formData.salary ? parseFloat(formData.salary) : undefined,
      })
      
      setSuccessMsg('تم إنشاء حساب العامل وربطه بالمدجنة بنجاح.')
      
      setFormData({
        name: '',
        whatsapp: '',
        email: '',
        password: '',
        salary: ''
      })
      
      setTimeout(() => {
        router.push('/dashboard')
      }, 2000)
    } catch (error: any) {
      const message = error.response?.data?.message || 'حدث خطأ أثناء الإنشاء.'
      setErrorMsg(message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="container py-8 max-w-2xl mx-auto space-y-6" dir="rtl">
      <div className="flex items-center gap-4 mb-6">
        <Button variant="ghost" size="sm" onClick={() => router.back()} className="w-10 px-0">
          <ArrowRight className="h-5 w-5" />
        </Button>
        <h1 className="text-2xl font-bold text-slate-800 tracking-tight">إضافة عامل جديد</h1>
      </div>

      <Card className="border-slate-200/60 shadow-sm">
        <CardHeader className="bg-slate-50/50 border-b border-slate-100 flex-row gap-2 items-center p-5 rounded-t-2xl">
          <UserPlus className="h-5 w-5 text-emerald-600" />
          <h2 className="text-lg font-semibold">بيانات العامل</h2>
        </CardHeader>
        <div className="p-6">
          
          {successMsg && (
            <div className="mb-6 flex items-center gap-3 bg-emerald-50 border border-emerald-100 text-emerald-700 px-4 py-3 rounded-xl text-sm font-bold">
              <CheckCircle className="w-4 h-4 shrink-0" />
              {successMsg}
            </div>
          )}

          {errorMsg && (
            <div className="mb-6 flex items-center gap-3 bg-rose-50 border border-rose-100 text-rose-700 px-4 py-3 rounded-xl text-sm font-bold">
              {errorMsg}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-5">
            <div className="space-y-2">
              <label htmlFor="name" className="text-sm font-bold text-slate-700">اسم العامل <span className="text-rose-500">*</span></label>
              <Input 
                id="name"
                name="name" 
                placeholder="مثال: أحمد عبد الله" 
                value={formData.name}
                onChange={handleChange}
                required
                className="h-11"
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div className="space-y-2">
                <label htmlFor="whatsapp" className="text-sm font-bold text-slate-700">رقم الهاتف / واتساب <span className="text-rose-500">*</span></label>
                <Input 
                  id="whatsapp"
                  name="whatsapp" 
                  placeholder="مثال: 0500000000" 
                  value={formData.whatsapp}
                  onChange={handleChange}
                  dir="ltr"
                  className="h-11 text-left"
                />
              </div>

              <div className="space-y-2">
                <label htmlFor="email" className="text-sm font-bold text-slate-700">البريد الإلكتروني (اختياري)</label>
                <Input 
                  id="email"
                  name="email" 
                  type="email"
                  placeholder="name@example.com"
                  value={formData.email}
                  onChange={handleChange}
                  dir="ltr"
                  className="h-11 text-left"
                />
              </div>

              <div className="space-y-2">
                <label htmlFor="password" className="text-sm font-bold text-slate-700">كلمة المرور المشفرة <span className="text-rose-500">*</span></label>
                <Input 
                  id="password"
                  name="password" 
                  type="password"
                  placeholder="******" 
                  value={formData.password}
                  onChange={handleChange}
                  required
                  dir="ltr"
                  className="h-11 text-left"
                />
              </div>

              <div className="space-y-2">
                <label htmlFor="salary" className="text-sm font-bold text-slate-700">الراتب / المصروف الشهري الأساسي (USD)</label>
                <Input 
                  id="salary"
                  name="salary" 
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00" 
                  value={formData.salary}
                  onChange={handleChange}
                  dir="ltr"
                  className="h-11 text-left"
                />
              </div>
            </div>

            <div className="pt-4 flex justify-end">
              <Button 
                type="submit" 
                size="lg" 
                disabled={loading}
                className="w-full md:w-auto bg-emerald-600 hover:bg-emerald-700 text-white gap-2 min-w-[150px] rounded-xl h-12 font-bold"
              >
                {loading ? (
                  <>
                    <Loader2 className="h-5 w-5 animate-spin" />
                    جاري الحفظ...
                  </>
                ) : (
                  <>حفظ بيانات العامل</>
                )}
              </Button>
            </div>
          </form>
        </div>
      </Card>
      
      <p className="text-sm text-slate-500 text-center leading-relaxed max-w-md mx-auto">
        بحفظك لهذه البيانات، يتم إنشاء الحساب آلياً وربطه بالمزرعة الحالية. سيتجه النظام للعامل مباشرة لواجهته اليومية عند إدخاله لبريده وكلمة سره. ولا يتم تسجيل الراتب محاسبياً حتى تقوم أنت بصرفه.
      </p>
    </div>
  )
}

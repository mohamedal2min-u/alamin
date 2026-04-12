'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { Plus, Users, Phone, Mail, DollarSign, Calendar, Trash2, Loader2, UserPlus } from 'lucide-react'
import { workersApi } from '@/lib/api/workers'
import { toast } from 'sonner'
import { cn } from '@/lib/utils'
import { format } from 'date-fns'
import { ar } from 'date-fns/locale'

interface Worker {
  id: number
  name: string
  email: string
  whatsapp: string | null
  salary: number | null
  joined_at: string
  status: string
}

export default function WorkersPage() {
  const [workers, setWorkers] = useState<Worker[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [isDeleting, setIsDeleting] = useState<number | null>(null)

  const fetchWorkers = async () => {
    try {
      setIsLoading(true)
      const response = await workersApi.list()
      setWorkers(response.data.data)
    } catch (error) {
      console.error('Failed to fetch workers:', error)
      toast.error('فشل في جلب قائمة العمال')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    fetchWorkers()
  }, [])

  const handleDelete = async (id: number, name: string) => {
    if (!confirm(`هل أنت متأكد من حذف العامل "${name}" من المزرعة؟`)) return

    try {
      setIsDeleting(id)
      await workersApi.delete(id)
      toast.success('تم حذف العامل بنجاح')
      setWorkers(prev => prev.filter(w => w.id !== id))
    } catch (error) {
      console.error('Failed to delete worker:', error)
      toast.error('فشل في حذف العامل')
    } finally {
      setIsDeleting(null)
    }
  }

  return (
    <div className="p-4 md:p-8 max-w-7xl mx-auto space-y-6 pb-24 md:pb-8">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">إدارة العمال</h1>
          <p className="text-slate-500 text-sm mt-1">عرض وإدارة الحسابات الخاصة بالعمال في المزرعة</p>
        </div>
        <Link
          href="/workers/new"
          className="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-primary-600 text-white rounded-xl font-bold text-sm hover:bg-primary-700 transition-all duration-200 shadow-sm shadow-primary-200 active:scale-95"
        >
          <UserPlus className="h-4 w-4" />
          إضافة عامل جديد
        </Link>
      </div>

      {isLoading ? (
        <div className="flex flex-col items-center justify-center py-20 bg-white rounded-2xl border border-slate-100">
          <Loader2 className="h-10 w-10 text-primary-500 animate-spin mb-4" />
          <p className="text-slate-500 font-medium">جاري تحميل البيانات...</p>
        </div>
      ) : workers.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-20 bg-white rounded-2xl border border-dashed border-slate-200 text-center px-6">
          <div className="h-16 w-16 bg-slate-50 rounded-full flex items-center justify-center mb-4">
            <Users className="h-8 w-8 text-slate-300" />
          </div>
          <h3 className="text-lg font-bold text-slate-800">لا يوجد عمال مضافين</h3>
          <p className="text-slate-500 max-w-sm mt-1 mb-6">لم تقم بإضافة أي عامل لهذه المزرعة بعد. أضف عاملاً لتمكينه من إدخال البيانات اليومية.</p>
          <Link
            href="/workers/new"
            className="inline-flex items-center justify-center gap-2 px-6 py-2 bg-white border-2 border-primary-600 text-primary-600 rounded-xl font-bold text-sm hover:bg-primary-50 transition-all duration-200 active:scale-95"
          >
            <Plus className="h-4 w-4" />
            ابدأ بإضافة أول عامل
          </Link>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {workers.map((worker) => (
            <div 
              key={worker.id}
              className="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm hover:shadow-md transition-all duration-300 group"
            >
              <div className="flex justify-between items-start mb-4">
                <div className="flex items-center gap-3">
                  <div className="h-12 w-12 rounded-xl bg-orange-50 border border-orange-100 flex items-center justify-center text-orange-600 group-hover:bg-orange-600 group-hover:text-white transition-colors duration-300 font-bold text-lg">
                    {worker.name.charAt(0)}
                  </div>
                  <div>
                    <h3 className="font-bold text-slate-900 group-hover:text-primary-600 transition-colors uppercase">{worker.name}</h3>
                    <div className={cn(
                      "inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold mt-1",
                      worker.status === 'active' ? "bg-emerald-50 text-emerald-600" : "bg-red-50 text-red-600"
                    )}>
                      {worker.status === 'active' ? 'نشط' : 'غير نشط'}
                    </div>
                  </div>
                </div>
                
                <button
                  onClick={() => handleDelete(worker.id, worker.name)}
                  disabled={isDeleting === worker.id}
                  className="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all"
                  title="حذف العامل"
                >
                  {isDeleting === worker.id ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  ) : (
                    <Trash2 className="h-4 w-4" />
                  )}
                </button>
              </div>

              <div className="space-y-2.5 border-t border-slate-50 pt-4">
                <div className="flex items-center gap-3 text-slate-600">
                  <Mail className="h-4 w-4 text-slate-400 shrink-0" />
                  <span className="text-xs truncate" title={worker.email}>{worker.email}</span>
                </div>
                
                {worker.whatsapp && (
                  <div className="flex items-center gap-3 text-slate-600">
                    <Phone className="h-4 w-4 text-slate-400 shrink-0" />
                    <span className="text-xs">{worker.whatsapp}</span>
                  </div>
                )}

                <div className="flex items-center gap-3 text-slate-600">
                  <DollarSign className="h-4 w-4 text-slate-400 shrink-0" />
                  <span className="text-xs font-bold text-slate-900">
                    {worker.salary?.toLocaleString() ?? 'غير محدد'}
                    <span className="text-[10px] text-slate-500 font-normal mr-1">USD/شهرياً</span>
                  </span>
                </div>

                <div className="flex items-center gap-3 text-slate-500 mt-2">
                  <Calendar className="h-3.5 w-3.5 text-slate-300 shrink-0" />
                  <span className="text-[11px]">
                    انضم منذ {format(new Date(worker.joined_at), 'dd MMMM yyyy', { locale: ar })}
                  </span>
                </div>
              </div>

              <div className="mt-5 grid grid-cols-1">
                <Link 
                  href={`/reports?tab=workers&worker_id=${worker.id}`}
                  className="w-full py-2 bg-slate-50 text-slate-600 hover:bg-primary-50 hover:text-primary-700 rounded-xl text-center text-xs font-bold transition-all border border-transparent hover:border-primary-100"
                >
                  عرض التقارير والنشاط
                </Link>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

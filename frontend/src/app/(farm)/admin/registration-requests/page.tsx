'use client'

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '@/lib/api/client'
import { ClipboardList, CheckCircle2, XCircle, Clock } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { Skeleton } from '@/components/ui/Skeleton'

type RegistrationRequest = {
  id: number
  name: string
  whatsapp: string
  email: string | null
  farm_name: string | null
  location: string | null
  status: 'pending' | 'approved' | 'rejected'
  created_at: string
}

export default function RegistrationRequestsPage() {
  const queryClient = useQueryClient()

  const { data, isLoading, isError } = useQuery({
    queryKey: ['admin-registration-requests'],
    queryFn: async () => {
      const res = await apiClient.get<{ data: RegistrationRequest[] }>('/admin/registration-requests')
      return res.data.data
    },
  })

  const approveMutation = useMutation({
    mutationFn: async (id: number) => {
      await apiClient.post(`/admin/registration-requests/${id}/approve`)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-registration-requests'] })
      alert('تم قبول الطلب وتجهيز المزرعة بنجاح.')
    },
    onError: () => alert('حدث خطأ أثناء القبول.')
  })

  const rejectMutation = useMutation({
    mutationFn: async ({ id, reason }: { id: number; reason: string }) => {
      await apiClient.post(`/admin/registration-requests/${id}/reject`, { reason })
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-registration-requests'] })
      alert('تم رفض الطلب بنجاح.')
    },
    onError: () => alert('حدث خطأ أثناء الرفض.')
  })

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">طلبات التسجيل</h1>
        <p className="mt-0.5 text-sm text-slate-500">مراجعة طلبات إنشاء الحسابات الجديدة</p>
      </div>

      {isLoading ? (
        <div className="space-y-4">
          <Skeleton className="h-20 w-full" />
          <Skeleton className="h-20 w-full" />
          <Skeleton className="h-20 w-full" />
        </div>
      ) : isError ? (
        <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-center text-red-600">
          حدث خطأ أثناء جلب البيانات، يرجى المحاولة لاحقاً.
        </div>
      ) : !data || data.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white py-20 text-center">
          <ClipboardList className="mb-4 h-12 w-12 text-slate-300" />
          <h3 className="text-lg font-semibold text-slate-700">لا توجد طلبات</h3>
          <p className="mt-1 text-sm text-slate-500">لا توجد أي طلبات تسجيل قيد الانتظار حالياً.</p>
        </div>
      ) : (
        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
          <table className="w-full text-right text-sm">
            <thead className="bg-slate-50 text-slate-600">
              <tr>
                <th className="px-4 py-3 font-medium">الاسم</th>
                <th className="px-4 py-3 font-medium">الواتساب</th>
                <th className="px-4 py-3 font-medium">البريد الإلكتروني</th>
                <th className="px-4 py-3 font-medium">המزرعة</th>
                <th className="px-4 py-3 font-medium">الموقع</th>
                <th className="px-4 py-3 font-medium">تاريخ الطلب</th>
                <th className="px-4 py-3 text-center font-medium">الحالة</th>
                <th className="px-4 py-3 text-center font-medium">الإجراءات</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {data.map((req) => (
                <tr key={req.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3 font-medium text-slate-900">{req.name}</td>
                  <td className="px-4 py-3 text-slate-600" dir="ltr">{req.whatsapp}</td>
                  <td className="px-4 py-3 text-slate-500" dir="ltr">{req.email || '—'}</td>
                  <td className="px-4 py-3 text-slate-600">{req.farm_name || '—'}</td>
                  <td className="px-4 py-3 text-slate-600">{req.location || '—'}</td>
                  <td className="px-4 py-3 text-slate-500">
                    {new Date(req.created_at).toLocaleDateString('ar')}
                  </td>
                  <td className="px-4 py-3 text-center">
                    {req.status === 'pending' && (
                      <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-700">
                        <Clock className="h-3.5 w-3.5" />
                        قيد الانتظار
                      </span>
                    )}
                    {req.status === 'approved' && (
                      <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">
                        <CheckCircle2 className="h-3.5 w-3.5" />
                        مقبول
                      </span>
                    )}
                    {req.status === 'rejected' && (
                      <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700">
                        <XCircle className="h-3.5 w-3.5" />
                        مرفوض
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-center">
                    {req.status === 'pending' ? (
                      <div className="flex items-center justify-center gap-2">
                        <Button 
                          size="sm" 
                          variant="outline" 
                          className="h-8 border-emerald-600 text-emerald-700 hover:bg-emerald-50"
                          onClick={() => approveMutation.mutate(req.id)}
                          loading={approveMutation.isPending && approveMutation.variables === req.id}
                          disabled={approveMutation.isPending || rejectMutation.isPending}
                        >
                          قبول
                        </Button>
                        <Button 
                          size="sm" 
                          variant="outline" 
                          className="h-8 border-red-600 text-red-700 hover:bg-red-50"
                          onClick={() => {
                            const reason = prompt('أدخل سبب الرفض (اختياري):')
                            if (reason !== null) {
                              rejectMutation.mutate({ id: req.id, reason })
                            }
                          }}
                          loading={rejectMutation.isPending && rejectMutation.variables?.id === req.id}
                          disabled={approveMutation.isPending || rejectMutation.isPending}
                        >
                          رفض
                        </Button>
                      </div>
                    ) : (
                      <span className="text-slate-400">—</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}


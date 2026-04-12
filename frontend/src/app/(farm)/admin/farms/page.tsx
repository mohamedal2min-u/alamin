'use client'

import React, { useEffect, useState, useCallback, Fragment } from 'react'
import Link from 'next/link'
import { Building2, Plus, AlertCircle, Users, UserCog, Check, X } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { adminApi, type AdminFarm, type AdminUser } from '@/lib/api/admin'
import { formatDate } from '@/lib/utils'

const STATUS_LABELS: Record<AdminFarm['status'], string> = {
  active:        'نشطة',
  pending_setup: 'قيد الإعداد',
  suspended:     'موقوفة',
}

const STATUS_CLASSES: Record<AdminFarm['status'], string> = {
  active:        'bg-green-50 text-green-700',
  pending_setup: 'bg-amber-50 text-amber-700',
  suspended:     'bg-red-50 text-red-700',
}

export default function AdminFarmsPage() {
  const [farms, setFarms]       = useState<AdminFarm[]>([])
  const [users, setUsers]       = useState<AdminUser[]>([])
  const [loading, setLoading]   = useState(true)
  const [error, setError]       = useState<string | null>(null)

  // assign-admin state: which farm row is open + selected user
  const [assigningFarmId, setAssigningFarmId]   = useState<number | null>(null)
  const [selectedUserId, setSelectedUserId]     = useState<string>('')
  const [assignLoading, setAssignLoading]       = useState(false)
  const [assignError, setAssignError]           = useState<string | null>(null)

  const fetchData = useCallback(() => {
    setLoading(true)
    setError(null)
    Promise.all([adminApi.listFarms(), adminApi.listUsers()])
      .then(([farmsRes, usersRes]) => {
        setFarms(farmsRes.data)
        setUsers(usersRes.data)
      })
      .catch(() => setError('تعذّر تحميل البيانات'))
      .finally(() => setLoading(false))
  }, [])

  useEffect(() => { fetchData() }, [fetchData])

  const openAssign = (farmId: number, currentAdmin: AdminFarm['admin']) => {
    setAssigningFarmId(farmId)
    setSelectedUserId(currentAdmin ? String(currentAdmin.id) : '')
    setAssignError(null)
  }

  const cancelAssign = () => {
    setAssigningFarmId(null)
    setSelectedUserId('')
    setAssignError(null)
  }

  const confirmAssign = async (farmId: number) => {
    if (!selectedUserId) {
      setAssignError('اختر مستخدماً')
      return
    }
    setAssignLoading(true)
    setAssignError(null)
    try {
      const res = await adminApi.assignAdmin(farmId, Number(selectedUserId))
      setFarms((prev) =>
        prev.map((f) => (f.id === farmId ? { ...f, admin: res.data.admin } : f))
      )
      cancelAssign()
    } catch (err: unknown) {
      const axiosError = err as { response?: { data?: { message?: string } } }
      setAssignError(axiosError?.response?.data?.message ?? 'فشل تعيين المدير')
    } finally {
      setAssignLoading(false)
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">إدارة المداجن</h1>
          <p className="mt-0.5 text-sm text-slate-500">جميع المزارع المسجّلة في النظام</p>
        </div>
        <Button asChild>
          <Link href="/admin/farms/new">
            <Plus className="h-4 w-4" />
            مزرعة جديدة
          </Link>
        </Button>
      </div>

      {/* Loading */}
      {loading && (
        <div className="space-y-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <div key={i} className="h-16 animate-pulse rounded-xl bg-slate-100" />
          ))}
        </div>
      )}

      {/* Error */}
      {!loading && error && (
        <div className="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-5 text-red-700">
          <AlertCircle className="h-5 w-5 shrink-0" />
          <p className="text-sm">{error}</p>
        </div>
      )}

      {/* Empty */}
      {!loading && !error && farms.length === 0 && (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white py-20 text-center">
          <Building2 className="mb-4 h-12 w-12 text-slate-300" />
          <h3 className="text-lg font-semibold text-slate-700">لا توجد مداجن بعد</h3>
          <p className="mt-1 text-sm text-slate-500">ابدأ بإنشاء أول مزرعة في النظام</p>
          <Button asChild className="mt-5">
            <Link href="/admin/farms/new">
              <Plus className="h-4 w-4" />
              مزرعة جديدة
            </Link>
          </Button>
        </div>
      )}

      {/* Table */}
      {!loading && !error && farms.length > 0 && (
        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-100 bg-slate-50 text-right text-xs font-medium text-slate-500">
                <th className="px-5 py-3">اسم المزرعة</th>
                <th className="px-5 py-3">الموقع</th>
                <th className="px-5 py-3">تاريخ البدء</th>
                <th className="px-5 py-3">الأعضاء</th>
                <th className="px-5 py-3">مدير المزرعة</th>
                <th className="px-5 py-3">الحالة</th>
                <th className="px-5 py-3" />
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {farms.map((farm) => (
                <Fragment key={farm.id}>
                  {/* Main row */}
                  <tr className="transition hover:bg-slate-50">
                    <td className="px-5 py-3 font-medium text-slate-800">{farm.name}</td>
                    <td className="px-5 py-3 text-slate-500">{farm.location ?? '—'}</td>
                    <td className="px-5 py-3 text-slate-500">
                      {farm.started_at ? formatDate(farm.started_at) : '—'}
                    </td>
                    <td className="px-5 py-3">
                      <span className="flex items-center gap-1.5 text-slate-600">
                        <Users className="h-3.5 w-3.5" />
                        {farm.members_count}
                      </span>
                    </td>
                    <td className="px-5 py-3 text-slate-600">
                      {farm.admin?.name ?? (
                        <span className="text-slate-400 italic">غير محدد</span>
                      )}
                    </td>
                    <td className="px-5 py-3">
                      <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${STATUS_CLASSES[farm.status]}`}>
                        {STATUS_LABELS[farm.status]}
                      </span>
                    </td>
                    <td className="px-5 py-3">
                      <button
                        onClick={() => openAssign(farm.id, farm.admin)}
                        className="flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:border-violet-300 hover:text-violet-700"
                      >
                        <UserCog className="h-3.5 w-3.5" />
                        {farm.admin ? 'تغيير المدير' : 'تعيين مدير'}
                      </button>
                    </td>
                  </tr>

                  {/* Inline assign-admin row */}
                  {assigningFarmId === farm.id && (
                    <tr key={`assign-${farm.id}`} className="bg-violet-50">
                      <td colSpan={7} className="px-5 py-4">
                        <div className="flex items-start gap-3">
                          <div className="flex-1 space-y-2">
                            <p className="text-xs font-medium text-violet-700">
                              تعيين مدير لـ &quot;{farm.name}&quot;
                            </p>
                            <select
                              value={selectedUserId}
                              onChange={(e) => setSelectedUserId(e.target.value)}
                              className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                            >
                              <option value="">— اختر مستخدماً —</option>
                              {users.map((u) => (
                                <option key={u.id} value={u.id}>
                                  {u.name} {u.email ? `(${u.email})` : u.whatsapp ? `(${u.whatsapp})` : ''}
                                </option>
                              ))}
                            </select>
                            {assignError && (
                              <p className="text-xs text-red-600">{assignError}</p>
                            )}
                          </div>
                          <div className="flex gap-2 pt-6">
                            <button
                              onClick={() => confirmAssign(farm.id)}
                              disabled={assignLoading}
                              className="flex items-center gap-1 rounded-lg bg-violet-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-violet-700 disabled:opacity-50"
                            >
                              <Check className="h-3.5 w-3.5" />
                              تأكيد
                            </button>
                            <button
                              onClick={cancelAssign}
                              className="flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 transition hover:bg-slate-50"
                            >
                              <X className="h-3.5 w-3.5" />
                              إلغاء
                            </button>
                          </div>
                        </div>
                      </td>
                    </tr>
                  )}
                </Fragment>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

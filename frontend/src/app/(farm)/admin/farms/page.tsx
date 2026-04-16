'use client'

import React, { useEffect, useState, useCallback, Fragment } from 'react'
import Link from 'next/link'
import {
  Building2, Plus, AlertCircle, Users, UserCog,
  Check, X, Trash2, UserPlus, Eye, EyeOff,
} from 'lucide-react'
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

type ActivePanel =
  | { type: 'assign';  farmId: number }
  | { type: 'create';  farmId: number }
  | { type: 'delete';  farmId: number }
  | null

export default function AdminFarmsPage() {
  const [farms, setFarms]     = useState<AdminFarm[]>([])
  const [users, setUsers]     = useState<AdminUser[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState<string | null>(null)

  // One active panel per table (assign existing / create new / confirm delete)
  const [activePanel, setActivePanel] = useState<ActivePanel>(null)

  // ── Assign-existing state ────────────────────────────────────────────────
  const [selectedUserId, setSelectedUserId] = useState('')
  const [assignLoading, setAssignLoading]   = useState(false)
  const [assignError, setAssignError]       = useState<string | null>(null)

  // ── Create-new-manager state ─────────────────────────────────────────────
  const [newName, setNewName]         = useState('')
  const [newWhatsapp, setNewWhatsapp] = useState('')
  const [newEmail, setNewEmail]       = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [showPw, setShowPw]           = useState(false)
  const [createLoading, setCreateLoading] = useState(false)
  const [createError, setCreateError]     = useState<string | null>(null)

  // ── Delete state ─────────────────────────────────────────────────────────
  const [deleteLoading, setDeleteLoading] = useState(false)
  const [deleteError, setDeleteError]     = useState<string | null>(null)

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

  const closePanel = () => {
    setActivePanel(null)
    setSelectedUserId('')
    setAssignError(null)
    setNewName(''); setNewWhatsapp(''); setNewEmail(''); setNewPassword('')
    setCreateError(null)
    setDeleteError(null)
  }

  // ── Confirm assign existing user ─────────────────────────────────────────
  const confirmAssign = async (farmId: number) => {
    if (!selectedUserId) { setAssignError('اختر مستخدماً'); return }
    setAssignLoading(true)
    setAssignError(null)
    try {
      const res = await adminApi.assignAdmin(farmId, Number(selectedUserId))
      setFarms((prev) =>
        prev.map((f) => (f.id === farmId ? { ...f, admin: res.data.admin } : f))
      )
      closePanel()
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } }
      setAssignError(e?.response?.data?.message ?? 'فشل تعيين المدير')
    } finally {
      setAssignLoading(false)
    }
  }

  // ── Confirm create new manager ────────────────────────────────────────────
  const confirmCreate = async (farmId: number) => {
    if (!newName.trim())     { setCreateError('الاسم مطلوب'); return }
    if (!newWhatsapp.trim()) { setCreateError('رقم الواتساب مطلوب'); return }
    if (newPassword.length < 8) { setCreateError('كلمة المرور 8 أحرف على الأقل'); return }
    setCreateLoading(true)
    setCreateError(null)
    try {
      const res = await adminApi.createManager(farmId, {
        name:     newName.trim(),
        whatsapp: newWhatsapp.trim(),
        email:    newEmail.trim() || undefined,
        password: newPassword,
      })
      setFarms((prev) =>
        prev.map((f) => (f.id === farmId ? { ...f, admin: res.data.admin } : f))
      )
      closePanel()
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const first = e?.response?.data?.errors
        ? Object.values(e.response.data.errors)[0]?.[0]
        : null
      setCreateError(first ?? e?.response?.data?.message ?? 'فشل إنشاء الحساب')
    } finally {
      setCreateLoading(false)
    }
  }

  // ── Confirm delete farm ───────────────────────────────────────────────────
  const confirmDelete = async (farmId: number) => {
    setDeleteLoading(true)
    setDeleteError(null)
    try {
      await adminApi.deleteFarm(farmId)
      setFarms((prev) => prev.filter((f) => f.id !== farmId))
      closePanel()
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } }
      setDeleteError(e?.response?.data?.message ?? 'فشل حذف المزرعة')
    } finally {
      setDeleteLoading(false)
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100">إدارة المداجن</h1>
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
            <div key={i} className="h-16 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-700" />
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
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-20 text-center">
          <Building2 className="mb-4 h-12 w-12 text-slate-300" />
          <h3 className="text-lg font-semibold text-slate-700 dark:text-slate-200">لا توجد مداجن بعد</h3>
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
        <div className="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/50 text-right text-xs font-medium text-slate-500 dark:text-slate-400">
                <th className="px-5 py-3">اسم المزرعة</th>
                <th className="px-5 py-3">الموقع</th>
                <th className="px-5 py-3">تاريخ البدء</th>
                <th className="px-5 py-3">الأعضاء</th>
                <th className="px-5 py-3">مدير المزرعة</th>
                <th className="px-5 py-3">الحالة</th>
                <th className="px-5 py-3 text-center">إجراءات</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
              {farms.map((farm) => (
                <Fragment key={farm.id}>
                  {/* Main row */}
                  <tr className="transition hover:bg-slate-50 dark:hover:bg-slate-700/40">
                    <td className="px-5 py-3 font-medium text-slate-800 dark:text-slate-200">{farm.name}</td>
                    <td className="px-5 py-3 text-slate-500">{farm.location ?? '—'}</td>
                    <td className="px-5 py-3 text-slate-500">
                      {farm.started_at ? formatDate(farm.started_at) : '—'}
                    </td>
                    <td className="px-5 py-3">
                      <span className="flex items-center gap-1.5 text-slate-600 dark:text-slate-400">
                        <Users className="h-3.5 w-3.5" />
                        {farm.members_count}
                      </span>
                    </td>
                    <td className="px-5 py-3 text-slate-600 dark:text-slate-400">
                      {farm.admin?.name ?? (
                        <span className="text-slate-400 italic">غير محدد</span>
                      )}
                    </td>
                    <td className="px-5 py-3">
                      <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${STATUS_CLASSES[farm.status]}`}>
                        {STATUS_LABELS[farm.status]}
                      </span>
                    </td>
                    {/* Actions column */}
                    <td className="px-5 py-3">
                      <div className="flex items-center justify-center gap-1.5">
                        {/* Assign existing user */}
                        <button
                          onClick={() => {
                            closePanel()
                            setActivePanel({ type: 'assign', farmId: farm.id })
                            setSelectedUserId(farm.admin ? String(farm.admin.id) : '')
                          }}
                          title="تعيين مدير من المستخدمين"
                          className="flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-600 px-2.5 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300 transition hover:border-violet-300 hover:text-violet-700"
                        >
                          <UserCog className="h-3.5 w-3.5" />
                          {farm.admin ? 'تغيير' : 'تعيين'}
                        </button>

                        {/* Create new manager */}
                        <button
                          onClick={() => {
                            closePanel()
                            setActivePanel({ type: 'create', farmId: farm.id })
                          }}
                          title="إنشاء حساب مدير جديد"
                          className="flex items-center gap-1 rounded-lg border border-emerald-200 dark:border-emerald-700 px-2.5 py-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-400 transition hover:bg-emerald-50 dark:hover:bg-emerald-900/30"
                        >
                          <UserPlus className="h-3.5 w-3.5" />
                          مدير جديد
                        </button>

                        {/* Delete farm */}
                        <button
                          onClick={() => {
                            closePanel()
                            setActivePanel({ type: 'delete', farmId: farm.id })
                          }}
                          title="حذف المزرعة"
                          className="flex items-center gap-1 rounded-lg border border-red-200 dark:border-red-800 px-2.5 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 transition hover:bg-red-50 dark:hover:bg-red-900/30"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                          حذف
                        </button>
                      </div>
                    </td>
                  </tr>

                  {/* ── Panel: Assign existing user ── */}
                  {activePanel?.type === 'assign' && activePanel.farmId === farm.id && (
                    <tr className="bg-violet-50 dark:bg-violet-900/20">
                      <td colSpan={7} className="px-5 py-4">
                        <div className="flex items-start gap-3">
                          <div className="flex-1 space-y-2">
                            <p className="text-xs font-semibold text-violet-700 dark:text-violet-300">
                              تعيين مدير موجود لـ &quot;{farm.name}&quot;
                            </p>
                            <select
                              value={selectedUserId}
                              onChange={(e) => setSelectedUserId(e.target.value)}
                              className="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-2 text-sm text-slate-800 dark:text-slate-200 focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                            >
                              <option value="">— اختر مستخدماً —</option>
                              {users.map((u) => (
                                <option key={u.id} value={u.id}>
                                  {u.name} {u.email ? `(${u.email})` : u.whatsapp ? `(${u.whatsapp})` : ''}
                                </option>
                              ))}
                            </select>
                            {assignError && <p className="text-xs text-red-600">{assignError}</p>}
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
                              onClick={closePanel}
                              className="flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-2 text-xs font-medium text-slate-600 dark:text-slate-300 transition hover:bg-slate-50"
                            >
                              <X className="h-3.5 w-3.5" />
                              إلغاء
                            </button>
                          </div>
                        </div>
                      </td>
                    </tr>
                  )}

                  {/* ── Panel: Create new manager ── */}
                  {activePanel?.type === 'create' && activePanel.farmId === farm.id && (
                    <tr className="bg-emerald-50 dark:bg-emerald-900/20">
                      <td colSpan={7} className="px-5 py-4">
                        <p className="mb-3 text-xs font-semibold text-emerald-800 dark:text-emerald-300">
                          إنشاء حساب مدير جديد لـ &quot;{farm.name}&quot;
                        </p>
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                          <div className="flex flex-col gap-1">
                            <label className="text-[10px] font-bold text-slate-500 uppercase">الاسم *</label>
                            <input
                              value={newName}
                              onChange={(e) => setNewName(e.target.value)}
                              placeholder="الاسم الكامل"
                              className="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-2 text-sm text-slate-800 dark:text-slate-200 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                            />
                          </div>
                          <div className="flex flex-col gap-1">
                            <label className="text-[10px] font-bold text-slate-500 uppercase">واتساب *</label>
                            <input
                              value={newWhatsapp}
                              onChange={(e) => setNewWhatsapp(e.target.value)}
                              placeholder="05xxxxxxxx"
                              dir="ltr"
                              className="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-2 text-sm text-slate-800 dark:text-slate-200 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                            />
                          </div>
                          <div className="flex flex-col gap-1">
                            <label className="text-[10px] font-bold text-slate-500 uppercase">البريد (اختياري)</label>
                            <input
                              value={newEmail}
                              onChange={(e) => setNewEmail(e.target.value)}
                              placeholder="example@mail.com"
                              type="email"
                              dir="ltr"
                              className="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-2 text-sm text-slate-800 dark:text-slate-200 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                            />
                          </div>
                          <div className="flex flex-col gap-1">
                            <label className="text-[10px] font-bold text-slate-500 uppercase">كلمة المرور *</label>
                            <div className="relative">
                              <input
                                value={newPassword}
                                onChange={(e) => setNewPassword(e.target.value)}
                                type={showPw ? 'text' : 'password'}
                                placeholder="8 أحرف+"
                                dir="ltr"
                                className="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-2 text-sm text-slate-800 dark:text-slate-200 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 pe-8"
                              />
                              <button
                                type="button"
                                onClick={() => setShowPw((v) => !v)}
                                className="absolute left-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                              >
                                {showPw ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                              </button>
                            </div>
                          </div>
                        </div>
                        {createError && (
                          <p className="mt-2 text-xs text-red-600">{createError}</p>
                        )}
                        <div className="mt-3 flex gap-2">
                          <button
                            onClick={() => confirmCreate(farm.id)}
                            disabled={createLoading}
                            className="flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-emerald-700 disabled:opacity-50"
                          >
                            <Check className="h-3.5 w-3.5" />
                            {createLoading ? 'جارٍ الإنشاء...' : 'إنشاء الحساب'}
                          </button>
                          <button
                            onClick={closePanel}
                            className="flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-2 text-xs font-medium text-slate-600 dark:text-slate-300 transition hover:bg-slate-50"
                          >
                            <X className="h-3.5 w-3.5" />
                            إلغاء
                          </button>
                        </div>
                      </td>
                    </tr>
                  )}

                  {/* ── Panel: Confirm delete ── */}
                  {activePanel?.type === 'delete' && activePanel.farmId === farm.id && (
                    <tr className="bg-red-50 dark:bg-red-900/20">
                      <td colSpan={7} className="px-5 py-4">
                        <div className="flex items-center gap-4">
                          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/50">
                            <Trash2 className="h-5 w-5 text-red-600" />
                          </div>
                          <div className="flex-1">
                            <p className="text-sm font-bold text-red-800 dark:text-red-300">
                              هل أنت متأكد من حذف &quot;{farm.name}&quot;؟
                            </p>
                            <p className="text-xs text-red-600 dark:text-red-400 mt-0.5">
                              سيتم حذف المزرعة وجميع بياناتها بشكل نهائي ولا يمكن التراجع عن هذا الإجراء.
                            </p>
                            {deleteError && <p className="mt-1 text-xs text-red-700">{deleteError}</p>}
                          </div>
                          <div className="flex gap-2 shrink-0">
                            <button
                              onClick={() => confirmDelete(farm.id)}
                              disabled={deleteLoading}
                              className="flex items-center gap-1.5 rounded-lg bg-red-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-red-700 disabled:opacity-50"
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                              {deleteLoading ? 'جارٍ الحذف...' : 'تأكيد الحذف'}
                            </button>
                            <button
                              onClick={closePanel}
                              className="flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-2 text-xs font-medium text-slate-600 dark:text-slate-300 transition hover:bg-slate-50"
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

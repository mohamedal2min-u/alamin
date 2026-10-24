'use client'

import { useEffect, useState } from 'react'
import { Plus, Trash2, Save, RefreshCcw, Syringe, AlertTriangle } from 'lucide-react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { vaccinationsApi, vaccineKeys } from '@/lib/api/vaccinations'
import { useFarmStore } from '@/stores/farm.store'
import { useLayoutStore } from '@/stores/layout.store'
import { useCurrentRole } from '@/lib/roles'
import { toEnglishDigits } from '@/lib/utils'
import { VACCINE_METHOD_LABELS, type VaccineMethod, type VaccineTemplate } from '@/types/vaccination'

interface Row {
  key: number
  age_day: string
  title: string
  method: VaccineMethod
  withdrawal_days: string
  notes: string
}

let nextKey = 1
const toRows = (t: VaccineTemplate): Row[] =>
  t.items.map((i) => ({
    key: nextKey++,
    age_day: String(i.age_day),
    title: i.title,
    method: i.method,
    withdrawal_days: i.withdrawal_days != null ? String(i.withdrawal_days) : '',
    notes: i.notes ?? '',
  }))

const inputCls =
  'w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-[13px] text-slate-800 focus:border-primary-400 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100'

const errMsg = (e: unknown, fallback: string) =>
  (e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? fallback

export default function VaccinesPage() {
  const farmId = useFarmStore((s) => s.currentFarm?.id)
  const role = useCurrentRole()
  const canEdit = role === 'farm_admin' || role === 'super_admin'
  const { setPageTitle, setPageSubtitle } = useLayoutStore()
  const queryClient = useQueryClient()

  const [name, setName] = useState('')
  const [rows, setRows] = useState<Row[]>([])
  const [loadedId, setLoadedId] = useState<number | null>(null)
  const [saving, setSaving] = useState(false)
  const [applying, setApplying] = useState(false)
  const [dirty, setDirty] = useState(false)

  useEffect(() => {
    setPageTitle('برنامج اللقاحات')
    setPageSubtitle(null)
  }, [setPageTitle, setPageSubtitle])

  const { data: template, isLoading, isError } = useQuery({
    queryKey: vaccineKeys.template(farmId),
    queryFn: vaccinationsApi.template,
    enabled: !!farmId,
    staleTime: 5 * 60 * 1000,
  })

  // تعبئة النموذج عند أول تحميل أو تبديل المزرعة
  if (template && template.id !== loadedId) {
    setLoadedId(template.id)
    setName(template.name)
    setRows(toRows(template))
    setDirty(false)
  }

  const update = (key: number, patch: Partial<Row>) => {
    setRows((rs) => rs.map((r) => (r.key === key ? { ...r, ...patch } : r)))
    setDirty(true)
  }

  const addRow = () => {
    const last = rows[rows.length - 1]
    setRows((rs) => [...rs, {
      key: nextKey++, age_day: last ? String(Number(last.age_day || 0) + 7) : '1',
      title: '', method: 'drinking_water', withdrawal_days: '', notes: '',
    }])
    setDirty(true)
  }

  const save = async () => {
    if (rows.some((r) => !r.title.trim() || !(Number(r.age_day) >= 1))) {
      toast.error('أكمل اسم اللقاح ويوم العمر لكل سطر')
      return
    }
    setSaving(true)
    try {
      const res = await vaccinationsApi.saveTemplate({
        name: name.trim() || 'برنامج اللقاحات',
        items: rows.map((r) => ({
          age_day: Number(r.age_day),
          title: r.title.trim(),
          method: r.method,
          withdrawal_days: r.withdrawal_days === '' ? null : Number(r.withdrawal_days),
          notes: r.notes.trim() || null,
        })),
      })
      toast.success(res.message)
      queryClient.setQueryData(vaccineKeys.template(farmId), res.data)
      setName(res.data.name)
      setRows(toRows(res.data))
      setDirty(false)
    } catch (e) {
      toast.error(errMsg(e, 'تعذّر حفظ البرنامج'))
    } finally {
      setSaving(false)
    }
  }

  const applyToActive = async () => {
    if (!window.confirm('سيُعاد توليد اللقاحات «المستحقة» للفوج الحالي من هذا البرنامج. المنفَّذ والمتخطّى يبقى كما هو. متابعة؟')) return
    setApplying(true)
    try {
      const res = await vaccinationsApi.applyToActive()
      toast.success(res.message)
      queryClient.invalidateQueries({ queryKey: vaccineKeys.all(farmId) })
    } catch (e) {
      toast.error(errMsg(e, 'تعذّر التطبيق على الفوج الحالي'))
    } finally {
      setApplying(false)
    }
  }

  if (isLoading) {
    return <div className="py-16 text-center text-sm text-slate-400">جارٍ التحميل...</div>
  }
  if (isError || !template) {
    return <div className="py-16 text-center text-sm text-red-600">تعذّر تحميل برنامج اللقاحات</div>
  }

  return (
    <div className="space-y-4 px-1 pt-3 pb-10" dir="rtl">
      <div className="flex items-center gap-2">
        <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 dark:bg-sky-900/30">
          <Syringe className="h-5 w-5 text-sky-600" />
        </div>
        <div>
          <h1 className="text-lg font-extrabold text-slate-900 dark:text-slate-100">برنامج اللقاحات</h1>
          <p className="text-[11px] text-slate-500 dark:text-slate-400">يُطبَّق تلقائياً على كل فوج جديد عند تفعيله</p>
        </div>
      </div>

      {template.is_example && !dirty && (
        <div className="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-[12px] font-bold text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300">
          <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
          مثال — عدّله مع الطبيب البيطري. هذا برنامج شائع للاحم وليس توصية طبية.
        </div>
      )}

      <div>
        <label className="mb-1 block text-xs font-bold text-slate-600 dark:text-slate-300">اسم البرنامج</label>
        <input
          value={name}
          onChange={(e) => { setName(e.target.value); setDirty(true) }}
          disabled={!canEdit}
          maxLength={150}
          className={inputCls}
        />
      </div>

      <ul className="space-y-2.5">
        {rows.map((r) => (
          <li key={r.key} className="rounded-xl border border-slate-100 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div className="grid grid-cols-[64px_1fr_auto] items-end gap-2">
              <div>
                <label className="mb-1 block text-[10px] font-bold text-slate-400">يوم العمر</label>
                <input
                  type="number" inputMode="numeric" min={1} max={120}
                  value={r.age_day}
                  onChange={(e) => update(r.key, { age_day: toEnglishDigits(e.target.value) })}
                  disabled={!canEdit}
                  className={inputCls}
                />
              </div>
              <div className="min-w-0">
                <label className="mb-1 block text-[10px] font-bold text-slate-400">اللقاح</label>
                <input
                  value={r.title}
                  onChange={(e) => update(r.key, { title: e.target.value })}
                  placeholder="مثال: نيوكاسل + برونشيت"
                  maxLength={150}
                  disabled={!canEdit}
                  className={inputCls}
                />
              </div>
              {canEdit && (
                <button
                  onClick={() => { setRows((rs) => rs.filter((x) => x.key !== r.key)); setDirty(true) }}
                  className="mb-0.5 rounded-lg p-2 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/20"
                  aria-label="حذف"
                >
                  <Trash2 className="h-4 w-4" />
                </button>
              )}
            </div>
            <div className="mt-2 grid grid-cols-2 gap-2">
              <div>
                <label className="mb-1 block text-[10px] font-bold text-slate-400">الطريقة</label>
                <select
                  value={r.method}
                  onChange={(e) => update(r.key, { method: e.target.value as VaccineMethod })}
                  disabled={!canEdit}
                  className={inputCls}
                >
                  {(Object.keys(VACCINE_METHOD_LABELS) as VaccineMethod[]).map((m) => (
                    <option key={m} value={m}>{VACCINE_METHOD_LABELS[m]}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="mb-1 block text-[10px] font-bold text-slate-400">فترة السحب (أيام)</label>
                <input
                  type="number" inputMode="numeric" min={0} max={365}
                  value={r.withdrawal_days}
                  onChange={(e) => update(r.key, { withdrawal_days: toEnglishDigits(e.target.value) })}
                  placeholder="لا يوجد"
                  disabled={!canEdit}
                  className={inputCls}
                />
              </div>
            </div>
            <input
              value={r.notes}
              onChange={(e) => update(r.key, { notes: e.target.value })}
              placeholder="ملاحظات (اختياري)"
              maxLength={1000}
              disabled={!canEdit}
              className={`${inputCls} mt-2`}
            />
          </li>
        ))}
      </ul>

      {rows.length === 0 && (
        <p className="py-6 text-center text-sm text-slate-400">لا لقاحات في البرنامج</p>
      )}

      {canEdit && (
        <div className="space-y-2.5">
          <button
            onClick={addRow}
            className="flex w-full items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-slate-200 py-2.5 text-sm font-bold text-slate-500 active:scale-[0.99] dark:border-slate-600 dark:text-slate-300"
          >
            <Plus className="h-4 w-4" /> إضافة لقاح
          </button>

          <button
            onClick={save}
            disabled={saving || !dirty}
            className="flex w-full items-center justify-center gap-1.5 rounded-xl bg-primary-600 py-3 text-sm font-bold text-white active:scale-[0.99] disabled:opacity-50"
          >
            <Save className="h-4 w-4" /> {saving ? 'جارٍ الحفظ...' : 'حفظ البرنامج'}
          </button>

          <button
            onClick={applyToActive}
            disabled={applying || dirty}
            className="flex w-full items-center justify-center gap-1.5 rounded-xl border border-sky-200 bg-sky-50 py-3 text-sm font-bold text-sky-700 active:scale-[0.99] disabled:opacity-50 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-300"
          >
            <RefreshCcw className="h-4 w-4" /> {applying ? 'جارٍ التطبيق...' : 'تطبيق على الفوج الحالي'}
          </button>
          <p className="text-center text-[11px] text-slate-400">
            حفظ البرنامج لا يغيّر الفوج الحالي. «تطبيق على الفوج الحالي» يعيد توليد المستحق فقط.
          </p>
        </div>
      )}
    </div>
  )
}

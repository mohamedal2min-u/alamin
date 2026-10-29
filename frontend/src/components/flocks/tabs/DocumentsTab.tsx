'use client'

import { useRef, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { FileImage, Upload } from 'lucide-react'
import { toast } from 'sonner'
import { flockDocumentsApi, type FlockDocument } from '@/lib/api/flockDocuments'
import { useCurrentRole } from '@/lib/roles'
import { Button } from '@/components/ui/Button'
import { formatDate } from '@/lib/utils'

interface Props {
  flockId: number
}

// مستندات الفوج: صور أوراق الحساب والفواتير — للرجوع إليها عند المطابقة
export function DocumentsTab({ flockId }: Props) {
  const queryClient = useQueryClient()
  const role = useCurrentRole()
  const canUpload = role === 'super_admin' || role === 'farm_admin' || role === 'supervisor' || role === 'accountant'
  const fileRef = useRef<HTMLInputElement>(null)
  const [title, setTitle] = useState('')
  const [uploading, setUploading] = useState(false)

  const { data: docs = [], isLoading } = useQuery<FlockDocument[]>({
    queryKey: ['flock-documents', flockId],
    queryFn: () => flockDocumentsApi.list(flockId).then((r) => r.data),
    staleTime: 30_000,
  })

  const onFile = async (file: File | undefined) => {
    if (!file) return
    setUploading(true)
    try {
      await flockDocumentsApi.upload(flockId, file, title.trim() || undefined)
      toast.success('تم رفع المستند')
      setTitle('')
      queryClient.invalidateQueries({ queryKey: ['flock-documents', flockId] })
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      toast.error(msg ?? 'تعذر رفع المستند')
    } finally {
      setUploading(false)
      if (fileRef.current) fileRef.current.value = ''
    }
  }

  return (
    <div className="space-y-4 p-4">
      {canUpload && (
        <div className="flex flex-col gap-2 sm:flex-row">
          <input
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="اسم المستند (مثلاً: ورقة الحساب)"
            className="flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900"
          />
          <input
            ref={fileRef}
            type="file"
            accept="image/*"
            className="hidden"
            onChange={(e) => onFile(e.target.files?.[0])}
          />
          <Button onClick={() => fileRef.current?.click()} disabled={uploading}>
            <Upload className="h-4 w-4" />
            {uploading ? 'جارٍ الرفع…' : 'رفع صورة'}
          </Button>
        </div>
      )}

      {isLoading ? (
        <p className="text-sm text-slate-500">جارٍ التحميل…</p>
      ) : docs.length === 0 ? (
        <div className="flex flex-col items-center gap-2 py-10 text-slate-400">
          <FileImage className="h-8 w-8" />
          <p className="text-sm">لا توجد مستندات</p>
        </div>
      ) : (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
          {docs.map((d) => (
            <a
              key={d.id}
              href={d.url}
              target="_blank"
              rel="noreferrer"
              className="overflow-hidden rounded-lg border border-slate-200 hover:border-primary-400 dark:border-slate-700"
            >
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={d.url} alt={d.title ?? 'مستند'} className="aspect-[3/4] w-full object-cover" />
              <div className="p-2 text-xs">
                <p className="font-medium text-slate-700 dark:text-slate-200">{d.title ?? 'مستند'}</p>
                {d.created_at && <p className="text-slate-400">{formatDate(d.created_at)}</p>}
              </div>
            </a>
          ))}
        </div>
      )}
    </div>
  )
}

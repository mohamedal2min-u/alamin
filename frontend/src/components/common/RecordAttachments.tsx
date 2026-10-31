'use client'

import { useRef, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Paperclip } from 'lucide-react'
import { toast } from 'sonner'
import { flockDocumentsApi, type FlockDocument, type RecordType } from '@/lib/api/flockDocuments'
import { useCurrentRole } from '@/lib/roles'

interface Props {
  flockId: number
  recordType: RecordType
  recordId: number
}

// صور الفاتورة المحفوظة مع القيد نفسه: تظهر عند فتح القيد، مع زر لإرفاق صورة
export function RecordAttachments({ flockId, recordType, recordId }: Props) {
  const queryClient = useQueryClient()
  const role = useCurrentRole()
  const canUpload = role === 'super_admin' || role === 'farm_admin' || role === 'supervisor' || role === 'accountant'
  const fileRef = useRef<HTMLInputElement>(null)
  const [uploading, setUploading] = useState(false)
  const record = { type: recordType, id: recordId }
  const key = ['flock-documents', flockId, recordType, recordId]

  const { data: docs = [] } = useQuery<FlockDocument[]>({
    queryKey: key,
    queryFn: () => flockDocumentsApi.list(flockId, record).then((r) => r.data),
    staleTime: 30_000,
  })

  const onFile = async (file: File | undefined) => {
    if (!file) return
    setUploading(true)
    try {
      await flockDocumentsApi.upload(flockId, file, 'فاتورة', record)
      toast.success('تم حفظ الفاتورة مع القيد')
      queryClient.invalidateQueries({ queryKey: ['flock-documents', flockId] })
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      toast.error(msg ?? 'تعذر رفع الصورة')
    } finally {
      setUploading(false)
      if (fileRef.current) fileRef.current.value = ''
    }
  }

  if (docs.length === 0 && !canUpload) return null

  return (
    <div className="flex flex-wrap items-center gap-2" onClick={(e) => e.stopPropagation()}>
      {docs.map((d) => (
        <a key={d.id} href={d.url} target="_blank" rel="noreferrer" title={d.title ?? 'فاتورة'}
          className="block h-16 w-12 overflow-hidden rounded-lg border border-slate-200 hover:border-primary-400 dark:border-slate-600">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={d.url} alt={d.title ?? 'فاتورة'} className="h-full w-full object-cover" />
        </a>
      ))}
      {canUpload && (
        <>
          <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={(e) => onFile(e.target.files?.[0])} />
          <button
            type="button"
            onClick={() => fileRef.current?.click()}
            disabled={uploading}
            className="inline-flex items-center gap-1 rounded-lg border border-dashed border-slate-300 px-2.5 py-1.5 text-xs font-bold text-slate-600 hover:border-primary-400 disabled:opacity-60 dark:border-slate-600 dark:text-slate-300"
          >
            <Paperclip className="h-3.5 w-3.5" />
            {uploading ? 'جارٍ الرفع…' : docs.length ? 'صورة أخرى' : 'إرفاق صورة الفاتورة'}
          </button>
        </>
      )}
    </div>
  )
}

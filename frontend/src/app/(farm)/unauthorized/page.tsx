// frontend/src/app/(farm)/unauthorized/page.tsx
import Link from 'next/link'

export default function UnauthorizedPage() {
  return (
    <div className="flex flex-col items-center justify-center py-24 text-center">
      <div className="mb-4 text-6xl">🚫</div>
      <h1 className="mb-2 text-2xl font-bold text-slate-900">غير مصرح لك بالوصول</h1>
      <p className="mb-8 max-w-sm text-sm text-slate-500">
        ليس لديك صلاحية لعرض هذه الصفحة. إذا كنت تعتقد أن هذا خطأ،
        يرجى التواصل مع مدير المزرعة.
      </p>
      <Link
        href="/flocks"
        className="rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700"
      >
        العودة للأفواج
      </Link>
    </div>
  )
}

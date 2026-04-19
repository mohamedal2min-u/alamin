'use client'

import React from 'react'
import Link from 'next/link'
import { ShieldCheck, ArrowRight } from 'lucide-react'

export default function PrivacyPolicyPage() {
  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-900 py-12 px-4 sm:px-6 lg:px-8 font-['Cairo']" dir="rtl">
      <div className="max-w-3xl mx-auto bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        {/* Header */}
        <div className="bg-slate-900 py-8 px-6 text-center border-b border-white/5">
          <div className="inline-flex items-center justify-center p-3 bg-blue-500/10 rounded-xl mb-4">
            <ShieldCheck className="w-8 h-8 text-blue-500" />
          </div>
          <h1 className="text-2xl font-bold text-white mb-2">سياسة الخصوصية</h1>
          <p className="text-slate-400 text-sm">تطبيق YMD - نظام إدارة مزارع الدواجن</p>
        </div>

        {/* Content */}
        <div className="p-8 sm:p-10 space-y-8 text-slate-700 dark:text-slate-300 leading-relaxed text-right">
          <section>
            <h2 className="text-xl font-bold text-slate-900 dark:text-white mb-4">تمهيد</h2>
            <p>
              يهدف تطبيق **YMD** (نظام إدارة مزارع الدواجن) إلى مساعدة أصحاب المزارع والعمال في إدارة العمليات اليومية والمحاسبة. نحن نلتزم بحماية بياناتك وخصوصيتك ونعتز بثقتك بنا.
            </p>
          </section>

          <section>
            <h2 className="text-xl font-bold text-slate-900 dark:text-white mb-4">1. المعلومات التي نجمعها</h2>
            <p className="mb-3">نحن نجمع الحد الأدنى من البيانات اللازمة لتشغيل الخدمة بكفاءة:</p>
            <ul className="list-disc list-inside space-y-2 mr-4">
              <li><strong>معلومات الحساب:</strong> اسم المستخدم، رقم الهاتف (للتوثيق)، وصورة البروفايل إن وجدت.</li>
              <li><strong>بيانات المزرعة:</strong> البيانات الإنتاجية التي تدخلها (الأفواج، الأعلاف، الأدوية، والعمليات المالية).</li>
              <li><strong>بيانات الموقع:</strong> قد نطلب الوصول للموقع فقط لمرة واحدة عند تسجيل موقع المزرعة أو للتحقق من الحضور في حال فعلت المزرعة هذه الميزة.</li>
            </ul>
          </section>

          <section>
            <h2 className="text-xl font-bold text-slate-900 dark:text-white mb-4">2. كيف نستخدم بياناتك</h2>
            <p>
              تستخدم البيانات حصرياً داخل النظام لتقديم التقارير المحاسبية والإحصائية الخاصة بمزرعتك، ولتسهيل التواصل والعمل الجماعي بين أفراد الفريق المصرح لهم فقط.
            </p>
          </section>

          <section>
            <h2 className="text-xl font-bold text-slate-900 dark:text-white mb-4">3. حماية البيانات ومشاركتها</h2>
            <p>
              **بياناتك مقدسة:** نحن لا نشارك أي بيانات خاصة بك أو بمزرعتك مع أي جهات خارجية أو معلنين. يتم تخزين البيانات على خوادم محمية ومشفرة، ويتم الوصول إليها فقط من خلال حسابات معتمدة بكلمات مرور مشفرة.
            </p>
          </section>

          <section>
            <h2 className="text-xl font-bold text-slate-900 dark:text-white mb-4">4. حقوق المستخدم</h2>
            <p>
              تملك كامل الحق في الوصول إلى بياناتك، تعديلها، أو حذفها في أي وقت. يمكنك إغلاق حسابك وطلب حذف كافة البيانات المرتبطة به عبر التواصل معنا.
            </p>
          </section>

          <section className="bg-slate-50 dark:bg-slate-900/50 p-6 rounded-xl border border-slate-100 dark:border-slate-700/50">
            <h2 className="text-xl font-bold text-slate-900 dark:text-white mb-4">5. التواصل معنا</h2>
            <p className="mb-2">لأية استفسارات بخصوص الخصوصية أو البيانات، يرجى التواصل عبر:</p>
            <div className="space-y-1">
              <p>البريد الإلكتروني: <a href="mailto:webart@alamin.se" className="text-blue-500 hover:underline">webart@alamin.se</a></p>
              <p>الموقع الرسمي: <a href="https://ch.alamin.se" target="_blank" className="text-blue-500 hover:underline">ch.alamin.se</a></p>
            </div>
          </section>

          <div className="pt-8 border-t border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row justify-between items-center gap-4 text-sm text-slate-400">
            <p>تاريخ التحديث: 19 أبريل 2026</p>
            <Link href="/login" className="flex items-center gap-2 text-blue-500 hover:text-blue-600 transition-colors font-semibold">
              <ArrowRight className="w-4 h-4 ml-1" />
              العودة لتسجيل الدخول
            </Link>
          </div>
        </div>
      </div>
    </div>
  )
}

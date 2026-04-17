# سجل التغييرات (Change Log)

## [2026-04-17] - تحسينات الأداء الشاملة (Performance Optimization)

### المشاكل المُعالجة
- **Worker Dashboard**: polling كل 10 ثوانٍ على قراتين = 18 طلب/دقيقة/مستخدم — حُذف polling بالكامل
- **`/flocks/[id]`**: كان يستخدم `useEffect + state` (لا cache، يُعيد الجلب في كل زيارة) — تم التحويل إلى `useQuery`
- **`/workers`**: نفس المشكلة — تم التحويل إلى `useQuery`
- **`/sales`**: نفس المشكلة — تم التحويل إلى `useQuery`

### التغييرات
#### Worker Dashboard (`worker/page.tsx`)
- حُذف `refetchInterval` من جميع القراءات الثلاث (كان 10s/10s/30s)
- رُفع `staleTime` إلى 60s على الجميع
- أُضيف `placeholderData: keepPreviousData` لـ history

#### Flock Details (`flocks/[id]/page.tsx`)
- تحويل من `useEffect + setState` إلى `useQuery(['flock', flockId])`
- staleTime: 60s — البيانات مُخزّنة بعد أول زيارة
- `queryClient.setQueryData` بدلاً من `setFlock` في onSuccess callbacks

#### Workers Page (`workers/page.tsx`)
- تحويل إلى `useQuery(['workers', currentFarm?.id])`
- حذف optimistic state بعد delete، استبداله بـ `queryClient.setQueryData`

#### Sales Page (`sales/page.tsx`)
- تحويل إلى `useQuery(['sales', currentFarm?.id])`
- `queryClient.setQueryData` بعد تحديث الدفع بدلاً من setSales

### النتائج
- Frontend: `npx tsc --noEmit` → 0 أخطاء ✅
- طلبات الشبكة: تقلّصت من ~18 طلب/دقيقة إلى 0 polling (تحديث فقط عند العودة للتبويب)


## [2026-04-17] - إصلاح تبويب المياه + إضافة إنشاء المصاريف

### الإصلاحات
- **Bug Fix**: تبويب "المياه" في صفحة تفاصيل الفوج كان يعرض `ExpensesTab` بدلاً من `WaterTab` — تم الإصلاح بفصل التبويبين
- أُضيف تبويب "المياه" (`WaterTab`) كتبويب مستقل في صفحة الفوج
- تبويب "المصروفات" أصبح له مفتاح `expenses` بدلاً من `water`

### الإضافات

#### Backend (API)
- `GET  /api/expense-categories` — قائمة تصنيفات المصاريف (النظامية + خاصة المزرعة)
- `POST /api/expenses` — إنشاء مصروف مستقل (بدون ربط إلزامي بفوج)
  - يدعم: `expense_category_id`, `entry_date`, `total_amount`, `payment_status`, `flock_id` (اختياري)

#### Frontend
- صفحة المصاريف `/expenses`: أُضيف زر "إضافة مصروف" + نموذج inline لإنشاء مصروف جديد مع اختيار التصنيف وحالة الدفع
- API client `expenses.ts`: أُضيفت `expensesApi.create()` و `expensesApi.categories()`

### النتائج
- Backend: 184 اختبار ✅
- Frontend: `npx tsc --noEmit` → 0 أخطاء ✅

## [2026-04-15] - تكملة تبويبات تفاصيل الفوج: المياه والملاحظات

### الإضافات

#### Backend (API)
- **FlockWaterLog Module**: Action (List + Create) + Resource + Controller + FormRequest + Routes
  - `GET  /api/flocks/{flock}/water-logs` — قائمة سجلات المياه
  - `POST /api/flocks/{flock}/water-logs` — تسجيل استهلاك مياه
  - الكمية اختيارية (يمكن تسجيل يوم بدون كمية)
  - التحقق: الفوج يجب أن يكون نشطاً
- **FlockNote Module**: Action (List + Create) + Resource + Controller + FormRequest + Routes
  - `GET  /api/flocks/{flock}/notes` — قائمة ملاحظات الفوج
  - `POST /api/flocks/{flock}/notes` — إضافة ملاحظة جديدة
  - أنواع الملاحظات: general, instruction, operational, alert
  - التحقق: الفوج يجب أن يكون نشطاً، نص الملاحظة مطلوب
- **Factories**: FlockWaterLogFactory + FlockNoteFactory
- **Tests**: 19 اختباراً جديداً (WaterLogCreateTest + WaterLogListTest + FlockNoteCreateTest + FlockNoteListTest)

#### Frontend
- **Types**: `waterLog.ts` + `flockNote.ts`
- **API clients**: `waterLogs.ts` + `flockNotes.ts`
- **WaterTab component**: تبويب المياه الكامل (inline form, quantity optional, unit_label)
- **NotesTab component**: تبويب الملاحظات الكامل (inline form, note_type selector, textarea)
- **Wire-up**: ربط التبويبين في صفحة تفاصيل الفوج `/flocks/[id]` — لم يتبق أي `TabPlaceholder`

### النتائج
- Backend: 184 اختبار ✅ (0 فشل، 0 خطأ)
- Frontend: `npx tsc --noEmit` → 0 أخطاء ✅


## [2026-04-12] - التحسينات البصرية الموحدة (UI/UX Refinement)

### التحسينات العامة
- **نظام التصميم (Design System)**: توحيد الانحناءات (`rounded-2xl`)، وظلال البطاقات (`--shadow-card`، `--shadow-float`، `--shadow-raised`).
- **الهيكل الرئيسي**: توحيد ارتفاع `Header`، وإصلاح أبعاد `BottomNav` للأجهزة المحمولة، وتحسين القائمة الجانبية بإضافة اللوجو والبراند (دجاجتي).
- **مكونات الواجهة المشتركة**: تحديث مكونات `Card` و `Input` و `Tabs` لتتطابق مع الستايل الجديد.
- **إصلاحات TypeScript**: التخلص المبرم من جميع مشاكل `Badge variants` و `Suspense` و `null safety` لتطابق نسخة الإنتاج (Production Ready)، وتحقيق 0 أخطاء بناء.

### تحسينات الصفحات والمكونات
- **شاشة الدخول**: إصلاح خطأ SSR بإضافة `Suspense wrapper`، تغيير اللوجو والاسم لكي يتطابق مع هوية "دجاجتي".
- **شاشة المخزون**: إزالة الهيدر المزدوج، توحيد هيكل البطاقات ونماذج الإضافة (Items/Shipments) ضمن مسافات منتظمة (`space-y-5`).
- **لوحة المعلومات**: تبسيط بطاقة ملخص القطيع `FlockSummaryCard` وتقليل التشتت البصري (إزالة الزخارف الزائدة).
- **شاشة التقارير**: إصلاح الهيكل التحميلي `skeleton loading`، تعديل رابط الـ API للبحث التلقائي في `FiltersBar`.
- **توحيد العملة (Global Currency)**: استبدال جميع مسميات العملة (ر.س) المتبقية في شاشات لوحة المتابعة (DaySummaryCard, QuickEntryCard)، المخزون (Inventory)، المصروفات (Expenses)، والتقارير (Reports) لتكون بشكل ثابت بالدولار (USD).


## [2026-04-11] - نظام إدارة الشركاء (التحديث الاحترافي)

### الإضافات
- **نظام إدارة الشركاء (CRUD)**: واجهة كاملة لإدارة الشركاء في المزرعة.
- **الربط التلقائي بالمستخدمين**: عند إضافة شريك، يتم إنشاء حساب مستخدم (`User`) له تلقائياً بصلاحية `partner`.
- **معرف الدخول**: استخدام رقم الواتساب كمعرف أساسي للدخول مع دعم كلمات سر مخصصة أو افتراضية.
- **قواعد توزيع الحصص (100% Invariant)**:
    - الأدمن شريك تلقائي بنسبة 100% عند البداية.
    - خصم حصص الشركاء الجدد من حصة الأدمن تلقائياً.
    - استرداد الحصص للأدمن عند تعطيل أو حذف الشركاء.
    - منع أي عملية تؤدي لكسر قاعدة (المجموع = 100%) أو تجعل حصة الأدمن سالبة.
- **تبويب تقرير الشركاء**: ربط البيانات المالية (الحصص والحركات) بتقرير الشركاء في وحدة التقارير.

### التحسينات
- تحسين تجربة المستخدم في نموذج الإضافة بإضافة رسائل إرشادية حول الحصص والحسابات.
- استخدام العمليات المتسلسلة (Database Transactions) لضمان سلامة البيانات.

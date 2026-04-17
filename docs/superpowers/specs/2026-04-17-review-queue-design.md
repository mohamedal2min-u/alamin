# Spec: تبويب "الذمم والمراجعة" — صفحة المحاسبة
**التاريخ:** 2026-04-17  
**الحالة:** معتمد — جاهز للتنفيذ  
**المشروع:** دجاجاتي — إدارة مداجن دجاج اللحم

---

## 1. الهدف والسياق

### المشكلة
لا يوجد في النظام حالياً مكان موحّد لمتابعة الحركات المالية غير المكتملة:
- مصروفات غير مدفوعة أو مدفوعة جزئياً
- مبيعات لم يُستلم ثمنها كاملاً
- سجلات فيها تناقض مالي يمنع إغلاق الفوج

### الحل
تبويب **"الذمم والمراجعة"** داخل صفحة المحاسبة الجديدة (`/accounting`)، يجمع كل الحركات المالية المحتاجة متابعة أو تصحيح، مع أدوات تعديل سريع مدمجة.

---

## 2. القرارات المحسومة (لا تُعاد)

| القرار | القيمة المعتمدة |
|--------|----------------|
| payment_status القيم | `paid` / `partial` / `debt` |
| معنى `debt` | غير مدفوع — يُعرض في الواجهة كـ "غير مدفوع" |
| لا إضافة `unpaid` | محسوم |
| هيكل review queue | Pure Query Layer — لا جداول جديدة |
| المصدر المالي | expenses + sales فقط |
| inventory_transactions | مصدر كمي فقط — لا يدخل review queue مستقلاً |
| مكان التبويب | داخل `/accounting` — ليس داخل `/reports` |

---

## 3. تعريف الحركة "المحتاجة مراجعة"

سجل يظهر في review queue إذا انطبق عليه **واحد أو أكثر** من الأسباب التالية:

| الكود | العرض العربي | شرط expenses | شرط sales |
|-------|-------------|-------------|----------|
| `unpaid` | غير مدفوع | `payment_status = 'debt'` | `payment_status = 'debt'` |
| `partial_payment` | دفع جزئي | `payment_status = 'partial'` | `payment_status = 'partial'` |
| `missing_price` | ناقص السعر | `total_amount <= 0` | `net_amount <= 0` |
| `missing_payment_status` | ناقص حالة الدفع | محجوز (NOT NULL حالياً) | محجوز (NOT NULL حالياً) |
| `inconsistent_financial_state` | تناقض مالي | انظر §4 | انظر §4 |
| `blocking_flock_closure` | مانع إغلاق | مشتق من التناقض + missing_price | مشتق من التناقض + missing_price |

### ملاحظة على `missing_payment_status`
رغم أن `payment_status` هو NOT NULL في expenses و sales حالياً، يُبقى السبب في عقد الـ API والواجهة لدعم حالات legacy أو مصادر مستقبلية. لن يظهر عملياً في v1 إلا إذا وُجدت بيانات انتقالية.

### ملاحظة على `missing_price`
`total_amount` و `net_amount` هما NOT NULL في schema الحالي، لذا "ناقص السعر" يعني **القيمة = 0 أو سالبة** وليس NULL. هذا **Business Rule** صريح: أي سجل مالي بقيمة صفر يجب مراجعته.

---

## 4. قواعد التناقض المالي

### expenses
| الشرط | الوصف |
|-------|-------|
| `payment_status = 'paid' AND paid_amount < total_amount` | مُسجَّل كمدفوع لكن المبلغ ناقص |
| `payment_status = 'partial' AND paid_amount <= 0` | جزئي بدون دفع فعلي |
| `payment_status = 'partial' AND paid_amount >= total_amount` | جزئي لكن المبلغ مكتمل |
| `payment_status = 'debt' AND paid_amount > 0` | دَين لكن فيه مبلغ مدفوع |
| `ABS(remaining_amount - (total_amount - paid_amount)) > 0.01` | انحراف في القيمة المشتقة |

### sales
| الشرط | الوصف |
|-------|-------|
| `payment_status = 'paid' AND received_amount < net_amount` | مُسجَّل كمدفوع لكن المستلم ناقص |
| `payment_status = 'partial' AND received_amount <= 0` | جزئي بدون استلام فعلي |
| `payment_status = 'partial' AND received_amount >= net_amount` | جزئي لكن المستلم مكتمل |
| `payment_status = 'debt' AND received_amount > 0` | دَين لكن يوجد مستلم |
| `ABS(remaining_amount - (net_amount - received_amount)) > 0.01` | انحراف في القيمة المشتقة |

---

## 5. قواعد منع إغلاق الفوج

### ما يمنع الإغلاق (blocking)
سجلات مرتبطة بالفوج (`flock_id = X`) فيها:
- `total_amount <= 0` (expenses) أو `net_amount <= 0` (sales)
- أي تناقض مالي من §4

### ما لا يمنع الإغلاق
- `payment_status = 'debt'` مع أرقام منضبطة وصحيحة → الفوج **يُغلق** مع بقاء الدَّين مفتوحاً
- `payment_status = 'partial'` مع أرقام منضبطة وصحيحة → الفوج **يُغلق**

---

## 6. تعديلات قاعدة البيانات

### migration جديد واحد فقط

```php
// add_due_date_to_financial_tables
Schema::table('expenses', fn($t) => $t->date('due_date')->nullable()->after('payment_status'));
Schema::table('sales',    fn($t) => $t->date('due_date')->nullable()->after('payment_status'));
```

**لا** يُضاف `reviewed_at` أو `pricing_completed_at` في v1 — غير مطلوب لأي سلوك تشغيلي حالي.

---

## 7. API Contract

### `GET /api/accounting/review-queue`

**Query Parameters:**

| الباراميتر | النوع | الوصف |
|-----------|------|-------|
| `flock_id` | int? | تصفية بفوج محدد |
| `source_type` | expense\|sale | تصفية بنوع المصدر |
| `payment_status` | paid\|partial\|debt | تصفية بحالة الدفع |
| `review_reason` | unpaid\|partial_payment\|missing_price\|missing_payment_status\|inconsistent_financial_state\|blocking_flock_closure | تصفية بسبب المراجعة |
| `start_date` | Y-m-d | من تاريخ |
| `end_date` | Y-m-d | إلى تاريخ |

**Response Item:**
```json
{
  "id": 12,
  "source_type": "expense",
  "source_id": 12,
  "flock_id": 3,
  "flock_name": "دفعة أبريل",
  "reference": "مصروف #12",
  "category_name": "علف",
  "entry_date": "2026-04-10",
  "due_date": null,
  "total_amount": 500.00,
  "settled_amount": 0.00,
  "remaining_amount": 500.00,
  "payment_status": "debt",
  "review_reasons": ["unpaid"],
  "is_blocking_closure": false,
  "can_update": true
}
```

> `settled_amount` = `paid_amount` للمصروفات، `received_amount` للمبيعات — توحيد في الـ response فقط.

**Response Envelope:**
```json
{
  "data": [...],
  "summary": {
    "unpaid_count": 5,
    "partial_payment_count": 4,
    "missing_price_count": 2,
    "missing_payment_status_count": 0,
    "inconsistent_financial_state_count": 1,
    "blocking_flock_closure_count": 3
  }
}
```

**قاعدة اتساق summary:** يُحسب كل count بنفس شروط الـ scope التي تفلتر الجدول — لا اختلاف بين البطاقات والجدول.

---

### `PATCH /api/accounting/review-queue/{type}/{id}`

`type` ∈ {`expense`, `sale`} — يأتي من route param

**Request Body:**
```json
{
  "total_amount": 500.00,        // للمصروفات فقط (إذا كان <= 0)
  "paid_amount": 250.00,         // للمصروفات
  "received_amount": 250.00,     // للمبيعات
  "payment_status": "partial",
  "due_date": "2026-05-01"
}
```

**Validation Rules:**
- `payment_status = 'paid'` → `paid_amount >= total_amount` (expenses) أو `received_amount >= net_amount` (sales)
- `payment_status = 'partial'` → `settled_amount > 0 AND settled_amount < total/net`
- `payment_status = 'debt'` → `settled_amount = 0`
- `total_amount` إذا أُرسل يجب أن يكون `> 0`
- `type` مأخوذ من route — الـ validation يتم في Service وليس عبر `required_if`

**Post-update Logic (في Service):**
1. تحديث السجل الأصلي
2. إعادة احتساب `remaining_amount = total/net - settled`
3. إعادة تقييم `payment_status` إذا لم يُرسل صراحةً
4. إرجاع السجل بصيغة review_item الموحدة مع `review_reasons` المحدَّثة

**Response:** صيغة review item الموحدة نفسها.

---

### `GET /api/flocks/{flock}/closure-check`

**Response:**
```json
{
  "can_close": false,
  "blocking_count": 3,
  "blocking_records_count_by_reason": {
    "missing_price": 1,
    "inconsistent_financial_state": 2
  },
  "blocking_records": [
    {
      "source_type": "expense",
      "source_id": 7,
      "reference": "مصروف #7",
      "reasons": ["inconsistent_financial_state"],
      "description": "payment_status=paid لكن paid_amount=0"
    }
  ]
}
```

---

## 8. هيكل Backend

### الملفات الجديدة

```
app/
├── Services/
│   └── ReviewQueueService.php
│       ├── getQueue(int $farmId, array $filters): Collection
│       ├── getSummary(int $farmId, array $filters): array
│       ├── getClosureBlockers(int $farmId, int $flockId): array
│       └── updateRecord(string $type, int $id, int $farmId, array $data): array
├── Http/Controllers/Api/Accounting/
│   └── ReviewQueueController.php
│       ├── index()          → GET /api/accounting/review-queue
│       ├── update()         → PATCH /api/accounting/review-queue/{type}/{id}
│       └── closureCheck()   → GET /api/flocks/{flock}/closure-check
└── Http/Requests/Accounting/
    └── UpdateReviewItemRequest.php
```

### Scopes على الموديلات الحالية

**`Expense.php` — scopes مضافة:**
- `scopeNeedsReview(Builder $q)` — يجمع كل conditions المراجعة
- `scopeBlocksClosure(Builder $q, int $flockId)` — يفلتر المانعين للإغلاق فقط

**`Sale.php` — scopes مضافة:**
- `scopeNeedsReview(Builder $q)`
- `scopeBlocksClosure(Builder $q, int $flockId)`

**الفصل المهم:**
- `needsReview` يشمل debt + partial + missing_price + inconsistent (لأغراض المتابعة)
- `blocksClosure` يشمل فقط missing_price + inconsistent (لأغراض منع الإغلاق)

### فحص الإغلاق في FlockController

`update()` عند `status = 'closed'`:
```php
$blockers = $this->reviewQueueService->getClosureBlockers($farmId, $flock->id);
if ($blockers['blocking_count'] > 0) {
    return response()->json([
        'message' => 'لا يمكن إغلاق الفوج — يوجد سجلات مالية ناقصة أو متناقضة',
        'blocking_count' => $blockers['blocking_count'],
        'blocking_records_count_by_reason' => $blockers['blocking_records_count_by_reason'],
    ], 422);
}
```

---

## 9. هيكل Frontend

### صفحة جديدة: `/accounting`

```
frontend/src/app/(farm)/accounting/
└── page.tsx                          ← صفحة المحاسبة (tabs)
    └── Tab: "الذمم والمراجعة"

frontend/src/components/
└── accounting/
    ├── ReviewQueueTab.tsx            ← التبويب الرئيسي
    ├── ReviewSummaryCards.tsx        ← 5 بطاقات إحصاء
    ├── ReviewQueueFilters.tsx        ← شريط فلاتر
    ├── ReviewQueueTable.tsx          ← الجدول الموحّد
    └── ReviewQuickEditDrawer.tsx     ← Drawer تعديل سريع

frontend/src/lib/api/
└── reviewQueue.ts                    ← API client
```

### بطاقات الإحصاء

| العنوان | اللون | المفتاح |
|---------|-------|---------|
| غير مدفوع | أحمر | `unpaid_count` |
| دفع جزئي | برتقالي | `partial_payment_count` |
| ناقص السعر | أحمر داكن | `missing_price_count` |
| ناقص حالة الدفع | رمادي | `missing_payment_status_count` |
| مانع إغلاق | أحمر + أيقونة قفل | `blocking_flock_closure_count` |

### أعمدة الجدول

| العمود | المصدر | الملاحظة |
|--------|--------|---------|
| النوع | `source_type` | مصروف / بيع |
| الفئة | `category_name` | — |
| الفوج | `flock_name` | — |
| المرجع | `reference` | — |
| إجمالي | `total_amount` | — |
| المدفوع/المستلم | `settled_amount` | موحّد |
| المتبقي | `remaining_amount` | — |
| حالة الدفع | Badge ملوّن | paid=أخضر / partial=برتقالي / debt=أحمر |
| أسباب المراجعة | Badges | نفس الأسماء العربية المعتمدة |
| مانع إغلاق | Badge أحمر + قفل | إذا `is_blocking_closure` |
| تاريخ الاستحقاق | `due_date` | — |
| إجراء | زر "تعديل" | يفتح Drawer |

### أسماء Badges الأسباب (ثابتة في الواجهة)

| الكود | العرض |
|-------|-------|
| `unpaid` | غير مدفوع |
| `partial_payment` | دفع جزئي |
| `missing_price` | ناقص السعر |
| `missing_payment_status` | ناقص حالة الدفع |
| `inconsistent_financial_state` | تناقض مالي |
| `blocking_flock_closure` | مانع إغلاق |

### Drawer التعديل السريع

يُفتح بالنقر على "تعديل" في صف الجدول. يعرض:
1. ملخص السجل (النوع، التاريخ، المبلغ الكلي)
2. حقل `total_amount` — يظهر فقط إذا كان `<= 0`
3. حقل المدفوع/المستلم — `paid_amount` للمصروفات، `received_amount` للمبيعات
4. حقل `payment_status` (Select)
5. حقل `due_date`
6. زر "حفظ التعديل" + زر "فتح السجل الأصلي"

"فتح السجل الأصلي" يذهب إلى:
- expenses: `/expenses` (مع highlight للسجل إن أمكن)
- sales: `/flocks/{flock_id}?tab=sales`

### تكامل شاشة تفاصيل الفوج

في `/flocks/[id]/page.tsx` — يُستدعى `closure-check` عند فتح زر الإغلاق:

```tsx
// تنبيه في header الصفحة إذا blocking_count > 0
<div className="rounded-xl bg-red-50 border border-red-200 p-4 flex items-center justify-between">
  <span className="text-sm font-bold text-red-800">
    {blockingCount} سجل يمنع إغلاق الفوج
  </span>
  <Link href={`/accounting?tab=review&filter=blocking&flock_id=${flockId}`}>
    عرض السجلات المانعة
  </Link>
</div>
```

زر "إغلاق الفوج" يبقى متاحاً للنقر — لكن الـ API يمنع الإغلاق مع رسالة واضحة.

---

## 10. الاختبارات الأساسية (Backend)

| Test | الوصف |
|------|-------|
| `ReviewQueueIndexTest` | يعيد السجلات الصحيحة لكل reason |
| `ReviewQueueSummaryTest` | summary counts تتطابق مع الجدول |
| `ReviewQueueUpdateExpenseTest` | PATCH على expense يُعيد احتساب remaining_amount |
| `ReviewQueueUpdateSaleTest` | PATCH على sale يُعيد احتساب remaining_amount |
| `ClosureCheckBlockedTest` | إغلاق فوج فيه تناقض مالي يُرفض |
| `ClosureCheckAllowedDebtTest` | إغلاق فوج فيه debt منضبط يُسمح |
| `ClosureCheckAllowedPartialTest` | إغلاق فوج فيه partial منضبط يُسمح |

---

## 11. ما هو خارج النطاق في v1

- `reviewed_at` / `pricing_completed_at` — لا سلوك تشغيلي يحتاجهما الآن
- inventory_transactions كمصدر مستقل في review queue
- Pagination في review queue (v1 يُرجع كل السجلات المطابقة)
- إشعارات تلقائية للسجلات الجديدة المحتاجة مراجعة
- Bulk update لعدة سجلات دفعة واحدة

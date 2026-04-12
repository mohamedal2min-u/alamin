# سير العمل — دجاجاتي
**Workflow — Broiler Farm Management**
**الإصدار:** 1.0 | **التاريخ:** 2026-04-11

> هذا الملف يوثّق كيف يتدفق العمل من المستخدم إلى قاعدة البيانات.
> ثلاثة مستويات: سير العمل التشغيلي، تدفق البيانات، وسير التطوير.

---

## القسم الأول: سير العمل التشغيلي

### 1.1 دورة حياة القطيع الكاملة

```
يوم الإدخال
    │
    ▼
┌─────────────────────────────────────────────┐
│  إنشاء قطيع جديد                           │
│  • اختيار العنبر (يجب أن يكون فارغاً)      │
│  • إدخال العدد الأولي                      │
│  • إدخال موردّ الكتاكيت + السلالة          │
│  Status: active                             │
└─────────────────┬───────────────────────────┘
                  │
                  ▼  (يومياً — لمدة 35-42 يوم)
┌─────────────────────────────────────────────┐
│  السجل اليومي                              │
│  • النفوق (mortality_count)                │
│  • الوزن (avg_weight_kg + sample_size)     │
│  • استهلاك العلف (feed_consumed_kg)        │
│  • درجة الحرارة (اختياري)                 │
│  → يُحدَّث current_count تلقائياً         │
└─────────────────┬───────────────────────────┘
                  │
                  ▼  (عند وصول الوزن للهدف)
┌─────────────────────────────────────────────┐
│  حصاد جزئي (Partial Harvest) — اختياري    │
│  • عدد الطيور المحصودة                    │
│  • الوزن الكلي                             │
│  • السعر/كغ (USD)                         │
│  • العميل                                  │
│  Status: harvesting                         │
│  → يُنشأ تلقائياً: Sale + SaleItem        │
└─────────────────┬───────────────────────────┘
                  │
                  ▼  (بعد آخر حصاد)
┌─────────────────────────────────────────────┐
│  حصاد نهائي (Final Harvest)                │
│  • نفس بيانات الحصاد الجزئي               │
│  → يُغلق القطيع تلقائياً                  │
│  Status: closed                             │
│  → يُتاح العنبر للقطيع التالي             │
└─────────────────┬───────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────┐
│  تقرير الدورة (محسوب تلقائياً)            │
│  • FCR = علف ÷ وزن محصود                  │
│  • تكلفة الكيلو = مصاريف ÷ وزن            │
│  • صافي الربح = إيرادات - مصاريف          │
│  • مقارنة بالدورات السابقة                │
└─────────────────────────────────────────────┘
```

---

### 1.2 سير عمل المخزون

```
شراء مواد جديدة
    │
    ▼
StockMovement (type: purchase, direction: in)
    │
    ├─→ تحديث inventory_items.current_stock
    │
    └─→ هل current_stock < min_stock_level?
            │ نعم → تنبيه تلقائي
            │ لا  → لا شيء

استهلاك يومي (من القطيع)
    │
    ▼
StockMovement (type: consumption, direction: out, flock_id: X)
    │
    ├─→ التحقق: current_stock >= الكمية المطلوبة
    │           │ لا → رفض العملية
    │           │ نعم → المتابعة
    │
    └─→ تحديث current_stock
```

---

### 1.3 سير عمل المصاريف → تكلفة الدورة

```
إضافة مصروف
    │
    ├─→ هل flock_id محدد؟
    │       │ نعم → مصروف الدورة
    │       │           → يدخل في حساب تكلفة الكيلو
    │       │ لا  → مصروف عام للمزرعة
    │
    └─→ تحديث تقرير الربح والخسارة
```

---

## القسم الثاني: تدفق البيانات (Data Flow)

### 2.1 تدفق طلب API عادي

```
Flutter/Next.js
    │
    │  HTTP Request + Bearer Token
    ▼
Laravel API
    │
    ├─[1] Sanctum Middleware
    │       → التحقق من Token
    │       → استخراج user
    │
    ├─[2] FarmMember Middleware
    │       → التحقق أن user عضو في هذا farm
    │       → استخراج farm_id
    │
    ├─[3] Route → Controller
    │
    ├─[4] Form Request (Validation)
    │       → التحقق من البيانات
    │       → التحقق من Spatie Permission
    │
    ├─[5] Service Class
    │       → Business Logic
    │       → Database Operations (مع farm_id إلزامي)
    │
    └─[6] Resource/Response
            → JSON Response (عربي إن لزم)
```

### 2.2 عزل البيانات (Multi-tenant Security)

```
كل Model يجب أن يحتوي:
─────────────────────────────
protected static function booted(): void
{
    static::addGlobalScope('farm', function (Builder $builder) {
        if (auth()->check() && auth()->user()->currentFarmId()) {
            $builder->where('farm_id', auth()->user()->currentFarmId());
        }
    });
}
─────────────────────────────

جداول مستثناة من Global Scope (Lookup tables):
- plans
- flock_types
- item_categories
- expense_categories
```

### 2.3 تدفق المصادقة (Auth Flow)

```
المستخدم يدخل البريد + كلمة المرور
    │
    ▼
POST /api/auth/login
    │
    ├─→ التحقق من بيانات المستخدم
    ├─→ التحقق من is_active = true
    │
    ▼
Response:
{
  "token": "...",
  "user": {
    "id": 1,
    "name": "...",
    "farms": [
      {"id": 1, "name": "مزرعة الخير", "role": "farm_admin"},
      {"id": 2, "name": "مزرعة النور", "role": "farm_manager"}
    ]
  }
}
    │
    ▼
المستخدم يختار المزرعة
    │
    ▼
كل request بعدها يحمل:
  Authorization: Bearer {token}
  X-Farm-Id: {farm_id}
```

---

## القسم الثالث: سير التطوير (Dev Workflow)

### 3.1 دورة إضافة ميزة جديدة

```
[1] التوثيق
    │  هل المتطلب موجود في module-map.md؟
    │  لا → أضفه أولاً
    │  نعم → تابع
    │
    ▼
[2] Database Schema
    │  هل يحتاج جدولاً جديداً أو حقلاً؟
    │  نعم → وثّق في database_schema.md أولاً
    │         ثم اكتب Migration
    │  لا  → تابع
    │
    ▼
[3] Backend (Laravel)
    │  Migration → Model → Service → Request → Controller → Route
    │  اكتب Pest test لكل Service method
    │
    ▼
[4] Frontend (Next.js)
    │  API Client → Zustand Store → Component → Page
    │
    ▼
[5] سجّل في change-log.md
```

### 3.2 قواعد الفريق (Team Rules)

| القاعدة | التفاصيل |
|---|---|
| لا PR بلا Test | كل PR يجب أن يحتوي Pest test لكل Business Logic |
| `farm_id` على كل query | كود Review يرفض أي query بلا `farm_id` |
| لا حذف حقيقي | كل حذف يستخدم `soft delete` (deleted_at) |
| لا Magic Numbers | كل ثابت في `config/` أو Enum |
| ARabic errors | رسائل الخطأ بالعربية في response |

### 3.3 بيئات التطوير

```
development  → قاعدة بيانات محلية + DemoFarm Seed
staging      → قاعدة بيانات staging + بيانات حقيقية معقّمة
production   → قاعدة بيانات منفصلة + Backups يومية
```

---

## القسم الرابع: Workflows الحسابية الأساسية

### 4.1 حساب FCR (Feed Conversion Ratio)

```
FCR = إجمالي العلف المستهلك (كغ) ÷ إجمالي الوزن المحصود (كغ)

المصدر:
  إجمالي العلف = SUM(flock_daily_records.feed_consumed_kg)
                + SUM(stock_movements.quantity WHERE type=consumption AND flock_id=X)

  إجمالي الوزن = SUM(harvests.total_weight_kg WHERE flock_id=X)

المعدل الطبيعي لدجاج اللحم: FCR = 1.6 - 2.0
```

### 4.2 حساب تكلفة الكيلو

```
تكلفة الكيلو = إجمالي مصاريف الدورة (USD) ÷ إجمالي الوزن المحصود (كغ)

إجمالي مصاريف الدورة = SUM(expenses.amount WHERE flock_id=X)
                       + تكلفة الكتاكيت (expense type: chicks_cost)
                       + تكلفة العلف (stock_movements purchases)
                       + تكلفة الدواء
```

### 4.3 حساب صافي الربح

```
الإيرادات = SUM(sale_items.total_price WHERE sale.flock_id=X)
المصاريف  = SUM(expenses.amount WHERE flock_id=X)

صافي الربح = الإيرادات - المصاريف

هامش الربح % = (صافي الربح ÷ الإيرادات) × 100
```

### 4.4 تحديث current_count

```
يُحدَّث عند كل حدث:

current_count =   initial_count
               - SUM(flock_daily_records.mortality_count)
               - SUM(flock_daily_records.culled_count)
               - SUM(harvests.bird_count)

الحدث يُطلق → FlockCountUpdated Event
             → FlockObserver يُحدِّث current_count
```

---

## القسم الخامس: Error Handling

### 5.1 أنواع الأخطاء وردودها

| الخطأ | HTTP Status | الرسالة (عربي) |
|---|---|---|
| بيانات غير صحيحة | 422 | "البيانات المدخلة غير صحيحة" |
| غير مصادق | 401 | "يرجى تسجيل الدخول" |
| غير مصادح | 403 | "ليس لديك صلاحية لهذا الإجراء" |
| مورد غير موجود | 404 | "البيان المطلوب غير موجود" |
| تجاوز حدود الخطة | 402 | "وصلت لحد خطتك، يرجى الترقية" |
| خطأ في الخادم | 500 | "حدث خطأ، يرجى المحاولة لاحقاً" |

### 5.2 قواعد Business Validation

```
إنشاء قطيع:
  ✗ العنبر مشغول بقطيع نشط
  ✗ تجاوز حد الخطة (max_flocks)
  ✗ العنبر لا ينتمي للمزرعة

السجل اليومي:
  ✗ تاريخ مستقبلي
  ✗ اليوم مسجّل بالفعل لهذا القطيع
  ✗ النفوق > current_count

الحصاد:
  ✗ عدد الطيور > current_count
  ✗ الوزن = 0 أو سالب
  ✗ السعر = 0 أو سالب

حركة المخزون (out):
  ✗ الكمية > current_stock
```

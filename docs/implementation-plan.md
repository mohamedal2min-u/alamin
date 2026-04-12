# خطة التنفيذ — دجاجاتي
**Implementation Plan — Broiler Farm Management**
**الإصدار:** 1.0 | **التاريخ:** 2026-04-11

> **القاعدة الذهبية:** لا يبدأ أي مرحلة قبل اعتماد المرحلة السابقة.
> كل مرحلة لها **بوابة شرط (Gate)** يجب اجتيازها قبل المتابعة.

---

## الجدول الزمني العام

```
المرحلة 0 — التوثيق         │ الآن         │ بوابة: اعتماد database_schema.md
المرحلة 1 — قاعدة البيانات  │ بعد الاعتماد │ بوابة: Migrations تعمل + Seeds
المرحلة 2 — Auth + Core API  │ بعد M1       │ بوابة: Login + Sanctum يعمل
المرحلة 3 — Flocks API       │ بعد M2       │ بوابة: CRUD + Business Rules
المرحلة 4 — Inventory API    │ بعد M3       │ بوابة: حركات المخزون تعمل
المرحلة 5 — Sales + Expenses │ بعد M4       │ بوابة: الأرقام تتطابق
المرحلة 6 — Reports API      │ بعد M5       │ بوابة: FCR و P&L صحيح
المرحلة 7 — Next.js Frontend │ موازي M2-M6  │ بوابة: كل صفحة تعمل بـ API حقيقي
المرحلة 8 — Flutter App      │ بعد M7       │ بوابة: ثلاث screens رئيسية
```

---

## المرحلة 0 — التوثيق (Docs-First) ← الحالية

**الهدف:** تحديد كل شيء على الورق قبل أي كود.

### الملفات المطلوبة (الستة فقط)

| الملف | الحالة | الشرط للانتقال |
|---|---|---|
| `docs/project-charter.md` | ✅ مكتمل | — |
| `docs/module-map.md` | ✅ مكتمل | — |
| `docs/implementation-plan.md` | ✅ هذا الملف | — |
| `docs/workflow.md` | ✅ مكتمل | — |
| `docs/database_schema.md` | 🔲 فارغ | **بوابة الانتقال لـ M1** |
| `docs/change-log.md` | 🔲 فارغ | يُملأ تدريجياً |

**🚧 بوابة المرحلة 0:**
> `database_schema.md` معتمد + مراجعة كل ملف آخر ← لا نتجاوز هذه النقطة قبل الاعتماد.

---

## المرحلة 1 — قاعدة البيانات

**المكدس:** PostgreSQL 16 + Laravel Migrations + Seeders

### قائمة الـ Migrations (بالترتيب الإلزامي)

```
001_create_plans_table
002_create_users_table
003_create_farms_table
004_create_farm_users_table
005_create_sheds_table
006_create_flock_types_table
007_create_flocks_table
008_create_flock_daily_records_table
009_create_harvests_table
010_create_item_categories_table
011_create_inventory_items_table
012_create_stock_movements_table
013_create_customers_table
014_create_sales_table
015_create_sale_items_table
016_create_expense_categories_table
017_create_expenses_table
018_create_workers_table
019_create_worker_tasks_table  (v2 — postponed)
020_create_notifications_table (v2 — postponed)
```

### Seeders المطلوبة

```
PlansSeeder           → خطتان: Basic, Pro
FlockTypesSeeder      → broiler, layer, breeder
ItemCategoriesSeeder  → 6 تصنيفات
ExpenseCategoriesSeeder → 8 تصنيفات
SuperAdminSeeder      → مستخدم Super Admin واحد
DemoFarmSeeder        → مزرعة تجريبية كاملة (للاختبار)
```

**🚧 بوابة المرحلة 1:**
> `php artisan migrate --seed` يعمل بلا أخطاء على PostgreSQL 16.

---

## المرحلة 2 — Auth + Core API

**المكدس:** Laravel 11 + Sanctum + Spatie Permission

### Endpoints الأساسية

```
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me
PUT    /api/auth/profile

GET    /api/farms/{farm}/sheds
POST   /api/farms/{farm}/sheds
PUT    /api/farms/{farm}/sheds/{shed}
DELETE /api/farms/{farm}/sheds/{shed}

GET    /api/farms/{farm}/users
POST   /api/farms/{farm}/users/invite
PUT    /api/farms/{farm}/users/{user}/role
DELETE /api/farms/{farm}/users/{user}
```

### Middleware المطلوبة

```php
// على كل route خاص بمزرعة
middleware(['auth:sanctum', 'farm.member', 'check.plan'])
```

**🚧 بوابة المرحلة 2:**
> Login يُرجع Token + Token يمنح الوصول لبيانات المزرعة الصحيحة فقط.

---

## المرحلة 3 — Flocks API ★

**الأهمية:** أعلى مرحلة في المشروع — كل شيء يدور حول الـ Flock.

### Endpoints

```
GET    /api/farms/{farm}/flocks
POST   /api/farms/{farm}/flocks
GET    /api/farms/{farm}/flocks/{flock}
PUT    /api/farms/{farm}/flocks/{flock}
DELETE /api/farms/{farm}/flocks/{flock}

GET    /api/farms/{farm}/flocks/{flock}/daily-records
POST   /api/farms/{farm}/flocks/{flock}/daily-records
PUT    /api/farms/{farm}/flocks/{flock}/daily-records/{record}

GET    /api/farms/{farm}/flocks/{flock}/harvests
POST   /api/farms/{farm}/flocks/{flock}/harvests
PUT    /api/farms/{farm}/flocks/{flock}/harvests/{harvest}

POST   /api/farms/{farm}/flocks/{flock}/close

GET    /api/farms/{farm}/flocks/{flock}/summary   ← FCR، تكلفة الكيلو، الربح
```

### Business Logic في Service Classes

```
FlockService::createFlock()        → التحقق من العنبر خالٍ + خطة الاشتراك
FlockService::recordDaily()        → تحديث current_count
FlockService::recordHarvest()      → تحديث current_count + فتح Sales
FlockService::closeFlock()         → التحقق من اكتمال الحصاد
FlockService::calculateFCR()       → العلف ÷ الوزن
FlockService::calculateCostPerKg() → المصاريف ÷ الوزن
```

**🚧 بوابة المرحلة 3:**
> إنشاء قطيع + إدخال 7 أيام يومية + حصاد نهائي → FCR and P&L صحيح.

---

## المرحلة 4 — Inventory API

```
GET    /api/farms/{farm}/inventory
POST   /api/farms/{farm}/inventory
GET    /api/farms/{farm}/inventory/{item}
GET    /api/farms/{farm}/inventory/{item}/movements

POST   /api/farms/{farm}/stock-movements   ← الحركة الرئيسية
```

**🚧 بوابة المرحلة 4:**
> إضافة بند علف + ربطه بقطيع + ظهوره في FCR.

---

## المرحلة 5 — Sales + Expenses API

```
# Sales
GET    /api/farms/{farm}/customers
POST   /api/farms/{farm}/customers
GET    /api/farms/{farm}/sales
POST   /api/farms/{farm}/sales
GET    /api/farms/{farm}/sales/{sale}

# Expenses
GET    /api/farms/{farm}/expenses
POST   /api/farms/{farm}/expenses
GET    /api/farms/{farm}/expenses/{expense}
```

**🚧 بوابة المرحلة 5:**
> إيرادات - مصاريف = صافي الربح لدورة منتهية.

---

## المرحلة 6 — Reports API

```
GET /api/farms/{farm}/reports/cycle/{flock}
GET /api/farms/{farm}/reports/pl?from=&to=
GET /api/farms/{farm}/reports/inventory-summary
GET /api/farms/{farm}/reports/compare?flocks[]=&flocks[]=
```

**🚧 بوابة المرحلة 6:**
> تقرير دورة كامل يشمل: FCR، تكلفة الكيلو، الإيرادات، المصاريف، صافي الربح.

---

## المرحلة 7 — Next.js Frontend

**المكدس:** Next.js 15 App Router + Tailwind CSS + RTL + USD

### هيكل المسارات (App Router)

```
app/
├── (auth)/
│   └── login/page.tsx
├── (super)/
│   ├── layout.tsx               ← Super Admin Layout
│   ├── dashboard/page.tsx
│   ├── farms/page.tsx
│   └── plans/page.tsx
├── (farm)/
│   ├── layout.tsx               ← Farm Layout + farm_id context
│   ├── dashboard/page.tsx       ← لوحة المزرعة الرئيسية
│   ├── flocks/
│   │   ├── page.tsx             ← قائمة القطعان
│   │   ├── new/page.tsx
│   │   └── [id]/
│   │       ├── page.tsx
│   │       ├── daily/page.tsx
│   │       └── harvest/page.tsx
│   ├── inventory/page.tsx
│   ├── sales/page.tsx
│   ├── expenses/page.tsx
│   ├── workers/page.tsx
│   ├── reports/page.tsx
│   └── settings/page.tsx
```

### أولوية تطوير الصفحات

```
الأولوية 1: login + farm/dashboard + farm/flocks
الأولوية 2: flocks/[id]/daily + flocks/[id]/harvest
الأولوية 3: inventory + expenses + sales
الأولوية 4: reports + workers + settings
الأولوية 5: super/* (admin panel)
```

**🚧 بوابة المرحلة 7:**
> مدير المزرعة يستطيع: تسجيل دخول → إنشاء قطيع → إدخال سجل يومي → تسجيل حصاد → رؤية تقرير الدورة.

---

## المرحلة 8 — Flutter App (v2)

**الهدف:** تطبيق جوال مخصص للعمارل وإدخال البيانات الميدانية.

### Screens الأولوية

1. تسجيل الدخول
2. قائمة القطعان النشطة
3. إدخال السجل اليومي (الأكثر استخداماً)

**🚧 بوابة المرحلة 8:**
> عامل يدخل من الجوال ويسجّل النفوق والوزن اليومي.

---

## قائمة Laravel Packages

```json
{
  "require": {
    "laravel/sanctum": "^4.0",
    "spatie/laravel-permission": "^6.0",
    "spatie/laravel-query-builder": "^5.0",
    "spatie/laravel-data": "^4.0",
    "league/fractal": "^0.20"
  },
  "require-dev": {
    "pestphp/pest": "^3.0"
  }
}
```

## قائمة Next.js Packages

```json
{
  "dependencies": {
    "next": "^15.0",
    "react": "^19.0",
    "axios": "^1.6",
    "zustand": "^4.5",
    "react-hook-form": "^7.0",
    "zod": "^3.22",
    "@tanstack/react-query": "^5.0",
    "recharts": "^2.10"
  }
}
```

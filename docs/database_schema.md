# Database Schema v1 Foundation

## 1) مبادئ التصميم

- قاعدة البيانات: PostgreSQL 16
- النظام: Multi-tenant عبر `farm_id`
- `farm` هو حد العزل الأساسي
- `flock` هو مركز التشغيل الأساسي
- لا يجوز وجود أكثر من فوج نشط واحد داخل نفس المدجنة
- كل سجل مهم يجب أن يدعم:
  - `created_at`
  - `updated_at`
  - `created_by`
  - `updated_by`
- التقارير في v1 هي طبقة استعلام فوق الجداول، وليست جداول مستقلة
- التشغيل اليومي يسجل كسجلات خام، ثم يتم التجميع في التقارير وصفحة تفاصيل الفوج
- العلف والدواء يرتبطان بالمخزون مباشرة
- المياه تبقى سجلًا تشغيليًا مستقلًا
- المحاسبة تشغيلية وليست محاسبة عامة كاملة

---

## 2) الجداول الأساسية

## 2.1 users

يمثل جميع حسابات النظام:
- super_admin
- farm_admin
- partner
- worker

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| name | varchar(150) | not null |
| email | varchar(190) | null, unique |
| whatsapp | varchar(30) | null, unique |
| password | varchar(255) | not null |
| status | varchar(30) | not null, default 'active' |
| avatar_path | varchar(255) | null |
| last_login_at | timestamp | null |
| email_verified_at | timestamp | null |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

### ملاحظات
- الدخول يكون عبر البريد أو الواتساب مع كلمة المرور
- الحساب لا يدخل إلا إذا كان نشطًا ومرتبطًا بدور أو مدجنة معتمدة

---

## 2.2 registration_requests

طلبات التسجيل الأولي قبل الاعتماد.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| name | varchar(150) | not null |
| email | varchar(190) | null |
| whatsapp | varchar(30) | not null |
| password_hash | varchar(255) | not null |
| location | varchar(255) | null |
| farm_name | varchar(190) | null |
| status | varchar(30) | not null, default 'pending' |
| reviewed_by | bigint | null, FK -> users.id |
| reviewed_at | timestamp | null |
| rejection_reason | text | null |
| notes | text | null |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

### status
- pending
- approved
- rejected

---

## 2.3 farms

يمثل المدجنة كوحدة عزل أساسية.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| name | varchar(190) | not null |
| location | varchar(255) | null |
| status | varchar(30) | not null, default 'pending_setup' |
| admin_user_id | bigint | null, FK -> users.id |
| started_at | date | null |
| notes | text | null |
| created_by | bigint | null, FK -> users.id |
| updated_by | bigint | null, FK -> users.id |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

### status
- pending_setup
- active
- suspended

---

## 2.4 farm_users

ربط المستخدمين بالمدجنة ودورهم داخلها.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| farm_id | bigint | not null, FK -> farms.id |
| user_id | bigint | not null, FK -> users.id |
| role | varchar(30) | not null |
| status | varchar(30) | not null, default 'active' |
| is_primary | boolean | not null, default false |
| joined_at | timestamp | null |
| notes | text | null |
| created_by | bigint | null, FK -> users.id |
| updated_by | bigint | null, FK -> users.id |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

### قيود
- unique (`farm_id`, `user_id`, `role`)

### roles
- farm_admin
- partner
- worker

> ملاحظة: `super_admin` دور على مستوى النظام، وليس عبر `farm_users`.

---

## 3) دومين الأفواج والتشغيل اليومي

## 3.1 flocks

الفوج هو الوحدة التشغيلية والمالية الأساسية.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| farm_id | bigint | not null, FK -> farms.id |
| name | varchar(190) | not null |
| status | varchar(30) | not null, default 'draft' |
| start_date | date | not null |
| close_date | date | null |
| initial_count | integer | not null |
| current_age_days | integer | null |
| notes | text | null |
| created_by | bigint | null, FK -> users.id |
| updated_by | bigint | null, FK -> users.id |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

### status
- draft
- active
- closed
- cancelled

### قيود
- check (`initial_count > 0`)
- يجب إنشاء partial unique index:
  - unique (`farm_id`) where `status = 'active'`

---

## 3.2 flock_mortalities

سجلات النفوق اليومية الخام.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| farm_id | bigint | not null, FK -> farms.id |
| flock_id | bigint | not null, FK -> flocks.id |
| entry_date | date | not null |
| quantity | integer | not null |
| reason | varchar(190) | null |
| notes | text | null |
| worker_id | bigint | null, FK -> users.id |
| editable_until | timestamp | null |
| created_by | bigint | null, FK -> users.id |
| updated_by | bigint | null, FK -> users.id |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

### قيود
- check (`quantity > 0`)

---

## 3.3 flock_feed_logs

سجلات العلف اليومية الخام.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| farm_id | bigint | not null, FK -> farms.id |
| flock_id | bigint | not null, FK -> flocks.id |
| item_id | bigint | not null, FK -> items.id |
| entry_date | date | not null |
| quantity | numeric(14,3) | not null |
| unit_label | varchar(50) | null |
| notes | text | null |
| worker_id | bigint | null, FK -> users.id |
| inventory_transaction_id | bigint | null, FK -> inventory_transactions.id |
| editable_until | timestamp | null |
| created_by | bigint | null, FK -> users.id |
| updated_by | bigint | null, FK -> users.id |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

### قيود
- check (`quantity > 0`)

---

## 3.4 flock_medicines

سجلات الدواء اليومية الخام.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| farm_id | bigint | not null, FK -> farms.id |
| flock_id | bigint | not null, FK -> flocks.id |
| item_id | bigint | not null, FK -> items.id |
| entry_date | date | not null |
| quantity | numeric(14,3) | not null |
| unit_label | varchar(50) | null |
| notes | text | null |
| worker_id | bigint | null, FK -> users.id |
| inventory_transaction_id | bigint | null, FK -> inventory_transactions.id |
| editable_until | timestamp | null |
| created_by | bigint | null, FK -> users.id |
| updated_by | bigint | null, FK -> users.id |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

### قيود
- check (`quantity > 0`)

---

## 3.5 flock_water_logs

سجلات المياه اليومية الخام.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| farm_id | bigint | not null, FK -> farms.id |
| flock_id | bigint | not null, FK -> flocks.id |
| entry_date | date | not null |
| quantity | numeric(14,3) | null |
| unit_label | varchar(50) | null |
| notes | text | null |
| worker_id | bigint | null, FK -> users.id |
| editable_until | timestamp | null |
| created_by | bigint | null, FK -> users.id |
| updated_by | bigint | null, FK -> users.id |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

---

## 3.6 flock_notes

ملاحظات تشغيلية وتعليمات وتنبيهات مرتبطة بالفوج.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| farm_id | bigint | not null, FK -> farms.id |
| flock_id | bigint | not null, FK -> flocks.id |
| note_type | varchar(50) | not null, default 'general' |
| note_text | text | not null |
| entry_date | date | null |
| worker_id | bigint | null, FK -> users.id |
| created_by | bigint | null, FK -> users.id |
| updated_by | bigint | null, FK -> users.id |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

### note_type
- general
- instruction
- operational
- alert

---

## 4) دومين المحاسبة التشغيلية

## 4.1 expense_categories

تصنيف المصروفات.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| farm_id | bigint | null, FK -> farms.id |
| name | varchar(150) | not null |
| code | varchar(50) | null |
| is_system | boolean | not null, default false |
| is_active | boolean | not null, default true |
| created_by | bigint | null, FK -> users.id |
| updated_by | bigint | null, FK -> users.id |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

### فئات أساسية مقترحة
- feed
- medicine
- water
- bedding
- wages
- transport
- maintenance
- other

---

## 4.2 expenses

المصروفات التشغيلية والمرتبطة أحيانًا بالفوج.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| farm_id | bigint | not null, FK -> farms.id |
| flock_id | bigint | null, FK -> flocks.id |
| expense_category_id | bigint | not null, FK -> expense_categories.id |
| entry_date | date | not null |
| expense_type | varchar(50) | null |
| quantity | numeric(14,3) | null |
| unit_price | numeric(14,4) | null |
| total_amount | numeric(14,2) | not null |
| paid_amount | numeric(14,2) | not null, default 0 |
| remaining_amount | numeric(14,2) | not null, default 0 |
| payment_status | varchar(30) | not null, default 'paid' |
| reference_no | varchar(100) | null |
| description | varchar(255) | null |
| notes | text | null |
| attachment_path | varchar(255) | null |
| linked_inventory_transaction_id | bigint | null, FK -> inventory_transactions.id |
| worker_id | bigint | null, FK -> users.id |
| created_by | bigint | null, FK -> users.id |
| updated_by | bigint | null, FK -> users.id |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

### payment_status
- paid
- partial
- debt

### قيود
- check (`total_amount >= 0`)
- check (`paid_amount >= 0`)
- check (`remaining_amount >= 0`)

---

## 5) دومين المبيعات

## 5.1 sales

عملية بيع على مستوى الفوج.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| farm_id | bigint | not null, FK -> farms.id |
| flock_id | bigint | not null, FK -> flocks.id |
| sale_date | date | not null |
| reference_no | varchar(100) | null |
| buyer_name | varchar(190) | null |
| invoice_attachment_path | varchar(255) | null |
| gross_amount | numeric(14,2) | not null |
| discount_amount | numeric(14,2) | not null, default 0 |
| net_amount | numeric(14,2) | not null |
| received_amount | numeric(14,2) | not null, default 0 |
| remaining_amount | numeric(14,2) | not null, default 0 |
| payment_status | varchar(30) | not null, default 'paid' |
| notes | text | null |
| created_by | bigint | null, FK -> users.id |
| updated_by | bigint | null, FK -> users.id |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

### payment_status
- paid
- partial
- debt

---

## 5.2 sale_items

تفاصيل البيع الحاملة للأعداد والأوزان.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| sale_id | bigint | not null, FK -> sales.id on delete cascade |
| farm_id | bigint | not null, FK -> farms.id |
| flock_id | bigint | not null, FK -> flocks.id |
| birds_count | integer | not null |
| total_weight_kg | numeric(14,3) | not null |
| avg_weight_kg | numeric(10,3) | null |
| unit_price_per_kg | numeric(14,4) | null |
| line_total | numeric(14,2) | not null |
| notes | text | null |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

### قيود
- check (`birds_count > 0`)
- check (`total_weight_kg > 0`)

---

## 6) دومين الشركاء

## 6.1 partners

بيانات الشريك الأساسية داخل المدجنة.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| farm_id | bigint | not null, FK -> farms.id |
| user_id | bigint | null, FK -> users.id |
| name | varchar(150) | not null |
| email | varchar(190) | null |
| whatsapp | varchar(30) | null |
| status | varchar(30) | not null, default 'active' |
| notes | text | null |
| created_by | bigint | null, FK -> users.id |
| updated_by | bigint | null, FK -> users.id |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

### status
- active
- inactive

---

## 6.2 farm_partner_shares

حصص الشركاء داخل المدجنة.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| farm_id | bigint | not null, FK -> farms.id |
| partner_id | bigint | not null, FK -> partners.id |
| share_percent | numeric(5,2) | not null, default 0 |
| is_active | boolean | not null, default true |
| effective_from | date | null |
| effective_to | date | null |
| notes | text | null |
| created_by | bigint | null, FK -> users.id |
| updated_by | bigint | null, FK -> users.id |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

### قيود
- check (`share_percent >= 0`)
- check (`share_percent <= 100`)

### قواعد عمل
- إذا `share_percent = 0` => غير فعال
- إذا `share_percent > 0` => فعال
- مجموع الحصص داخل المدجنة يجب ألا يتجاوز `100%`

---

## 6.3 partner_transactions

الحركات المالية للشركاء.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| farm_id | bigint | not null, FK -> farms.id |
| partner_id | bigint | not null, FK -> partners.id |
| flock_id | bigint | null, FK -> flocks.id |
| transaction_date | date | not null |
| transaction_type | varchar(30) | not null |
| amount | numeric(14,2) | not null |
| description | varchar(255) | null |
| reference_no | varchar(100) | null |
| notes | text | null |
| created_by | bigint | null, FK -> users.id |
| updated_by | bigint | null, FK -> users.id |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

### transaction_type
- deposit
- withdraw
- profit
- loss
- adjustment
- settlement

---

## 7) دومين المخزون

## 7.1 item_types

أنواع الأصناف.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| farm_id | bigint | null, FK -> farms.id |
| name | varchar(150) | not null |
| code | varchar(50) | null |
| is_system | boolean | not null, default false |
| is_active | boolean | not null, default true |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

### أمثلة
- feed
- medicine
- bedding
- water_supply
- other

---

## 7.2 warehouses

المستودعات التابعة للمدجنة.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| farm_id | bigint | not null, FK -> farms.id |
| name | varchar(150) | not null |
| location | varchar(255) | null |
| is_active | boolean | not null, default true |
| notes | text | null |
| created_by | bigint | null, FK -> users.id |
| updated_by | bigint | null, FK -> users.id |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

---

## 7.3 items

تعريف الصنف قبل أي حركة مخزون.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| farm_id | bigint | not null, FK -> farms.id |
| item_type_id | bigint | not null, FK -> item_types.id |
| name | varchar(150) | not null |
| input_unit | varchar(50) | not null |
| unit_value | numeric(14,3) | not null |
| content_unit | varchar(50) | not null |
| minimum_stock | numeric(14,3) | null |
| default_cost | numeric(14,4) | null |
| status | varchar(30) | not null, default 'active' |
| notes | text | null |
| created_by | bigint | null, FK -> users.id |
| updated_by | bigint | null, FK -> users.id |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

### قيود
- unique (`farm_id`, `name`)
- check (`unit_value > 0`)

---

## 7.4 warehouse_items

الرصيد الحالي لكل صنف داخل كل مستودع.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| farm_id | bigint | not null, FK -> farms.id |
| warehouse_id | bigint | not null, FK -> warehouses.id |
| item_id | bigint | not null, FK -> items.id |
| current_quantity | numeric(14,3) | not null, default 0 |
| average_cost | numeric(14,4) | null |
| last_in_at | timestamp | null |
| last_out_at | timestamp | null |
| status | varchar(30) | null |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

### قيود
- unique (`warehouse_id`, `item_id`)

---

## 7.5 inventory_transactions

جميع حركات المخزون القابلة للتتبع.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| farm_id | bigint | not null, FK -> farms.id |
| warehouse_id | bigint | not null, FK -> warehouses.id |
| item_id | bigint | not null, FK -> items.id |
| flock_id | bigint | null, FK -> flocks.id |
| transaction_date | date | not null |
| transaction_type | varchar(30) | not null |
| direction | varchar(10) | not null |
| source_module | varchar(50) | null |
| original_quantity | numeric(14,3) | null |
| computed_quantity | numeric(14,3) | not null |
| unit_price | numeric(14,4) | null |
| total_amount | numeric(14,2) | null |
| payment_status | varchar(30) | null |
| supplier_name | varchar(190) | null |
| invoice_no | varchar(100) | null |
| invoice_attachment_path | varchar(255) | null |
| reference_no | varchar(100) | null |
| notes | text | null |
| created_by | bigint | null, FK -> users.id |
| updated_by | bigint | null, FK -> users.id |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

### transaction_type
- in
- out
- adjustment
- return

### direction
- in
- out

### payment_status
- paid
- debt

---

## 8) إعدادات المستخدم

## 8.1 user_settings

إعدادات الحساب الشخصية.

| العمود | النوع | القيود |
|---|---|---|
| id | bigserial | PK |
| user_id | bigint | not null, unique, FK -> users.id on delete cascade |
| theme | varchar(20) | not null, default 'light' |
| locale | varchar(10) | not null, default 'ar' |
| timezone | varchar(50) | not null, default 'Asia/Amman' |
| created_at | timestamp | not null |
| updated_at | timestamp | not null |

---

## 9) الفهارس الأساسية المقترحة

- index on `farm_users(farm_id, user_id)`
- index on `flocks(farm_id, status, start_date)`
- index on `flock_mortalities(flock_id, entry_date)`
- index on `flock_feed_logs(flock_id, entry_date)`
- index on `flock_medicines(flock_id, entry_date)`
- index on `flock_water_logs(flock_id, entry_date)`
- index on `expenses(farm_id, flock_id, entry_date)`
- index on `sales(farm_id, flock_id, sale_date)`
- index on `partner_transactions(farm_id, partner_id, transaction_date)`
- index on `inventory_transactions(farm_id, item_id, transaction_date)`
- index on `warehouse_items(farm_id, warehouse_id, item_id)`

---

## 10) قواعد لا تُفرض كلها بالـ Schema فقط

هذه القواعد يجب تنفيذها في Laravel Actions / Services / Validation / Policies:

- لا دخول إلا لحساب نشط ومرتبط بدور أو مدجنة معتمدة
- العامل يعدل سجله فقط
- العامل يعدل خلال 15 دقيقة فقط
- لا إدخالات تشغيلية على فوج مغلق
- لا إدخال يومي بتاريخ سابق أو لاحق
- لا بيع بعدد أكبر من العدد الحي
- العدد الحي = `initial_count - mortalities - previous sales`
- مجموع حصص الشركاء <= `100%`
- لا حركة مخزون لصنف غير معرف
- العلف والدواء يجب أن يكونا من أصناف المخزون
- عند تسجيل العلف أو الدواء تشغيليًا يجب إنشاء حركة مخزون مرتبطة

---

## 11) ما تم استبعاده عمدًا من v1

- جداول تقارير مستقلة
- جداول Dashboard مستقلة
- جداول CRM أو customers مستقلة
- جداول plans أو subscriptions
- جداول sheds
- جداول محاسبة عامة كاملة مثل ledger / journal

السبب:
- غير ظاهرة صراحة في الواجهات المعتمدة
- خارج نطاق v1 الحالي
- قد تسبب over-engineering مبكرًا

---

## 12) ترتيب التنفيذ بعد اعتماد هذا الملف

1. مراجعة `database_schema.md`
2. تحويله إلى migration-ready plan
3. تنفيذ Laravel migrations
4. بناء Models + Relations
5. ثم API Spec
6. ثم Actions / Services / Policies
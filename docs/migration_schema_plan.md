# Migration-Ready Schema Plan

> مشتق من `database_schema.md` — خطة تنفيذ جاهزة للـ Migrations — **v2 (معتمد)**
> آخر تحديث: اعتماد Migration Alignment Plan v2 — Spatie Teams + farm_users بدون role
> لا تعديل هنا إلا بعد تحديث `database_schema.md` أولاً

---

## 1. Stack المعتمد

| البند | القرار |
|---|---|
| Framework | Laravel 11 |
| Database | PostgreSQL 16 |
| Primary Keys | `bigserial` → `$table->id()` |
| Timestamps | `TIMESTAMPTZ` → `$table->timestampsTz()` |
| Soft Deletes | غير مستخدمة — يُعوَّض بـ `status` |
| UUID | غير مستخدم في v1 |
| Tenant isolation | عبر `farm_id` على كل جدول تشغيلي |
| Authorization | `spatie/laravel-permission` مع Teams — `farm_id` كـ team context |

> **شرط قبل أي migrate**: يجب تعديل `config/permission.php` أولاً:
> ```php
> 'teams' => true,
> 'column_names' => ['team_foreign_key' => 'farm_id', ...],
> ```
> ثم `php artisan config:clear` — لأن migration الـ Spatie يقرأ هذا الـ config في `up()`.
> إذا رُنَّت migration بـ `teams = false` لن يُنشأ عمود `farm_id` في جداول Spatie.

> **تنبيه Laravel**: استبدل `$table->timestamps()` بـ `$table->timestampsTz()` في كل migration
> للحصول على `timestamp with time zone` بدلاً من `timestamp` — ضروري في بيئات multi-timezone.

---

## 2. ترتيب تنفيذ Migrations

الترتيب مبني على شجرة التبعيات الصارمة (FK dependencies):

```
── Infrastructure (0001_01_01_*) ─────────────────────────────────────
0.a  users + password_reset_tokens + sessions   [0001_01_01_000000]
0.b  cache + cache_locks                        [0001_01_01_000001]
0.c  jobs + job_batches + failed_jobs           [0001_01_01_000002]

── Project Domain (2026_04_11_0000xx) ───────────────────────────────
1.   registration_requests      → users
2.   farms                      → users
3.   user_settings              → users
4.   farm_users                 → farms, users   [بدون role — الدور في Spatie]
5.   item_types                 → farms (nullable)
6.   warehouses                 → farms
7.   items                      → farms, item_types
8.   warehouse_items            → farms, warehouses, items
9.   flocks                     → farms
10.  inventory_transactions     → farms, warehouses, items, flocks (nullable)
11.  flock_mortalities          → farms, flocks
12.  flock_feed_logs            → farms, flocks, items, inventory_transactions (nullable)
13.  flock_medicines            → farms, flocks, items, inventory_transactions (nullable)
14.  flock_water_logs           → farms, flocks
15.  flock_notes                → farms, flocks
16.  expense_categories         → farms (nullable)
17.  expenses                   → farms, flocks (nullable), expense_categories, inventory_transactions (nullable)
18.  sales                      → farms, flocks
19.  sale_items                 → sales, farms, flocks
20.  partners                   → farms, users (nullable)
21.  farm_partner_shares        → farms, partners
22.  partner_transactions       → farms, partners, flocks (nullable)

── Authorization (2026_04_11_015745) ────────────────────────────────
23.  Spatie: permissions + roles + model_has_* + role_has_permissions
     [موجود — لا تعديل على الملف، يشترط teams=true في config قبل التشغيل]
```

> **ملاحظة ترتيب Spatie**: timestamp الـ Spatie migration هو `015745` وهو أكبر من `000001–000022`
> لذا يُنفَّذ تلقائياً بعد migrations المشروع. لا توجد FKs بين جداول Spatie وجداول المشروع
> فالترتيب لا يخلق تبعية صارمة، لكن التسلسل الحالي مقبول ومنطقي.

---

## 3. تفصيل الجداول

---

### 3.0 Spatie Permission Tables

**الملف الموجود**: `2026_04_11_015745_create_permission_tables.php` — لا تعديل عليه

| الجدول | الغرض |
|---|---|
| `permissions` | تعريف الصلاحيات |
| `roles` | تعريف الأدوار + عمود `farm_id` (team_foreign_key) |
| `model_has_permissions` | ربط صلاحية مباشرة بـ Model |
| `model_has_roles` | ربط دور بـ User + عمود `farm_id` لتحديد نطاق المدجنة |
| `role_has_permissions` | ربط صلاحية بدور |

**الأدوار الأساسية في النظام**:

| الدور | النطاق | آلية التعيين |
|---|---|---|
| `super_admin` | Global (بدون farm scope) | `$user->assignRole('super_admin')` |
| `farm_admin` | Scoped إلى farm_id | `setPermissionsTeamId($farmId); $user->assignRole('farm_admin')` |
| `partner` | Scoped إلى farm_id | `setPermissionsTeamId($farmId); $user->assignRole('partner')` |
| `worker` | Scoped إلى farm_id | `setPermissionsTeamId($farmId); $user->assignRole('worker')` |

**التحقق من الدور داخل المدجنة** (Application Layer):
```php
// تعيين farm context قبل أي عملية role-scoped
setPermissionsTeamId($farmId);
$user->hasRole('farm_admin'); // true إذا كان farm_admin في هذه المدجنة
```

> لا توجد FKs بين جداول Spatie وجداول المشروع.
> `farm_id` في Spatie هو integer عادي — ليس FK مُقيَّد بـ `farms.id`.

---

### 3.1 `users`

**ترتيب**: Infrastructure — `0001_01_01_000000` (يُعاد كتابة جزء `users` فقط)

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| name | `$table->string('name', 150)` | varchar(150) | NOT NULL |
| email | `$table->string('email', 190)->nullable()->unique()` | varchar(190) | NULL, UNIQUE |
| whatsapp | `$table->string('whatsapp', 30)->nullable()->unique()` | varchar(30) | NULL, UNIQUE |
| password | `$table->string('password')` | varchar(255) | NOT NULL |
| status | `$table->string('status', 30)->default('active')` | varchar(30) | NOT NULL, DEFAULT 'active' |
| avatar_path | `$table->string('avatar_path')->nullable()` | varchar(255) | NULL |
| last_login_at | `$table->timestampTz('last_login_at')->nullable()` | timestamptz | NULL |
| email_verified_at | `$table->timestampTz('email_verified_at')->nullable()` | timestamptz | NULL |
| remember_token | `$table->rememberToken()` | varchar(100) | NULL |
| created_by | `$table->unsignedBigInteger('created_by')->nullable()` | bigint | NULL — بدون FK |
| updated_by | `$table->unsignedBigInteger('updated_by')->nullable()` | bigint | NULL — بدون FK |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**CHECK Constraints** (Raw SQL في migration):
```sql
ALTER TABLE users ADD CONSTRAINT chk_users_status
    CHECK (status IN ('active', 'inactive', 'suspended'));
```

> **`created_by` / `updated_by` بدون FK**: هذان العمودان self-referential على نفس جدول `users`.
> لا يمكن إضافتهما كـ `->constrained('users')` داخل نفس migration لأن الجدول لم يكتمل بعد.
> يبقيان كـ `unsignedBigInteger()->nullable()` — القيد المنطقي يُطبَّق في Application Layer.

> **`remember_token`**: مطلوب لـ Laravel Sanctum — غير مذكور في `database_schema.md`
> لكنه infrastructure ضروري ولا يتعارض مع schema المعتمد.

**ملاحظة**: الدخول عبر email أو whatsapp — يجب أن يكون واحد منهما على الأقل موجوداً.
القيد التالي يُنفَّذ في Application Layer (Validation Rule) لا في DB:
`email IS NOT NULL OR whatsapp IS NOT NULL`

---

### 3.2 `registration_requests`

**ترتيب**: 2 — يعتمد على: `users`

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| name | `$table->string('name', 150)` | varchar(150) | NOT NULL |
| email | `$table->string('email', 190)->nullable()` | varchar(190) | NULL |
| whatsapp | `$table->string('whatsapp', 30)` | varchar(30) | NOT NULL |
| password_hash | `$table->string('password_hash')` | varchar(255) | NOT NULL |
| location | `$table->string('location')->nullable()` | varchar(255) | NULL |
| farm_name | `$table->string('farm_name', 190)->nullable()` | varchar(190) | NULL |
| status | `$table->string('status', 30)->default('pending')` | varchar(30) | NOT NULL |
| reviewed_by | `$table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| reviewed_at | `$table->timestampTz('reviewed_at')->nullable()` | timestamptz | NULL |
| rejection_reason | `$table->text('rejection_reason')->nullable()` | text | NULL |
| notes | `$table->text('notes')->nullable()` | text | NULL |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| reviewed_by | users.id | SET NULL |

**CHECK Constraints**:
```sql
ALTER TABLE registration_requests ADD CONSTRAINT chk_reg_status
    CHECK (status IN ('pending', 'approved', 'rejected'));
```

---

### 3.3 `farms`

**ترتيب**: 3 — يعتمد على: `users`

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| name | `$table->string('name', 190)` | varchar(190) | NOT NULL |
| location | `$table->string('location')->nullable()` | varchar(255) | NULL |
| status | `$table->string('status', 30)->default('pending_setup')` | varchar(30) | NOT NULL |
| admin_user_id | `$table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| started_at | `$table->date('started_at')->nullable()` | date | NULL |
| notes | `$table->text('notes')->nullable()` | text | NULL |
| created_by | `$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| updated_by | `$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| admin_user_id | users.id | SET NULL |
| created_by | users.id | SET NULL |
| updated_by | users.id | SET NULL |

**CHECK Constraints**:
```sql
ALTER TABLE farms ADD CONSTRAINT chk_farms_status
    CHECK (status IN ('pending_setup', 'active', 'suspended'));
```

---

### 3.4 `user_settings`

**ترتيب**: 4 — يعتمد على: `users`

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| user_id | `$table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete()` | bigint | NOT NULL, UNIQUE, FK |
| theme | `$table->string('theme', 20)->default('light')` | varchar(20) | NOT NULL |
| locale | `$table->string('locale', 10)->default('ar')` | varchar(10) | NOT NULL |
| timezone | `$table->string('timezone', 50)->default('Asia/Amman')` | varchar(50) | NOT NULL |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| user_id | users.id | CASCADE |

---

### 3.5 `farm_users`

**ترتيب**: 5 — يعتمد على: `farms`, `users`

> **مسؤولية هذا الجدول**: عضوية خالصة (membership metadata).
> يجيب على: "هل هذا المستخدم عضو في هذه المدجنة؟ متى انضم؟ هل عضويته نشطة؟"
> **لا** يحمل الدور — الدور محفوظ في Spatie مع `farm_id` كـ team context.

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| farm_id | `$table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete()` | bigint | NOT NULL, FK |
| user_id | `$table->foreignId('user_id')->constrained('users')->cascadeOnDelete()` | bigint | NOT NULL, FK |
| status | `$table->string('status', 30)->default('active')` | varchar(30) | NOT NULL |
| is_primary | `$table->boolean('is_primary')->default(false)` | boolean | NOT NULL |
| joined_at | `$table->timestampTz('joined_at')->nullable()` | timestamptz | NULL |
| notes | `$table->text('notes')->nullable()` | text | NULL |
| created_by | `$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| updated_by | `$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| farm_id | farms.id | CASCADE |
| user_id | users.id | CASCADE |
| created_by | users.id | SET NULL |
| updated_by | users.id | SET NULL |

**UNIQUE Constraints**:
```php
$table->unique(['farm_id', 'user_id']); // مستخدم = عضوية واحدة لكل مدجنة
```

**CHECK Constraints**:
```sql
ALTER TABLE farm_users ADD CONSTRAINT chk_farm_users_status
    CHECK (status IN ('active', 'inactive'));
```

> **`role` محذوف**: كان `unique(['farm_id', 'user_id', 'role'])` — تغيّر إلى `unique(['farm_id', 'user_id'])`.
> لا `CHECK` على role لأنه لم يعد موجوداً هنا.
> للحصول على دور المستخدم داخل مدجنة:
> ```php
> setPermissionsTeamId($farmId);
> $user->getRoleNames(); // ['farm_admin'] أو ['worker'] إلخ
> ```

---

### 3.6 `item_types`

**ترتيب**: 6 — يعتمد على: `farms` (nullable — يدعم أصناف عامة للنظام)

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| farm_id | `$table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete()` | bigint | NULL, FK |
| name | `$table->string('name', 150)` | varchar(150) | NOT NULL |
| code | `$table->string('code', 50)->nullable()` | varchar(50) | NULL |
| is_system | `$table->boolean('is_system')->default(false)` | boolean | NOT NULL |
| is_active | `$table->boolean('is_active')->default(true)` | boolean | NOT NULL |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| farm_id | farms.id | SET NULL |

**ملاحظة**: `is_system = true` يعني الصنف يتبع للنظام لا لمدجنة محددة، وفي هذه الحالة `farm_id IS NULL`.

---

### 3.7 `warehouses`

**ترتيب**: 7 — يعتمد على: `farms`

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| farm_id | `$table->foreignId('farm_id')->constrained('farms')->restrictOnDelete()` | bigint | NOT NULL, FK |
| name | `$table->string('name', 150)` | varchar(150) | NOT NULL |
| location | `$table->string('location')->nullable()` | varchar(255) | NULL |
| is_active | `$table->boolean('is_active')->default(true)` | boolean | NOT NULL |
| notes | `$table->text('notes')->nullable()` | text | NULL |
| created_by | `$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| updated_by | `$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| farm_id | farms.id | RESTRICT |
| created_by | users.id | SET NULL |
| updated_by | users.id | SET NULL |

---

### 3.8 `items`

**ترتيب**: 8 — يعتمد على: `farms`, `item_types`

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| farm_id | `$table->foreignId('farm_id')->constrained('farms')->restrictOnDelete()` | bigint | NOT NULL, FK |
| item_type_id | `$table->foreignId('item_type_id')->constrained('item_types')->restrictOnDelete()` | bigint | NOT NULL, FK |
| name | `$table->string('name', 150)` | varchar(150) | NOT NULL |
| input_unit | `$table->string('input_unit', 50)` | varchar(50) | NOT NULL |
| unit_value | `$table->decimal('unit_value', 14, 3)` | numeric(14,3) | NOT NULL |
| content_unit | `$table->string('content_unit', 50)` | varchar(50) | NOT NULL |
| minimum_stock | `$table->decimal('minimum_stock', 14, 3)->nullable()` | numeric(14,3) | NULL |
| default_cost | `$table->decimal('default_cost', 14, 4)->nullable()` | numeric(14,4) | NULL |
| status | `$table->string('status', 30)->default('active')` | varchar(30) | NOT NULL |
| notes | `$table->text('notes')->nullable()` | text | NULL |
| created_by | `$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| updated_by | `$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| farm_id | farms.id | RESTRICT |
| item_type_id | item_types.id | RESTRICT |
| created_by | users.id | SET NULL |
| updated_by | users.id | SET NULL |

**UNIQUE Constraints**:
```php
$table->unique(['farm_id', 'name']);
```

**CHECK Constraints**:
```sql
ALTER TABLE items ADD CONSTRAINT chk_items_unit_value
    CHECK (unit_value > 0);
ALTER TABLE items ADD CONSTRAINT chk_items_status
    CHECK (status IN ('active', 'inactive'));
```

---

### 3.9 `warehouse_items`

**ترتيب**: 9 — يعتمد على: `farms`, `warehouses`, `items`

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| farm_id | `$table->foreignId('farm_id')->constrained('farms')->restrictOnDelete()` | bigint | NOT NULL, FK |
| warehouse_id | `$table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete()` | bigint | NOT NULL, FK |
| item_id | `$table->foreignId('item_id')->constrained('items')->restrictOnDelete()` | bigint | NOT NULL, FK |
| current_quantity | `$table->decimal('current_quantity', 14, 3)->default(0)` | numeric(14,3) | NOT NULL |
| average_cost | `$table->decimal('average_cost', 14, 4)->nullable()` | numeric(14,4) | NULL |
| last_in_at | `$table->timestampTz('last_in_at')->nullable()` | timestamptz | NULL |
| last_out_at | `$table->timestampTz('last_out_at')->nullable()` | timestamptz | NULL |
| status | `$table->string('status', 30)->nullable()` | varchar(30) | NULL |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| farm_id | farms.id | RESTRICT |
| warehouse_id | warehouses.id | RESTRICT |
| item_id | items.id | RESTRICT |

**UNIQUE Constraints**:
```php
$table->unique(['warehouse_id', 'item_id']);
```

---

### 3.10 `flocks`

**ترتيب**: 10 — يعتمد على: `farms`

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| farm_id | `$table->foreignId('farm_id')->constrained('farms')->restrictOnDelete()` | bigint | NOT NULL, FK |
| name | `$table->string('name', 190)` | varchar(190) | NOT NULL |
| status | `$table->string('status', 30)->default('draft')` | varchar(30) | NOT NULL |
| start_date | `$table->date('start_date')` | date | NOT NULL |
| close_date | `$table->date('close_date')->nullable()` | date | NULL |
| initial_count | `$table->integer('initial_count')` | integer | NOT NULL |
| current_age_days | `$table->integer('current_age_days')->nullable()` | integer | NULL |
| notes | `$table->text('notes')->nullable()` | text | NULL |
| created_by | `$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| updated_by | `$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| farm_id | farms.id | RESTRICT |
| created_by | users.id | SET NULL |
| updated_by | users.id | SET NULL |

**CHECK Constraints**:
```sql
ALTER TABLE flocks ADD CONSTRAINT chk_flocks_initial_count
    CHECK (initial_count > 0);
ALTER TABLE flocks ADD CONSTRAINT chk_flocks_status
    CHECK (status IN ('draft', 'active', 'closed', 'cancelled'));
ALTER TABLE flocks ADD CONSTRAINT chk_flocks_close_date
    CHECK (close_date IS NULL OR close_date >= start_date);
```

**Partial Unique Index** (PostgreSQL-specific — Raw SQL في migration):
```sql
CREATE UNIQUE INDEX uidx_flocks_one_active_per_farm
    ON flocks (farm_id)
    WHERE status = 'active';
```
> هذا القيد يضمن وجود فوج نشط واحد فقط لكل مدجنة — لا يمكن تحقيقه إلا بـ partial index.

---

### 3.11 `inventory_transactions`

**ترتيب**: 11 — يعتمد على: `farms`, `warehouses`, `items`, `flocks`

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| farm_id | `$table->foreignId('farm_id')->constrained('farms')->restrictOnDelete()` | bigint | NOT NULL, FK |
| warehouse_id | `$table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete()` | bigint | NOT NULL, FK |
| item_id | `$table->foreignId('item_id')->constrained('items')->restrictOnDelete()` | bigint | NOT NULL, FK |
| flock_id | `$table->foreignId('flock_id')->nullable()->constrained('flocks')->nullOnDelete()` | bigint | NULL, FK |
| transaction_date | `$table->date('transaction_date')` | date | NOT NULL |
| transaction_type | `$table->string('transaction_type', 30)` | varchar(30) | NOT NULL |
| direction | `$table->string('direction', 10)` | varchar(10) | NOT NULL |
| source_module | `$table->string('source_module', 50)->nullable()` | varchar(50) | NULL |
| original_quantity | `$table->decimal('original_quantity', 14, 3)->nullable()` | numeric(14,3) | NULL |
| computed_quantity | `$table->decimal('computed_quantity', 14, 3)` | numeric(14,3) | NOT NULL |
| unit_price | `$table->decimal('unit_price', 14, 4)->nullable()` | numeric(14,4) | NULL |
| total_amount | `$table->decimal('total_amount', 14, 2)->nullable()` | numeric(14,2) | NULL |
| payment_status | `$table->string('payment_status', 30)->nullable()` | varchar(30) | NULL |
| supplier_name | `$table->string('supplier_name', 190)->nullable()` | varchar(190) | NULL |
| invoice_no | `$table->string('invoice_no', 100)->nullable()` | varchar(100) | NULL |
| invoice_attachment_path | `$table->string('invoice_attachment_path')->nullable()` | varchar(255) | NULL |
| reference_no | `$table->string('reference_no', 100)->nullable()` | varchar(100) | NULL |
| notes | `$table->text('notes')->nullable()` | text | NULL |
| created_by | `$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| updated_by | `$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| farm_id | farms.id | RESTRICT |
| warehouse_id | warehouses.id | RESTRICT |
| item_id | items.id | RESTRICT |
| flock_id | flocks.id | SET NULL |
| created_by | users.id | SET NULL |
| updated_by | users.id | SET NULL |

**CHECK Constraints**:
```sql
ALTER TABLE inventory_transactions ADD CONSTRAINT chk_inv_txn_type
    CHECK (transaction_type IN ('in', 'out', 'adjustment', 'return'));
ALTER TABLE inventory_transactions ADD CONSTRAINT chk_inv_txn_direction
    CHECK (direction IN ('in', 'out'));
ALTER TABLE inventory_transactions ADD CONSTRAINT chk_inv_txn_payment_status
    CHECK (payment_status IS NULL OR payment_status IN ('paid', 'debt'));
ALTER TABLE inventory_transactions ADD CONSTRAINT chk_inv_txn_quantity
    CHECK (computed_quantity > 0);
```

---

### 3.12 `flock_mortalities`

**ترتيب**: 12 — يعتمد على: `farms`, `flocks`, `users`

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| farm_id | `$table->foreignId('farm_id')->constrained('farms')->restrictOnDelete()` | bigint | NOT NULL, FK |
| flock_id | `$table->foreignId('flock_id')->constrained('flocks')->restrictOnDelete()` | bigint | NOT NULL, FK |
| entry_date | `$table->date('entry_date')` | date | NOT NULL |
| quantity | `$table->integer('quantity')` | integer | NOT NULL |
| reason | `$table->string('reason', 190)->nullable()` | varchar(190) | NULL |
| notes | `$table->text('notes')->nullable()` | text | NULL |
| worker_id | `$table->foreignId('worker_id')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| editable_until | `$table->timestampTz('editable_until')->nullable()` | timestamptz | NULL |
| created_by | `$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| updated_by | `$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| farm_id | farms.id | RESTRICT |
| flock_id | flocks.id | RESTRICT |
| worker_id | users.id | SET NULL |
| created_by | users.id | SET NULL |
| updated_by | users.id | SET NULL |

**CHECK Constraints**:
```sql
ALTER TABLE flock_mortalities ADD CONSTRAINT chk_mortality_quantity
    CHECK (quantity > 0);
```

---

### 3.13 `flock_feed_logs`

**ترتيب**: 13 — يعتمد على: `farms`, `flocks`, `items`, `inventory_transactions`, `users`

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| farm_id | `$table->foreignId('farm_id')->constrained('farms')->restrictOnDelete()` | bigint | NOT NULL, FK |
| flock_id | `$table->foreignId('flock_id')->constrained('flocks')->restrictOnDelete()` | bigint | NOT NULL, FK |
| item_id | `$table->foreignId('item_id')->constrained('items')->restrictOnDelete()` | bigint | NOT NULL, FK |
| entry_date | `$table->date('entry_date')` | date | NOT NULL |
| quantity | `$table->decimal('quantity', 14, 3)` | numeric(14,3) | NOT NULL |
| unit_label | `$table->string('unit_label', 50)->nullable()` | varchar(50) | NULL |
| notes | `$table->text('notes')->nullable()` | text | NULL |
| worker_id | `$table->foreignId('worker_id')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| inventory_transaction_id | `$table->foreignId('inventory_transaction_id')->nullable()->constrained('inventory_transactions')->nullOnDelete()` | bigint | NULL, FK |
| editable_until | `$table->timestampTz('editable_until')->nullable()` | timestamptz | NULL |
| created_by | `$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| updated_by | `$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| farm_id | farms.id | RESTRICT |
| flock_id | flocks.id | RESTRICT |
| item_id | items.id | RESTRICT |
| inventory_transaction_id | inventory_transactions.id | SET NULL |
| worker_id | users.id | SET NULL |
| created_by | users.id | SET NULL |
| updated_by | users.id | SET NULL |

**CHECK Constraints**:
```sql
ALTER TABLE flock_feed_logs ADD CONSTRAINT chk_feed_log_quantity
    CHECK (quantity > 0);
```

---

### 3.14 `flock_medicines`

**ترتيب**: 14 — نفس هيكل `flock_feed_logs` تماماً

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| farm_id | `$table->foreignId('farm_id')->constrained('farms')->restrictOnDelete()` | bigint | NOT NULL, FK |
| flock_id | `$table->foreignId('flock_id')->constrained('flocks')->restrictOnDelete()` | bigint | NOT NULL, FK |
| item_id | `$table->foreignId('item_id')->constrained('items')->restrictOnDelete()` | bigint | NOT NULL, FK |
| entry_date | `$table->date('entry_date')` | date | NOT NULL |
| quantity | `$table->decimal('quantity', 14, 3)` | numeric(14,3) | NOT NULL |
| unit_label | `$table->string('unit_label', 50)->nullable()` | varchar(50) | NULL |
| notes | `$table->text('notes')->nullable()` | text | NULL |
| worker_id | `$table->foreignId('worker_id')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| inventory_transaction_id | `$table->foreignId('inventory_transaction_id')->nullable()->constrained('inventory_transactions')->nullOnDelete()` | bigint | NULL, FK |
| editable_until | `$table->timestampTz('editable_until')->nullable()` | timestamptz | NULL |
| created_by | `$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| updated_by | `$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**: نفس `flock_feed_logs`

**CHECK Constraints**:
```sql
ALTER TABLE flock_medicines ADD CONSTRAINT chk_medicine_log_quantity
    CHECK (quantity > 0);
```

---

### 3.15 `flock_water_logs`

**ترتيب**: 15 — يعتمد على: `farms`, `flocks`, `users`

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| farm_id | `$table->foreignId('farm_id')->constrained('farms')->restrictOnDelete()` | bigint | NOT NULL, FK |
| flock_id | `$table->foreignId('flock_id')->constrained('flocks')->restrictOnDelete()` | bigint | NOT NULL, FK |
| entry_date | `$table->date('entry_date')` | date | NOT NULL |
| quantity | `$table->decimal('quantity', 14, 3)->nullable()` | numeric(14,3) | NULL |
| unit_label | `$table->string('unit_label', 50)->nullable()` | varchar(50) | NULL |
| notes | `$table->text('notes')->nullable()` | text | NULL |
| worker_id | `$table->foreignId('worker_id')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| editable_until | `$table->timestampTz('editable_until')->nullable()` | timestamptz | NULL |
| created_by | `$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| updated_by | `$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| farm_id | farms.id | RESTRICT |
| flock_id | flocks.id | RESTRICT |
| worker_id | users.id | SET NULL |
| created_by | users.id | SET NULL |
| updated_by | users.id | SET NULL |

---

### 3.16 `flock_notes`

**ترتيب**: 16 — يعتمد على: `farms`, `flocks`, `users`

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| farm_id | `$table->foreignId('farm_id')->constrained('farms')->restrictOnDelete()` | bigint | NOT NULL, FK |
| flock_id | `$table->foreignId('flock_id')->constrained('flocks')->restrictOnDelete()` | bigint | NOT NULL, FK |
| note_type | `$table->string('note_type', 50)->default('general')` | varchar(50) | NOT NULL |
| note_text | `$table->text('note_text')` | text | NOT NULL |
| entry_date | `$table->date('entry_date')->nullable()` | date | NULL |
| worker_id | `$table->foreignId('worker_id')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| created_by | `$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| updated_by | `$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| farm_id | farms.id | RESTRICT |
| flock_id | flocks.id | RESTRICT |
| worker_id | users.id | SET NULL |
| created_by | users.id | SET NULL |
| updated_by | users.id | SET NULL |

**CHECK Constraints**:
```sql
ALTER TABLE flock_notes ADD CONSTRAINT chk_flock_note_type
    CHECK (note_type IN ('general', 'instruction', 'operational', 'alert'));
```

---

### 3.17 `expense_categories`

**ترتيب**: 17 — يعتمد على: `farms` (nullable)

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| farm_id | `$table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete()` | bigint | NULL, FK |
| name | `$table->string('name', 150)` | varchar(150) | NOT NULL |
| code | `$table->string('code', 50)->nullable()` | varchar(50) | NULL |
| is_system | `$table->boolean('is_system')->default(false)` | boolean | NOT NULL |
| is_active | `$table->boolean('is_active')->default(true)` | boolean | NOT NULL |
| created_by | `$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| updated_by | `$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| farm_id | farms.id | SET NULL |
| created_by | users.id | SET NULL |
| updated_by | users.id | SET NULL |

---

### 3.18 `expenses`

**ترتيب**: 18 — يعتمد على: `farms`, `flocks`, `expense_categories`, `inventory_transactions`, `users`

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| farm_id | `$table->foreignId('farm_id')->constrained('farms')->restrictOnDelete()` | bigint | NOT NULL, FK |
| flock_id | `$table->foreignId('flock_id')->nullable()->constrained('flocks')->nullOnDelete()` | bigint | NULL, FK |
| expense_category_id | `$table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete()` | bigint | NOT NULL, FK |
| entry_date | `$table->date('entry_date')` | date | NOT NULL |
| expense_type | `$table->string('expense_type', 50)->nullable()` | varchar(50) | NULL |
| quantity | `$table->decimal('quantity', 14, 3)->nullable()` | numeric(14,3) | NULL |
| unit_price | `$table->decimal('unit_price', 14, 4)->nullable()` | numeric(14,4) | NULL |
| total_amount | `$table->decimal('total_amount', 14, 2)` | numeric(14,2) | NOT NULL |
| paid_amount | `$table->decimal('paid_amount', 14, 2)->default(0)` | numeric(14,2) | NOT NULL |
| remaining_amount | `$table->decimal('remaining_amount', 14, 2)->default(0)` | numeric(14,2) | NOT NULL |
| payment_status | `$table->string('payment_status', 30)->default('paid')` | varchar(30) | NOT NULL |
| reference_no | `$table->string('reference_no', 100)->nullable()` | varchar(100) | NULL |
| description | `$table->string('description')->nullable()` | varchar(255) | NULL |
| notes | `$table->text('notes')->nullable()` | text | NULL |
| attachment_path | `$table->string('attachment_path')->nullable()` | varchar(255) | NULL |
| linked_inventory_transaction_id | `$table->foreignId('linked_inventory_transaction_id')->nullable()->constrained('inventory_transactions')->nullOnDelete()` | bigint | NULL, FK |
| worker_id | `$table->foreignId('worker_id')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| created_by | `$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| updated_by | `$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| farm_id | farms.id | RESTRICT |
| flock_id | flocks.id | SET NULL |
| expense_category_id | expense_categories.id | RESTRICT |
| linked_inventory_transaction_id | inventory_transactions.id | SET NULL |
| worker_id | users.id | SET NULL |
| created_by | users.id | SET NULL |
| updated_by | users.id | SET NULL |

**CHECK Constraints**:
```sql
ALTER TABLE expenses ADD CONSTRAINT chk_expenses_total_amount
    CHECK (total_amount >= 0);
ALTER TABLE expenses ADD CONSTRAINT chk_expenses_paid_amount
    CHECK (paid_amount >= 0);
ALTER TABLE expenses ADD CONSTRAINT chk_expenses_remaining_amount
    CHECK (remaining_amount >= 0);
ALTER TABLE expenses ADD CONSTRAINT chk_expenses_payment_status
    CHECK (payment_status IN ('paid', 'partial', 'debt'));
```

---

### 3.19 `sales`

**ترتيب**: 19 — يعتمد على: `farms`, `flocks`, `users`

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| farm_id | `$table->foreignId('farm_id')->constrained('farms')->restrictOnDelete()` | bigint | NOT NULL, FK |
| flock_id | `$table->foreignId('flock_id')->constrained('flocks')->restrictOnDelete()` | bigint | NOT NULL, FK |
| sale_date | `$table->date('sale_date')` | date | NOT NULL |
| reference_no | `$table->string('reference_no', 100)->nullable()` | varchar(100) | NULL |
| buyer_name | `$table->string('buyer_name', 190)->nullable()` | varchar(190) | NULL |
| invoice_attachment_path | `$table->string('invoice_attachment_path')->nullable()` | varchar(255) | NULL |
| gross_amount | `$table->decimal('gross_amount', 14, 2)` | numeric(14,2) | NOT NULL |
| discount_amount | `$table->decimal('discount_amount', 14, 2)->default(0)` | numeric(14,2) | NOT NULL |
| net_amount | `$table->decimal('net_amount', 14, 2)` | numeric(14,2) | NOT NULL |
| received_amount | `$table->decimal('received_amount', 14, 2)->default(0)` | numeric(14,2) | NOT NULL |
| remaining_amount | `$table->decimal('remaining_amount', 14, 2)->default(0)` | numeric(14,2) | NOT NULL |
| payment_status | `$table->string('payment_status', 30)->default('paid')` | varchar(30) | NOT NULL |
| notes | `$table->text('notes')->nullable()` | text | NULL |
| created_by | `$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| updated_by | `$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| farm_id | farms.id | RESTRICT |
| flock_id | flocks.id | RESTRICT |
| created_by | users.id | SET NULL |
| updated_by | users.id | SET NULL |

**CHECK Constraints**:
```sql
ALTER TABLE sales ADD CONSTRAINT chk_sales_payment_status
    CHECK (payment_status IN ('paid', 'partial', 'debt'));
ALTER TABLE sales ADD CONSTRAINT chk_sales_net_amount
    CHECK (net_amount >= 0);
ALTER TABLE sales ADD CONSTRAINT chk_sales_gross_amount
    CHECK (gross_amount >= 0);
ALTER TABLE sales ADD CONSTRAINT chk_sales_discount
    CHECK (discount_amount >= 0);
```

---

### 3.20 `sale_items`

**ترتيب**: 20 — يعتمد على: `sales`, `farms`, `flocks`

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| sale_id | `$table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete()` | bigint | NOT NULL, FK |
| farm_id | `$table->foreignId('farm_id')->constrained('farms')->restrictOnDelete()` | bigint | NOT NULL, FK |
| flock_id | `$table->foreignId('flock_id')->constrained('flocks')->restrictOnDelete()` | bigint | NOT NULL, FK |
| birds_count | `$table->integer('birds_count')` | integer | NOT NULL |
| total_weight_kg | `$table->decimal('total_weight_kg', 14, 3)` | numeric(14,3) | NOT NULL |
| avg_weight_kg | `$table->decimal('avg_weight_kg', 10, 3)->nullable()` | numeric(10,3) | NULL |
| unit_price_per_kg | `$table->decimal('unit_price_per_kg', 14, 4)->nullable()` | numeric(14,4) | NULL |
| line_total | `$table->decimal('line_total', 14, 2)` | numeric(14,2) | NOT NULL |
| notes | `$table->text('notes')->nullable()` | text | NULL |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| sale_id | sales.id | CASCADE |
| farm_id | farms.id | RESTRICT |
| flock_id | flocks.id | RESTRICT |

**CHECK Constraints**:
```sql
ALTER TABLE sale_items ADD CONSTRAINT chk_sale_items_birds_count
    CHECK (birds_count > 0);
ALTER TABLE sale_items ADD CONSTRAINT chk_sale_items_weight
    CHECK (total_weight_kg > 0);
```

---

### 3.21 `partners`

**ترتيب**: 21 — يعتمد على: `farms`, `users`

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| farm_id | `$table->foreignId('farm_id')->constrained('farms')->restrictOnDelete()` | bigint | NOT NULL, FK |
| user_id | `$table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| name | `$table->string('name', 150)` | varchar(150) | NOT NULL |
| email | `$table->string('email', 190)->nullable()` | varchar(190) | NULL |
| whatsapp | `$table->string('whatsapp', 30)->nullable()` | varchar(30) | NULL |
| status | `$table->string('status', 30)->default('active')` | varchar(30) | NOT NULL |
| notes | `$table->text('notes')->nullable()` | text | NULL |
| created_by | `$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| updated_by | `$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| farm_id | farms.id | RESTRICT |
| user_id | users.id | SET NULL |
| created_by | users.id | SET NULL |
| updated_by | users.id | SET NULL |

**CHECK Constraints**:
```sql
ALTER TABLE partners ADD CONSTRAINT chk_partners_status
    CHECK (status IN ('active', 'inactive'));
```

---

### 3.22 `farm_partner_shares`

**ترتيب**: 22 — يعتمد على: `farms`, `partners`

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| farm_id | `$table->foreignId('farm_id')->constrained('farms')->restrictOnDelete()` | bigint | NOT NULL, FK |
| partner_id | `$table->foreignId('partner_id')->constrained('partners')->restrictOnDelete()` | bigint | NOT NULL, FK |
| share_percent | `$table->decimal('share_percent', 5, 2)->default(0)` | numeric(5,2) | NOT NULL |
| is_active | `$table->boolean('is_active')->default(true)` | boolean | NOT NULL |
| effective_from | `$table->date('effective_from')->nullable()` | date | NULL |
| effective_to | `$table->date('effective_to')->nullable()` | date | NULL |
| notes | `$table->text('notes')->nullable()` | text | NULL |
| created_by | `$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| updated_by | `$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| farm_id | farms.id | RESTRICT |
| partner_id | partners.id | RESTRICT |
| created_by | users.id | SET NULL |
| updated_by | users.id | SET NULL |

**CHECK Constraints**:
```sql
ALTER TABLE farm_partner_shares ADD CONSTRAINT chk_share_percent_range
    CHECK (share_percent >= 0 AND share_percent <= 100);
ALTER TABLE farm_partner_shares ADD CONSTRAINT chk_share_effective_dates
    CHECK (effective_to IS NULL OR effective_to >= effective_from);
```

> **قاعدة العمل**: مجموع `share_percent` لشركاء نفس المدجنة لا يتجاوز 100%  
> تُنفَّذ في Application Layer (Service/Action) — لا يمكن تحقيقها بـ CHECK بكفاءة.

---

### 3.23 `partner_transactions`

**ترتيب**: 23 — يعتمد على: `farms`, `partners`, `flocks`, `users`

| العمود | Laravel Method | PostgreSQL Type | قيود |
|---|---|---|---|
| id | `$table->id()` | bigserial | PK |
| farm_id | `$table->foreignId('farm_id')->constrained('farms')->restrictOnDelete()` | bigint | NOT NULL, FK |
| partner_id | `$table->foreignId('partner_id')->constrained('partners')->restrictOnDelete()` | bigint | NOT NULL, FK |
| flock_id | `$table->foreignId('flock_id')->nullable()->constrained('flocks')->nullOnDelete()` | bigint | NULL, FK |
| transaction_date | `$table->date('transaction_date')` | date | NOT NULL |
| transaction_type | `$table->string('transaction_type', 30)` | varchar(30) | NOT NULL |
| amount | `$table->decimal('amount', 14, 2)` | numeric(14,2) | NOT NULL |
| description | `$table->string('description')->nullable()` | varchar(255) | NULL |
| reference_no | `$table->string('reference_no', 100)->nullable()` | varchar(100) | NULL |
| notes | `$table->text('notes')->nullable()` | text | NULL |
| created_by | `$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| updated_by | `$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()` | bigint | NULL, FK |
| created_at / updated_at | `$table->timestampsTz()` | timestamptz | NOT NULL |

**FK Map**:
| العمود | يشير إلى | ON DELETE |
|---|---|---|
| farm_id | farms.id | RESTRICT |
| partner_id | partners.id | RESTRICT |
| flock_id | flocks.id | SET NULL |
| created_by | users.id | SET NULL |
| updated_by | users.id | SET NULL |

**CHECK Constraints**:
```sql
ALTER TABLE partner_transactions ADD CONSTRAINT chk_partner_txn_type
    CHECK (transaction_type IN ('deposit', 'withdraw', 'profit', 'loss', 'adjustment', 'settlement'));
```

---

## 4. خريطة ON DELETE الإجمالية

| النمط | يُطبَّق عند |
|---|---|
| `CASCADE` | الأبناء بلا معنى بدون الأب: `farm_users`, `user_settings`, `sale_items` |
| `RESTRICT` | لا يُحذف الأب إذا كان له أبناء: `farms→flocks`, `flocks→logs`, `items→transactions` |
| `SET NULL` | العلاقة اختيارية أو من النوع audit: `created_by`, `updated_by`, `worker_id`, `flock_id` على transactions |

---

## 5. خطة الفهارس الكاملة

### 5.1 فهارس B-tree القياسية

```sql
-- Farm Boundary (tenant isolation)
CREATE INDEX idx_farm_users_farm_user         ON farm_users(farm_id, user_id);
CREATE INDEX idx_flocks_farm_status_date      ON flocks(farm_id, status, start_date);

-- Flock Daily Operations
CREATE INDEX idx_mortalities_flock_date       ON flock_mortalities(flock_id, entry_date);
CREATE INDEX idx_feed_logs_flock_date         ON flock_feed_logs(flock_id, entry_date);
CREATE INDEX idx_medicines_flock_date         ON flock_medicines(flock_id, entry_date);
CREATE INDEX idx_water_logs_flock_date        ON flock_water_logs(flock_id, entry_date);
CREATE INDEX idx_flock_notes_flock_date       ON flock_notes(flock_id, entry_date);

-- Accounting
CREATE INDEX idx_expenses_farm_flock_date     ON expenses(farm_id, flock_id, entry_date);
CREATE INDEX idx_expenses_category            ON expenses(expense_category_id);
CREATE INDEX idx_expenses_payment_status      ON expenses(payment_status);

-- Sales
CREATE INDEX idx_sales_farm_flock_date        ON sales(farm_id, flock_id, sale_date);
CREATE INDEX idx_sale_items_sale              ON sale_items(sale_id);

-- Inventory
CREATE INDEX idx_inv_txn_farm_item_date       ON inventory_transactions(farm_id, item_id, transaction_date);
CREATE INDEX idx_inv_txn_flock                ON inventory_transactions(flock_id) WHERE flock_id IS NOT NULL;
CREATE INDEX idx_warehouse_items_farm_wh      ON warehouse_items(farm_id, warehouse_id, item_id);

-- Partners
CREATE INDEX idx_partner_txn_farm_partner     ON partner_transactions(farm_id, partner_id, transaction_date);
CREATE INDEX idx_partner_shares_farm          ON farm_partner_shares(farm_id, is_active);

-- Auth / Lookup
CREATE INDEX idx_users_status                 ON users(status);
CREATE INDEX idx_reg_requests_status          ON registration_requests(status);
```

### 5.2 Partial Indexes (PostgreSQL-specific)

```sql
-- قيد الفوج النشط الواحد لكل مدجنة (الأهم)
CREATE UNIQUE INDEX uidx_flocks_one_active_per_farm
    ON flocks (farm_id)
    WHERE status = 'active';

-- فهرس سريع للمخزون المنخفض
CREATE INDEX idx_warehouse_items_low_stock
    ON warehouse_items(farm_id, item_id)
    WHERE current_quantity > 0;

-- الديون والمستحقات فقط
CREATE INDEX idx_expenses_unpaid
    ON expenses(farm_id, entry_date)
    WHERE payment_status IN ('partial', 'debt');

CREATE INDEX idx_sales_unpaid
    ON sales(farm_id, sale_date)
    WHERE payment_status IN ('partial', 'debt');

-- العمال الحاملون لـ editable_until فعّال
CREATE INDEX idx_feed_logs_editable
    ON flock_feed_logs(flock_id, editable_until)
    WHERE editable_until IS NOT NULL;

CREATE INDEX idx_medicine_logs_editable
    ON flock_medicines(flock_id, editable_until)
    WHERE editable_until IS NOT NULL;
```

---

## 6. قواعد العمل المُنفَّذة على مستوى Application

هذه القواعد **لا تُنفَّذ في DB schema** بل في Laravel Actions/Services/Policies/Validation:

| القاعدة | طبقة التنفيذ |
|---|---|
| الدخول لحساب نشط ومرتبط بمدجنة معتمدة فقط | Auth Middleware + Policy |
| العامل يعدّل سجله فقط خلال 15 دقيقة (`editable_until`) | Policy + Action |
| لا إدخالات تشغيلية على فوج مغلق أو ملغى | Action Validation |
| لا إدخال يومي بتاريخ سابق أو لاحق لليوم | Form Request Validation |
| لا بيع بعدد أكبر من العدد الحي | Action: `initial_count - mortalities - sold` |
| العدد الحي = `initial_count - Σmortalities - Σsold` | Computed في Service/Query |
| مجموع حصص الشركاء ≤ 100% | Action Validation |
| العلف/الدواء من أصناف المخزون فقط | FK على `item_id` + item_type check في Action |
| عند تسجيل علف/دواء → إنشاء `inventory_transaction` مرتبطة | Action (atomic transaction) |
| `email IS NOT NULL OR whatsapp IS NOT NULL` في users | Form Request Validation |

---

## 7. ملاحظات تقنية لمرحلة التنفيذ

### 7.1 Timestamps
```php
// في كل migration:
$table->timestampsTz(); // بدلاً من $table->timestamps()
// وللأعمدة الفردية:
$table->timestampTz('editable_until')->nullable();
$table->timestampTz('last_login_at')->nullable();
```

### 7.2 Check Constraints في Laravel
```php
// Laravel 11 يدعم DB::statement مباشرة في migration up():
DB::statement("ALTER TABLE flocks ADD CONSTRAINT chk_flocks_initial_count CHECK (initial_count > 0)");

// أو عبر rawIndex في بعض الإصدارات:
// يُفضَّل DB::statement للـ CHECK constraints لضمان التوافق
```

### 7.3 Partial Index في Laravel
```php
// في migration up():
DB::statement("
    CREATE UNIQUE INDEX uidx_flocks_one_active_per_farm
    ON flocks (farm_id)
    WHERE status = 'active'
");

// في migration down():
DB::statement("DROP INDEX IF EXISTS uidx_flocks_one_active_per_farm");
```

### 7.4 Decimal Precision
```
numeric(14,2)  → للمبالغ المالية (حتى 999,999,999,999.99)
numeric(14,3)  → للأوزان والكميات
numeric(14,4)  → لأسعار الوحدة (دقة أعلى)
numeric(5,2)   → للنسب المئوية (0.00 → 100.00)
```

### 7.5 Audit Columns Pattern

**جميع الجداول التشغيلية** (ما عدا `users`) تحمل النمط التالي:
```php
$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
$table->timestampsTz();
```

**استثناء `users` جدول نفسه**:
```php
// لا ->constrained() هنا — self-referential في نفس migration
$table->unsignedBigInteger('created_by')->nullable();
$table->unsignedBigInteger('updated_by')->nullable();
$table->timestampsTz();
```

### 7.6 Spatie Teams — نمط الاستخدام

```php
// قبل أي عملية role-scoped:
setPermissionsTeamId($farmId);

// تعيين دور داخل مدجنة:
setPermissionsTeamId($farm->id);
$user->assignRole('farm_admin');

// التحقق من دور داخل مدجنة:
setPermissionsTeamId($farm->id);
$user->hasRole('farm_admin');  // true فقط لهذه المدجنة

// super_admin بدون team context (global):
app(PermissionRegistrar::class)->setPermissionsTeamId(null);
$user->assignRole('super_admin');

// التحقق من عضوية في مدجنة (عبر farm_users):
$isMember = FarmUser::where('farm_id', $farmId)
    ->where('user_id', $userId)
    ->where('status', 'active')
    ->exists();
```

> `farm_users` + Spatie يعملان معاً: Spatie للدور، `farm_users` للعضوية.

---

## 8. ملخص الجداول

### 8.1 جداول Infrastructure + Authorization (موجودة)

| الملف | الجداول | الحالة |
|---|---|---|
| `0001_01_01_000000` | `users`*, `password_reset_tokens`, `sessions` | إعادة كتابة `users` فقط |
| `0001_01_01_000001` | `cache`, `cache_locks` | KEEP |
| `0001_01_01_000002` | `jobs`, `job_batches`, `failed_jobs` | KEEP |
| `2026_04_11_015745` | `permissions`, `roles`, `model_has_*`, `role_has_permissions` | KEEP — يشترط teams config |

### 8.2 جداول المشروع (22 migration مخصص)

| # | الجدول | الدومين | عدد الأعمدة | تغيير عن v1 |
|---|---|---|---|---|
| 1 | users | Auth | 14 | +remember_token, created_by, updated_by (بدون FK) |
| 2 | registration_requests | Auth | 13 | — |
| 3 | farms | Core | 10 | — |
| 4 | user_settings | Auth | 7 | — |
| 5 | farm_users | Core | **11** | **حذف `role`** — UNIQUE تغيّر |
| 6 | item_types | Inventory | 8 | — |
| 7 | warehouses | Inventory | 9 | — |
| 8 | items | Inventory | 15 | — |
| 9 | warehouse_items | Inventory | 10 | — |
| 10 | flocks | Operations | 14 | — |
| 11 | inventory_transactions | Inventory | 22 | — |
| 12 | flock_mortalities | Operations | 14 | — |
| 13 | flock_feed_logs | Operations | 16 | — |
| 14 | flock_medicines | Operations | 16 | — |
| 15 | flock_water_logs | Operations | 13 | — |
| 16 | flock_notes | Operations | 11 | — |
| 17 | expense_categories | Accounting | 9 | — |
| 18 | expenses | Accounting | 22 | — |
| 19 | sales | Sales | 16 | — |
| 20 | sale_items | Sales | 11 | — |
| 21 | partners | Partners | 12 | — |
| 22 | farm_partner_shares | Partners | 12 | — |
| 23 | partner_transactions | Partners | 14 | — |

---

> **الخطوة التالية**: تعديل `config/permission.php` ← teams=true ← ثم تنفيذ Laravel Migrations بالترتيب المحدد في القسم 2،
> ثم بناء Eloquent Models مع العلاقات، ثم API Spec.

# تبويب "الذمم والمراجعة" — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** إضافة صفحة `/accounting` جديدة مع تبويب "الذمم والمراجعة" يعرض قائمة المراجعة (review queue) للمصروفات والمبيعات التي تحتاج متابعة مالية، مع دعم التعديل المباشر وربط إغلاق الفوج.

**Architecture:** `ReviewQueueService` يقرأ `expenses` و `sales` من DB ثم يحسب `review_reasons` في PHP (mapping بعد الاستعلام). الـ summary counts تُحسب بنفس منطق الفلترة تمامًا لضمان التطابق. صفحة `/accounting` مستقلة عن `/reports` تمامًا.

**Tech Stack:** Laravel 11 (PHP), Eloquent, Next.js 15 App Router, TanStack Query, Shadcn/ui, Tailwind CSS.

---

## ملاحظات تصميمية ثابتة

### أسماء Summary Keys (API + UI)
```
unpaid_count
partial_payment_count
missing_price_count
missing_payment_status_count
inconsistent_financial_state_count
blocking_flock_closure_count
```

### Review Reasons (داخلية)
| reason key | شرط الاكتشاف |
|---|---|
| `unpaid` | `payment_status = 'unpaid'` |
| `partial_payment` | `payment_status = 'partial'` |
| `missing_price` | أي من الشروط الثلاثة (انظر Business Rule أدناه) |
| `missing_payment_status` | `payment_status IS NULL` (legacy/transition) |
| `inconsistent_financial_state` | `paid_amount > total_amount` |
| `blocking_flock_closure` | flock نشط + (`payment_status IN ('unpaid','partial')`) **أو** `missing_price` |

### Business Rule: missing_price (نهائي)
يُعتبر السجل **ناقص السعر** إذا تحقق **أي** من الشروط التالية:

| # | الشرط | ينطبق على |
|---|---|---|
| 1 | `quantity = 0` أو `quantity IS NULL` | expenses |
| 2 | `unit_price IS NULL` أو `unit_price = 0` | expenses |
| 3 | `total_amount <= 0` أو `net_amount <= 0` | expenses + sales |

**نتائج اكتشاف missing_price:**
- ✅ يظهر في قائمة "الذمم والمراجعة"
- ✅ يُحسب في `missing_price_count`
- ✅ **يعتبر مانعًا لإغلاق الفوج** إذا كان الفوج لا يزال نشطًا (يُضاف لـ `blocking_flock_closure_count` ويمنع PUT status=closed)

### Badge Labels (UI العربي)
| reason key | label |
|---|---|
| `unpaid` | غير مدفوع |
| `partial_payment` | دفع جزئي |
| `missing_price` | ناقص السعر |
| `missing_payment_status` | ناقص حالة الدفع |
| `inconsistent_financial_state` | تناقض مالي |
| `blocking_flock_closure` | مانع إغلاق |

### فرق مهم: review inclusion vs blocking closure
- السجلات `partial`/`unpaid` تدخل قائمة المراجعة ← طبيعي، لكنها مانعة فقط إذا كان الفوج نشطًا
- السجلات `missing_price` تدخل قائمة المراجعة **وتمنع الإغلاق** دائمًا ما دام الفوج نشطًا
- لا تُدمج هذه الحالات في الكود — كل reason تُحسب باستقلالية

---

## File Map

| الملف | نوع | المسؤولية |
|---|---|---|
| `backend/app/Services/ReviewQueueService.php` | Create | جلب السجلات + حساب reasons + summary |
| `backend/app/Http/Controllers/Api/Accounting/ReviewQueueController.php` | Create | index + update endpoints |
| `backend/app/Http/Requests/Accounting/UpdateReviewItemRequest.php` | Create | validation لـ PATCH |
| `backend/routes/api.php` | Modify | إضافة accounting routes |
| `backend/app/Actions/Flock/UpdateFlockAction.php` | Modify | فحص السجلات المانعة قبل الإغلاق |
| `frontend/src/lib/api/accounting.ts` | Create | API client للـ review queue |
| `frontend/src/app/(farm)/accounting/page.tsx` | Create | صفحة المحاسبة بتبويبين |
| `frontend/src/components/accounting/ReviewQueueTab.tsx` | Create | تبويب الذمم والمراجعة (جدول + بطاقات) |
| `frontend/src/lib/roles.ts` | Modify | إضافة `/accounting` لـ farm_admin |
| `frontend/src/components/layout/MoreMenu.tsx` | Modify | إضافة رابط المحاسبة |
| `frontend/src/app/(farm)/flocks/[id]/page.tsx` | Modify | رابط "عرض السجلات المانعة" |
| `backend/tests/Feature/ReviewQueue/ReviewQueueIndexTest.php` | Create | اختبار GET |
| `backend/tests/Feature/ReviewQueue/ReviewQueueUpdateTest.php` | Create | اختبار PATCH |
| `backend/tests/Feature/Flock/FlockClosureBlockingTest.php` | Create | اختبار منع الإغلاق |

---

## Task 1: ReviewQueueService — الجلب وحساب الأسباب

**Files:**
- Create: `backend/app/Services/ReviewQueueService.php`

### API Contract المتوقع (output)
```php
// getQueue() returns:
[
  'summary' => [
    'unpaid_count' => 12,
    'partial_payment_count' => 5,
    'missing_price_count' => 2,
    'missing_payment_status_count' => 0,
    'inconsistent_financial_state_count' => 1,
    'blocking_flock_closure_count' => 3,
  ],
  'data' => [
    [
      'id'             => 'expense-45',         // composite unique key
      'type'           => 'expense',
      'record_id'      => 45,
      'flock_id'       => 3,
      'flock_name'     => 'فوج مارس',
      'flock_status'   => 'active',
      'entry_date'     => '2026-04-10',
      'description'    => 'علف',
      'total_amount'   => 5000.0,
      'paid_amount'    => 2000.0,
      'remaining_amount' => 3000.0,
      'payment_status' => 'partial',
      'unit_price'     => null,
      'review_reasons' => ['partial_payment', 'missing_price', 'blocking_flock_closure'],
    ]
  ],
  'meta' => ['total' => 18, 'current_page' => 1, 'per_page' => 20],
]
```

- [ ] **Step 1: كتابة الـ service class**

أنشئ الملف `backend/app/Services/ReviewQueueService.php`:

```php
<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Sale;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewQueueService
{
    // reasons that make a record qualify for the review queue
    private const QUALIFYING_REASONS = [
        'unpaid',
        'partial_payment',
        'missing_price',
        'missing_payment_status',
        'inconsistent_financial_state',
    ];

    /**
     * @param  array{type?:string, reason?:string, flock_id?:int, page?:int, per_page?:int}  $filters
     */
    public function getQueue(int $farmId, array $filters = []): array
    {
        $type    = $filters['type']     ?? 'all';
        $reason  = $filters['reason']   ?? null;
        $flockId = $filters['flock_id'] ?? null;
        $page    = max(1, (int) ($filters['page']    ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));

        $rows = $this->fetchRows($farmId, $type, $flockId);

        // Attach reasons then filter qualifying ones
        $rows = $rows->map(fn ($row) => $this->attachReasons($row));
        $rows = $rows->filter(fn ($row) => count($row['review_reasons']) > 0);

        // Filter by specific reason if requested
        if ($reason) {
            $rows = $rows->filter(fn ($row) => in_array($reason, $row['review_reasons']));
        }

        $rows = $rows->values();

        $summary = $this->buildSummary($rows);

        // Paginate manually after reason-filtering
        $total   = $rows->count();
        $offset  = ($page - 1) * $perPage;
        $pageItems = $rows->slice($offset, $perPage)->values();

        return [
            'summary' => $summary,
            'data'    => $pageItems->toArray(),
            'meta'    => [
                'total'        => $total,
                'current_page' => $page,
                'per_page'     => $perPage,
            ],
        ];
    }

    /**
     * Update a single record, recalculate financial fields, return fresh row with reasons.
     */
    public function updateRecord(int $farmId, string $type, int $recordId, array $data): array
    {
        if ($type === 'expense') {
            $record = Expense::where('farm_id', $farmId)->findOrFail($recordId);

            if (array_key_exists('unit_price', $data)) {
                $record->unit_price = $data['unit_price'];
                // Recalculate total if quantity exists
                if ($record->quantity !== null && $data['unit_price'] !== null) {
                    $record->total_amount = $record->quantity * $data['unit_price'];
                }
            }

            if (array_key_exists('paid_amount', $data)) {
                $record->paid_amount = $data['paid_amount'];
            }

            $record->remaining_amount = max(0, $record->total_amount - $record->paid_amount);
            $record->payment_status   = $this->derivePaymentStatus(
                $record->total_amount,
                $record->paid_amount
            );

            $record->save();

            $row = $this->normalizeExpense($record->load(['flock', 'expenseCategory']));

        } else {
            $record = Sale::where('farm_id', $farmId)->findOrFail($recordId);

            if (array_key_exists('paid_amount', $data)) {
                $record->received_amount = $data['paid_amount'];
            }

            $record->remaining_amount = max(0, $record->net_amount - $record->received_amount);
            $record->payment_status   = $this->derivePaymentStatus(
                $record->net_amount,
                $record->received_amount
            );

            $record->save();

            $row = $this->normalizeSale($record->load('flock'));
        }

        return $this->attachReasons($row);
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    private function fetchRows(int $farmId, string $type, ?int $flockId): \Illuminate\Support\Collection
    {
        $rows = collect();

        if ($type === 'all' || $type === 'expense') {
            $expenseQuery = Expense::with(['flock', 'expenseCategory'])
                ->where('farm_id', $farmId);

            if ($flockId) {
                $expenseQuery->where('flock_id', $flockId);
            }

            $expenses = $expenseQuery->get()->map(fn ($e) => $this->normalizeExpense($e));
            $rows = $rows->concat($expenses);
        }

        if ($type === 'all' || $type === 'sale') {
            $saleQuery = Sale::with('flock')
                ->where('farm_id', $farmId);

            if ($flockId) {
                $saleQuery->where('flock_id', $flockId);
            }

            $sales = $saleQuery->get()->map(fn ($s) => $this->normalizeSale($s));
            $rows  = $rows->concat($sales);
        }

        return $rows;
    }

    private function normalizeExpense(Expense $e): array
    {
        return [
            'id'              => 'expense-' . $e->id,
            'type'            => 'expense',
            'record_id'       => $e->id,
            'flock_id'        => $e->flock_id,
            'flock_name'      => $e->flock?->name,
            'flock_status'    => $e->flock?->status,
            'entry_date'      => $e->entry_date?->toDateString(),
            'description'     => $e->expenseCategory?->name ?? $e->description,
            'total_amount'    => (float) $e->total_amount,
            'paid_amount'     => (float) ($e->paid_amount ?? 0),
            'remaining_amount' => (float) ($e->remaining_amount ?? $e->total_amount),
            'payment_status'  => $e->payment_status,
            'unit_price'      => $e->unit_price !== null ? (float) $e->unit_price : null,
            'quantity'        => $e->quantity !== null ? (float) $e->quantity : null,
            'review_reasons'  => [],
        ];
    }

    private function normalizeSale(Sale $s): array
    {
        return [
            'id'              => 'sale-' . $s->id,
            'type'            => 'sale',
            'record_id'       => $s->id,
            'flock_id'        => $s->flock_id,
            'flock_name'      => $s->flock?->name,
            'flock_status'    => $s->flock?->status,
            'entry_date'      => $s->sale_date?->toDateString(),
            'description'     => $s->buyer_name ?? 'بيع',
            'total_amount'    => (float) $s->net_amount,
            'paid_amount'     => (float) ($s->received_amount ?? 0),
            'remaining_amount' => (float) ($s->remaining_amount ?? $s->net_amount),
            'payment_status'  => $s->payment_status,
            'unit_price'      => null, // sales have no unit_price concept
            'review_reasons'  => [],
        ];
    }

    private function attachReasons(array $row): array
    {
        $reasons = [];

        // ── Payment status reasons ────────────────────────────────────────────
        if ($row['payment_status'] === null) {
            $reasons[] = 'missing_payment_status';
        } elseif ($row['payment_status'] === 'unpaid') {
            $reasons[] = 'unpaid';
        } elseif ($row['payment_status'] === 'partial') {
            $reasons[] = 'partial_payment';
        }

        // ── Missing price (Business Rule — نهائي) ────────────────────────────
        // Condition 1+2: expense with no quantity or no unit_price
        $missingPrice = $row['type'] === 'expense'
            && (
                empty($row['quantity']) ||          // quantity = 0 or null
                $row['unit_price'] === null ||
                $row['unit_price'] == 0
            );

        // Condition 3: total/net amount <= 0 (applies to both expense and sale)
        if (! $missingPrice && $row['total_amount'] <= 0) {
            $missingPrice = true;
        }

        if ($missingPrice) {
            $reasons[] = 'missing_price';
        }

        // ── Inconsistent financial state ──────────────────────────────────────
        if ($row['paid_amount'] > $row['total_amount'] && $row['total_amount'] > 0) {
            $reasons[] = 'inconsistent_financial_state';
        }

        // ── Blocking flock closure ────────────────────────────────────────────
        // Blocks if flock is active AND:
        //   - payment is unpaid/partial, OR
        //   - record has missing_price (incomplete record cannot be closed over)
        $isBlockingClosure = $row['flock_status'] === 'active'
            && (
                in_array($row['payment_status'], ['unpaid', 'partial'])
                || $missingPrice
            );

        if ($isBlockingClosure) {
            $reasons[] = 'blocking_flock_closure';
        }

        $row['review_reasons'] = $reasons;

        return $row;
    }

    private function buildSummary(\Illuminate\Support\Collection $qualifyingRows): array
    {
        $summary = [
            'unpaid_count'                    => 0,
            'partial_payment_count'           => 0,
            'missing_price_count'             => 0,
            'missing_payment_status_count'    => 0,
            'inconsistent_financial_state_count' => 0,
            'blocking_flock_closure_count'    => 0,
        ];

        foreach ($qualifyingRows as $row) {
            foreach ($row['review_reasons'] as $reason) {
                match ($reason) {
                    'unpaid'                      => $summary['unpaid_count']++,
                    'partial_payment'             => $summary['partial_payment_count']++,
                    'missing_price'               => $summary['missing_price_count']++,
                    'missing_payment_status'      => $summary['missing_payment_status_count']++,
                    'inconsistent_financial_state' => $summary['inconsistent_financial_state_count']++,
                    'blocking_flock_closure'      => $summary['blocking_flock_closure_count']++,
                    default                       => null,
                };
            }
        }

        return $summary;
    }

    private function derivePaymentStatus(float $total, float $paid): string
    {
        if ($paid <= 0) return 'unpaid';
        if ($paid >= $total) return 'paid';
        return 'partial';
    }
}
```

- [ ] **Step 2: التحقق من عدم وجود أخطاء PHP**

```bash
cd backend && php artisan about 2>&1 | head -5
```

Expected: لا exit code errors (التحقق من أن الـ service يُحمَّل بدون مشاكل syntax).

- [ ] **Step 3: Commit**

```bash
git add backend/app/Services/ReviewQueueService.php
git commit -m "feat(accounting): add ReviewQueueService with reason computation and summary"
```

---

## Task 2: Controller + Request + Routes

**Files:**
- Create: `backend/app/Http/Controllers/Api/Accounting/ReviewQueueController.php`
- Create: `backend/app/Http/Requests/Accounting/UpdateReviewItemRequest.php`
- Modify: `backend/routes/api.php`

- [ ] **Step 1: إنشاء Request class**

أنشئ `backend/app/Http/Requests/Accounting/UpdateReviewItemRequest.php`:

```php
<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // type comes from route param, not body — we use it here via route()
        $type = $this->route('type');

        $rules = [
            'paid_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];

        if ($type === 'expense') {
            $rules['unit_price'] = ['sometimes', 'nullable', 'numeric', 'min:0'];
        }

        return $rules;
    }
}
```

- [ ] **Step 2: إنشاء Controller**

أنشئ `backend/app/Http/Controllers/Api/Accounting/ReviewQueueController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\UpdateReviewItemRequest;
use App\Services\ReviewQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewQueueController extends Controller
{
    public function __construct(private readonly ReviewQueueService $service) {}

    /**
     * GET /accounting/review-queue
     * Query params: type (expense|sale|all), reason, flock_id, page, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        $filters = $request->only(['type', 'reason', 'flock_id', 'page', 'per_page']);

        $result = $this->service->getQueue($farmId, $filters);

        return response()->json($result);
    }

    /**
     * PATCH /accounting/review-queue/{type}/{id}
     * type: expense | sale
     * Recalculates remaining_amount, payment_status, then review_reasons.
     */
    public function update(
        UpdateReviewItemRequest $request,
        string $type,
        int $id
    ): JsonResponse {
        if (! in_array($type, ['expense', 'sale'])) {
            return response()->json(['message' => 'النوع غير صالح'], 422);
        }

        $farmId = $request->attributes->get('farm_id');

        $updated = $this->service->updateRecord($farmId, $type, $id, $request->validated());

        return response()->json($updated);
    }
}
```

- [ ] **Step 3: إضافة Routes**

في `backend/routes/api.php`، داخل middleware group الموجود (`auth:sanctum`, `farm.scope`, `farm.active`)، أضف بعد قسم Reports:

```php
    // ── Accounting: Review Queue ───────────────────────────────────────────────
    Route::prefix('accounting')->group(function (): void {
        Route::get('review-queue',              [\App\Http\Controllers\Api\Accounting\ReviewQueueController::class, 'index']);
        Route::patch('review-queue/{type}/{id}', [\App\Http\Controllers\Api\Accounting\ReviewQueueController::class, 'update']);
    });
```

- [ ] **Step 4: التحقق من تسجيل الـ routes**

```bash
cd backend && php artisan route:list --path=accounting
```

Expected output يحتوي على:
```
GET|HEAD  api/accounting/review-queue
PATCH     api/accounting/review-queue/{type}/{id}
```

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Controllers/Api/Accounting/ReviewQueueController.php \
        backend/app/Http/Requests/Accounting/UpdateReviewItemRequest.php \
        backend/routes/api.php
git commit -m "feat(accounting): add ReviewQueueController and routes"
```

---

## Task 3: اختبارات Backend

**Files:**
- Create: `backend/tests/Feature/ReviewQueue/ReviewQueueIndexTest.php`
- Create: `backend/tests/Feature/ReviewQueue/ReviewQueueUpdateTest.php`

- [ ] **Step 1: كتابة اختبار GET**

أنشئ `backend/tests/Feature/ReviewQueue/ReviewQueueIndexTest.php`:

```php
<?php

namespace Tests\Feature\ReviewQueue;

use App\Models\Expense;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewQueueIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Farm $farm;
    private Flock $activeFlock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin       = User::factory()->create();
        $this->farm        = Farm::factory()->create();
        $this->activeFlock = Flock::factory()->create([
            'farm_id' => $this->farm->id,
            'status'  => 'active',
        ]);

        $this->farm->users()->attach($this->admin->id, ['role' => 'farm_admin']);
    }

    public function test_returns_summary_with_correct_keys(): void
    {
        $this->actingAs($this->admin)
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->getJson('/api/accounting/review-queue')
            ->assertOk()
            ->assertJsonStructure([
                'summary' => [
                    'unpaid_count',
                    'partial_payment_count',
                    'missing_price_count',
                    'missing_payment_status_count',
                    'inconsistent_financial_state_count',
                    'blocking_flock_closure_count',
                ],
                'data',
                'meta' => ['total', 'current_page', 'per_page'],
            ]);
    }

    public function test_unpaid_expense_appears_in_queue(): void
    {
        Expense::factory()->create([
            'farm_id'        => $this->farm->id,
            'flock_id'       => $this->activeFlock->id,
            'payment_status' => 'unpaid',
            'paid_amount'    => 0,
            'total_amount'   => 3000,
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->getJson('/api/accounting/review-queue')
            ->assertOk();

        $this->assertEquals(1, $response->json('summary.unpaid_count'));
        $this->assertEquals(1, $response->json('meta.total'));
    }

    public function test_blocking_flock_closure_count_matches_active_flock_unpaid(): void
    {
        // Active flock + unpaid → blocking
        Expense::factory()->create([
            'farm_id'        => $this->farm->id,
            'flock_id'       => $this->activeFlock->id,
            'payment_status' => 'unpaid',
            'paid_amount'    => 0,
            'total_amount'   => 1000,
        ]);

        $closedFlock = Flock::factory()->create(['farm_id' => $this->farm->id, 'status' => 'closed']);

        // Closed flock + unpaid → NOT blocking
        Expense::factory()->create([
            'farm_id'        => $this->farm->id,
            'flock_id'       => $closedFlock->id,
            'payment_status' => 'unpaid',
            'paid_amount'    => 0,
            'total_amount'   => 500,
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->getJson('/api/accounting/review-queue')
            ->assertOk();

        $this->assertEquals(1, $response->json('summary.blocking_flock_closure_count'));
    }

    public function test_paid_records_excluded_from_queue(): void
    {
        Expense::factory()->create([
            'farm_id'        => $this->farm->id,
            'flock_id'       => $this->activeFlock->id,
            'payment_status' => 'paid',
            'paid_amount'    => 1000,
            'total_amount'   => 1000,
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->getJson('/api/accounting/review-queue')
            ->assertOk();

        $this->assertEquals(0, $response->json('meta.total'));
    }

    public function test_filter_by_type_expense_excludes_sales(): void
    {
        Expense::factory()->create([
            'farm_id'        => $this->farm->id,
            'flock_id'       => $this->activeFlock->id,
            'payment_status' => 'unpaid',
            'paid_amount'    => 0,
            'total_amount'   => 500,
        ]);

        Sale::factory()->create([
            'farm_id'          => $this->farm->id,
            'flock_id'         => $this->activeFlock->id,
            'payment_status'   => 'unpaid',
            'received_amount'  => 0,
            'remaining_amount' => 2000,
            'net_amount'       => 2000,
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->getJson('/api/accounting/review-queue?type=expense')
            ->assertOk();

        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('expense', $response->json('data.0.type'));
    }
}
```

- [ ] **Step 2: تشغيل الاختبار للتحقق من الفشل الأولي (لا factories بعد)**

```bash
cd backend && php artisan test tests/Feature/ReviewQueue/ReviewQueueIndexTest.php 2>&1 | tail -20
```

Expected: tests run (pass أو fail بسبب factories — سنصلح لاحقًا إن احتجنا factories).

- [ ] **Step 3: كتابة اختبار PATCH**

أنشئ `backend/tests/Feature/ReviewQueue/ReviewQueueUpdateTest.php`:

```php
<?php

namespace Tests\Feature\ReviewQueue;

use App\Models\Expense;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewQueueUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Farm $farm;
    private Flock $flock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->farm  = Farm::factory()->create();
        $this->flock = Flock::factory()->create(['farm_id' => $this->farm->id, 'status' => 'active']);
        $this->farm->users()->attach($this->admin->id, ['role' => 'farm_admin']);
    }

    public function test_patch_updates_paid_amount_and_recalculates(): void
    {
        $expense = Expense::factory()->create([
            'farm_id'          => $this->farm->id,
            'flock_id'         => $this->flock->id,
            'total_amount'     => 1000,
            'paid_amount'      => 0,
            'remaining_amount' => 1000,
            'payment_status'   => 'unpaid',
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->patchJson("/api/accounting/review-queue/expense/{$expense->id}", [
                'paid_amount' => 600,
            ])
            ->assertOk();

        $this->assertEquals(600.0, $response->json('paid_amount'));
        $this->assertEquals(400.0, $response->json('remaining_amount'));
        $this->assertEquals('partial', $response->json('payment_status'));
        $this->assertContains('partial_payment', $response->json('review_reasons'));
    }

    public function test_patch_full_payment_marks_as_paid_and_removes_from_reasons(): void
    {
        $expense = Expense::factory()->create([
            'farm_id'          => $this->farm->id,
            'flock_id'         => $this->flock->id,
            'total_amount'     => 1000,
            'paid_amount'      => 0,
            'remaining_amount' => 1000,
            'payment_status'   => 'unpaid',
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->patchJson("/api/accounting/review-queue/expense/{$expense->id}", [
                'paid_amount' => 1000,
            ])
            ->assertOk();

        $this->assertEquals('paid', $response->json('payment_status'));
        $this->assertEmpty($response->json('review_reasons'));
    }

    public function test_patch_expense_unit_price_recalculates_total(): void
    {
        $expense = Expense::factory()->create([
            'farm_id'          => $this->farm->id,
            'flock_id'         => $this->flock->id,
            'quantity'         => 10,
            'unit_price'       => null,
            'total_amount'     => 0,
            'paid_amount'      => 0,
            'remaining_amount' => 0,
            'payment_status'   => 'unpaid',
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->patchJson("/api/accounting/review-queue/expense/{$expense->id}", [
                'unit_price' => 50,
            ])
            ->assertOk();

        $this->assertEquals(500.0, $response->json('total_amount'));
        $this->assertNotContains('missing_price', $response->json('review_reasons'));
    }

    public function test_invalid_type_returns_422(): void
    {
        $this->actingAs($this->admin)
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->patchJson('/api/accounting/review-queue/inventory/1', ['paid_amount' => 100])
            ->assertStatus(422);
    }
}
```

- [ ] **Step 4: تشغيل الاختبارات**

```bash
cd backend && php artisan test tests/Feature/ReviewQueue/ 2>&1 | tail -30
```

Expected: all tests pass. إذا فشل بسبب غياب factories، أكمل Task 4 أولًا ثم ارجع.

- [ ] **Step 5: Commit**

```bash
git add backend/tests/Feature/ReviewQueue/
git commit -m "test(accounting): add ReviewQueue index and update feature tests"
```

---

## Task 4: منع إغلاق الفوج وإضافة اختباره

**Files:**
- Modify: `backend/app/Actions/Flock/UpdateFlockAction.php`
- Create: `backend/tests/Feature/Flock/FlockClosureBlockingTest.php`

- [ ] **Step 1: كتابة الاختبار أولًا (TDD)**

أنشئ `backend/tests/Feature/Flock/FlockClosureBlockingTest.php`:

```php
<?php

namespace Tests\Feature\Flock;

use App\Models\Expense;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlockClosureBlockingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Farm $farm;
    private Flock $flock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->farm  = Farm::factory()->create();
        $this->flock = Flock::factory()->create([
            'farm_id' => $this->farm->id,
            'status'  => 'active',
        ]);
        $this->farm->users()->attach($this->admin->id, ['role' => 'farm_admin']);
    }

    public function test_cannot_close_flock_with_unpaid_expense(): void
    {
        Expense::factory()->create([
            'farm_id'        => $this->farm->id,
            'flock_id'       => $this->flock->id,
            'payment_status' => 'unpaid',
            'paid_amount'    => 0,
            'total_amount'   => 2000,
        ]);

        $this->actingAs($this->admin)
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->putJson("/api/flocks/{$this->flock->id}", ['status' => 'closed'])
            ->assertStatus(422)
            ->assertJsonFragment(['blocking_count' => 1]);
    }

    public function test_cannot_close_flock_with_partial_sale(): void
    {
        \App\Models\Sale::factory()->create([
            'farm_id'          => $this->farm->id,
            'flock_id'         => $this->flock->id,
            'payment_status'   => 'partial',
            'received_amount'  => 500,
            'remaining_amount' => 1500,
            'net_amount'       => 2000,
        ]);

        $this->actingAs($this->admin)
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->putJson("/api/flocks/{$this->flock->id}", ['status' => 'closed'])
            ->assertStatus(422)
            ->assertJsonFragment(['blocking_count' => 1]);
    }

    public function test_cannot_close_flock_with_missing_price_expense(): void
    {
        // expense paid=paid لكن unit_price فارغ → يمنع الإغلاق
        Expense::factory()->create([
            'farm_id'        => $this->farm->id,
            'flock_id'       => $this->flock->id,
            'payment_status' => 'paid',
            'paid_amount'    => 0,
            'total_amount'   => 0,
            'unit_price'     => null,
            'quantity'       => 10,
        ]);

        $this->actingAs($this->admin)
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->putJson("/api/flocks/{$this->flock->id}", ['status' => 'closed'])
            ->assertStatus(422)
            ->assertJsonFragment(['blocking_count' => 1]);
    }

    public function test_cannot_close_flock_with_zero_total_sale(): void
    {
        \App\Models\Sale::factory()->create([
            'farm_id'          => $this->farm->id,
            'flock_id'         => $this->flock->id,
            'payment_status'   => 'paid',
            'received_amount'  => 0,
            'remaining_amount' => 0,
            'net_amount'       => 0,   // ← total <= 0
        ]);

        $this->actingAs($this->admin)
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->putJson("/api/flocks/{$this->flock->id}", ['status' => 'closed'])
            ->assertStatus(422)
            ->assertJsonFragment(['blocking_count' => 1]);
    }

    public function test_can_close_flock_when_all_records_paid_and_priced(): void
    {
        Expense::factory()->create([
            'farm_id'        => $this->farm->id,
            'flock_id'       => $this->flock->id,
            'payment_status' => 'paid',
            'paid_amount'    => 1000,
            'total_amount'   => 1000,
            'unit_price'     => 10,
            'quantity'       => 100,
        ]);

        $this->actingAs($this->admin)
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->putJson("/api/flocks/{$this->flock->id}", ['status' => 'closed'])
            ->assertOk();
    }
}
```

- [ ] **Step 2: تشغيل الاختبارات للتحقق من الفشل**

```bash
cd backend && php artisan test tests/Feature/Flock/FlockClosureBlockingTest.php 2>&1 | tail -20
```

Expected: فشل في الأولين لأن الـ action لا يتحقق بعد.

- [ ] **Step 3: تعديل UpdateFlockAction لإضافة فحص الإغلاق**

في `backend/app/Actions/Flock/UpdateFlockAction.php`، أضف هذه الدالة الخاصة وادعُها قبل التحقق من الانتقال إلى `closed`:

ابحث عن هذا السطر داخل `execute()`:
```php
if ($data['status'] === 'closed') {
    $totalSales = $flock->sales()->sum('net_amount');
```

أضف **قبله مباشرة** فحص الـ blocking:
```php
                if ($data['status'] === 'closed') {
                    $this->assertNoBlockingRecords($flock);
                }

                if ($data['status'] === 'closed') {
                    $totalSales = $flock->sales()->sum('net_amount');
```

ثم أضف الدالة الخاصة في نهاية الـ class (قبل قوس الإغلاق الأخير):

```php
    /**
     * @throws \Exception برمز 422 إذا وجدت سجلات مانعة للإغلاق
     *
     * المانعات:
     *   - expenses/sales بحالة unpaid أو partial
     *   - expenses بـ quantity=0/null أو unit_price=0/null
     *   - أي سجل بـ total_amount/net_amount <= 0
     */
    private function assertNoBlockingRecords(Flock $flock): void
    {
        // ── Unpaid / partial payments ─────────────────────────────────────────
        $unpaidExpenses = $flock->expenses()
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->count();

        $unpaidSales = $flock->sales()
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->count();

        // ── Missing price: expense with no quantity, no unit_price, or zero total ─
        $missingPriceExpenses = $flock->expenses()
            ->where(function ($q): void {
                $q->whereNull('quantity')
                  ->orWhere('quantity', '<=', 0)
                  ->orWhereNull('unit_price')
                  ->orWhere('unit_price', '<=', 0)
                  ->orWhere('total_amount', '<=', 0);
            })
            ->count();

        // ── Missing price: sale with net_amount <= 0 ──────────────────────────
        $missingPriceSales = $flock->sales()
            ->where('net_amount', '<=', 0)
            ->count();

        $blockingCount = $unpaidExpenses + $unpaidSales + $missingPriceExpenses + $missingPriceSales;

        if ($blockingCount > 0) {
            throw new \Exception(
                json_encode([
                    'message'        => 'لا يمكن إغلاق الفوج — يوجد ' . $blockingCount . ' سجل مالي غير مكتمل أو غير مسدد.',
                    'blocking_count' => $blockingCount,
                    'review_url'     => '/accounting?tab=review&filter=blocking&flock_id=' . $flock->id,
                ]),
                422
            );
        }
    }
```

**ملاحظة:** FlockController يعالج الـ Exception بالشكل التالي (تحقق من controller الحالي — إذا كان يعيد `$e->getMessage()` مباشرة، فالـ JSON يُعاد كـ string وهو صحيح). إذا احتجت تعديل الـ handler، راجع `backend/app/Http/Controllers/Api/Flock/FlockController.php` القسم الخاص بـ `update()`.

- [ ] **Step 4: تشغيل الاختبارات للتحقق من النجاح**

```bash
cd backend && php artisan test tests/Feature/Flock/FlockClosureBlockingTest.php 2>&1 | tail -20
```

Expected: جميع الاختبارات تنجح.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Actions/Flock/UpdateFlockAction.php \
        backend/tests/Feature/Flock/FlockClosureBlockingTest.php
git commit -m "feat(flock): block closure when unpaid/partial financial records exist"
```

---

## Task 5: Frontend API Client

**Files:**
- Create: `frontend/src/lib/api/accounting.ts`

- [ ] **Step 1: إنشاء الـ API client**

أنشئ `frontend/src/lib/api/accounting.ts`:

```typescript
import { apiClient } from './client'

export interface ReviewSummary {
  unpaid_count: number
  partial_payment_count: number
  missing_price_count: number
  missing_payment_status_count: number
  inconsistent_financial_state_count: number
  blocking_flock_closure_count: number
}

export type ReviewReason =
  | 'unpaid'
  | 'partial_payment'
  | 'missing_price'
  | 'missing_payment_status'
  | 'inconsistent_financial_state'
  | 'blocking_flock_closure'

export interface ReviewItem {
  id: string
  type: 'expense' | 'sale'
  record_id: number
  flock_id: number | null
  flock_name: string | null
  flock_status: string | null
  entry_date: string | null
  description: string | null
  total_amount: number
  paid_amount: number
  remaining_amount: number
  payment_status: 'paid' | 'partial' | 'unpaid' | null
  unit_price: number | null
  review_reasons: ReviewReason[]
}

export interface ReviewQueueResponse {
  summary: ReviewSummary
  data: ReviewItem[]
  meta: {
    total: number
    current_page: number
    per_page: number
  }
}

export interface ReviewQueueFilters {
  type?: 'expense' | 'sale' | 'all'
  reason?: ReviewReason
  flock_id?: number | string
  page?: number
  per_page?: number
  filter?: string // 'blocking' shorthand
}

// Arabic badge labels — single source of truth for UI
export const REASON_LABELS: Record<ReviewReason, string> = {
  unpaid:                        'غير مدفوع',
  partial_payment:               'دفع جزئي',
  missing_price:                 'ناقص السعر',
  missing_payment_status:        'ناقص حالة الدفع',
  inconsistent_financial_state:  'تناقض مالي',
  blocking_flock_closure:        'مانع إغلاق',
}

export const accountingApi = {
  getReviewQueue: async (filters: ReviewQueueFilters = {}): Promise<ReviewQueueResponse> => {
    const params = new URLSearchParams()

    if (filters.type) params.set('type', filters.type)
    if (filters.flock_id) params.set('flock_id', String(filters.flock_id))
    if (filters.page) params.set('page', String(filters.page))
    if (filters.per_page) params.set('per_page', String(filters.per_page))

    // 'blocking' filter shorthand maps to reason=blocking_flock_closure
    if (filters.filter === 'blocking') {
      params.set('reason', 'blocking_flock_closure')
    } else if (filters.reason) {
      params.set('reason', filters.reason)
    }

    const { data } = await apiClient.get<ReviewQueueResponse>(
      `/accounting/review-queue?${params.toString()}`
    )
    return data
  },

  updateReviewItem: async (
    type: 'expense' | 'sale',
    id: number,
    payload: { paid_amount?: number; unit_price?: number }
  ): Promise<ReviewItem> => {
    const { data } = await apiClient.patch<ReviewItem>(
      `/accounting/review-queue/${type}/${id}`,
      payload
    )
    return data
  },
}
```

- [ ] **Step 2: Commit**

```bash
git add frontend/src/lib/api/accounting.ts
git commit -m "feat(accounting): add accounting API client with ReviewQueue types"
```

---

## Task 6: ReviewQueueTab Component

**Files:**
- Create: `frontend/src/components/accounting/ReviewQueueTab.tsx`

- [ ] **Step 1: إنشاء المجلد وملف المكوّن**

أنشئ `frontend/src/components/accounting/ReviewQueueTab.tsx`:

```tsx
'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { accountingApi, REASON_LABELS } from '@/lib/api/accounting'
import type { ReviewItem, ReviewReason, ReviewQueueFilters } from '@/lib/api/accounting'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { formatNumber } from '@/lib/utils'
import { AlertTriangle, CheckCircle2, Loader2, RefreshCw } from 'lucide-react'

// ── Badge color map ───────────────────────────────────────────────────────────
const REASON_COLORS: Record<ReviewReason, string> = {
  unpaid:                        'bg-red-100 text-red-700 border-red-200',
  partial_payment:               'bg-amber-100 text-amber-700 border-amber-200',
  missing_price:                 'bg-orange-100 text-orange-700 border-orange-200',
  missing_payment_status:        'bg-gray-100 text-gray-700 border-gray-200',
  inconsistent_financial_state:  'bg-purple-100 text-purple-700 border-purple-200',
  blocking_flock_closure:        'bg-red-200 text-red-800 border-red-300',
}

interface Props {
  initialFlockId?: string
  initialFilter?: string
}

export function ReviewQueueTab({ initialFlockId, initialFilter }: Props) {
  const qc = useQueryClient()

  const [filters, setFilters] = useState<ReviewQueueFilters>({
    type: 'all',
    flock_id: initialFlockId,
    filter: initialFilter,
    page: 1,
    per_page: 20,
  })

  const [editingId, setEditingId] = useState<string | null>(null)
  const [editValues, setEditValues] = useState<{ paid_amount?: string; unit_price?: string }>({})

  const { data, isLoading, isFetching } = useQuery({
    queryKey: ['accounting', 'review-queue', filters],
    queryFn: () => accountingApi.getReviewQueue(filters),
    staleTime: 15_000,
  })

  const { mutate: updateItem, isPending: isUpdating } = useMutation({
    mutationFn: ({ type, id, payload }: {
      type: 'expense' | 'sale'
      id: number
      payload: { paid_amount?: number; unit_price?: number }
    }) => accountingApi.updateReviewItem(type, id, payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['accounting', 'review-queue'] })
      setEditingId(null)
      setEditValues({})
    },
  })

  const summary = data?.summary

  const handleSave = (item: ReviewItem) => {
    const payload: { paid_amount?: number; unit_price?: number } = {}
    if (editValues.paid_amount !== undefined) {
      payload.paid_amount = parseFloat(editValues.paid_amount)
    }
    if (editValues.unit_price !== undefined && item.type === 'expense') {
      payload.unit_price = parseFloat(editValues.unit_price)
    }
    updateItem({ type: item.type, id: item.record_id, payload })
  }

  return (
    <div className="space-y-5" dir="rtl">
      {/* ── Summary Cards ─────────────────────────────────────────────────── */}
      {summary && (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
          <SummaryCard label="غير مدفوع"     count={summary.unpaid_count}                    color="red" />
          <SummaryCard label="دفع جزئي"      count={summary.partial_payment_count}            color="amber" />
          <SummaryCard label="ناقص السعر"    count={summary.missing_price_count}              color="orange" />
          <SummaryCard label="ناقص حالة الدفع" count={summary.missing_payment_status_count}  color="gray" />
          <SummaryCard label="تناقض مالي"    count={summary.inconsistent_financial_state_count} color="purple" />
          <SummaryCard label="مانع إغلاق"    count={summary.blocking_flock_closure_count}    color="rose" />
        </div>
      )}

      {/* ── Filters ───────────────────────────────────────────────────────── */}
      <div className="flex flex-wrap gap-2">
        {(['all', 'expense', 'sale'] as const).map((t) => (
          <button
            key={t}
            onClick={() => setFilters((f) => ({ ...f, type: t, page: 1 }))}
            className={`rounded-full px-3 py-1 text-sm font-medium border transition-colors ${
              filters.type === t
                ? 'bg-emerald-600 text-white border-emerald-600'
                : 'bg-white text-slate-600 border-slate-200 hover:border-emerald-400'
            }`}
          >
            {t === 'all' ? 'الكل' : t === 'expense' ? 'المصروفات' : 'المبيعات'}
          </button>
        ))}

        {/* Reason filter buttons */}
        {(['unpaid', 'partial_payment', 'blocking_flock_closure'] as ReviewReason[]).map((r) => (
          <button
            key={r}
            onClick={() =>
              setFilters((f) => ({
                ...f,
                reason: f.reason === r ? undefined : r,
                filter: undefined,
                page: 1,
              }))
            }
            className={`rounded-full px-3 py-1 text-sm font-medium border transition-colors ${
              filters.reason === r
                ? 'bg-slate-800 text-white border-slate-800'
                : 'bg-white text-slate-600 border-slate-200 hover:border-slate-400'
            }`}
          >
            {REASON_LABELS[r]}
          </button>
        ))}

        {(filters.reason || filters.filter) && (
          <button
            onClick={() => setFilters((f) => ({ ...f, reason: undefined, filter: undefined, page: 1 }))}
            className="text-xs text-slate-400 underline"
          >
            مسح الفلتر
          </button>
        )}
      </div>

      {/* ── Table ─────────────────────────────────────────────────────────── */}
      {isLoading ? (
        <div className="flex justify-center py-12">
          <Loader2 className="h-6 w-6 animate-spin text-slate-400" />
        </div>
      ) : !data?.data.length ? (
        <div className="flex flex-col items-center justify-center py-16 text-slate-400">
          <CheckCircle2 className="mb-3 h-10 w-10 text-emerald-400" />
          <p className="text-sm font-medium">لا توجد سجلات تحتاج مراجعة</p>
        </div>
      ) : (
        <div className="space-y-2">
          {isFetching && !isLoading && (
            <div className="text-xs text-slate-400 flex items-center gap-1">
              <RefreshCw className="h-3 w-3 animate-spin" /> جاري التحديث...
            </div>
          )}

          {data.data.map((item) => (
            <ReviewRow
              key={item.id}
              item={item}
              isEditing={editingId === item.id}
              isUpdating={isUpdating && editingId === item.id}
              editValues={editValues}
              onEdit={() => {
                setEditingId(item.id)
                setEditValues({
                  paid_amount: String(item.paid_amount ?? 0),
                  unit_price: item.unit_price != null ? String(item.unit_price) : '',
                })
              }}
              onCancel={() => { setEditingId(null); setEditValues({}) }}
              onSave={() => handleSave(item)}
              onEditChange={setEditValues}
            />
          ))}

          {/* Pagination */}
          {data.meta.total > data.meta.per_page && (
            <div className="flex items-center justify-center gap-3 pt-2">
              <button
                disabled={filters.page === 1}
                onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) - 1 }))}
                className="rounded px-3 py-1 text-sm border disabled:opacity-40"
              >
                السابق
              </button>
              <span className="text-xs text-slate-500">
                صفحة {data.meta.current_page} من {Math.ceil(data.meta.total / data.meta.per_page)}
              </span>
              <button
                disabled={(filters.page ?? 1) * data.meta.per_page >= data.meta.total}
                onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}
                className="rounded px-3 py-1 text-sm border disabled:opacity-40"
              >
                التالي
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  )
}

// ── Summary Card ──────────────────────────────────────────────────────────────
function SummaryCard({ label, count, color }: { label: string; count: number; color: string }) {
  if (count === 0) return null
  const colorMap: Record<string, string> = {
    red:    'bg-red-50 border-red-200 text-red-700',
    amber:  'bg-amber-50 border-amber-200 text-amber-700',
    orange: 'bg-orange-50 border-orange-200 text-orange-700',
    gray:   'bg-gray-50 border-gray-200 text-gray-700',
    purple: 'bg-purple-50 border-purple-200 text-purple-700',
    rose:   'bg-rose-50 border-rose-200 text-rose-800',
  }
  return (
    <div className={`rounded-xl border p-3 ${colorMap[color] ?? 'bg-slate-50 border-slate-200 text-slate-700'}`}>
      <div className="text-2xl font-bold">{count}</div>
      <div className="text-xs mt-0.5 opacity-80">{label}</div>
    </div>
  )
}

// ── Review Row ────────────────────────────────────────────────────────────────
interface ReviewRowProps {
  item: ReviewItem
  isEditing: boolean
  isUpdating: boolean
  editValues: { paid_amount?: string; unit_price?: string }
  onEdit: () => void
  onCancel: () => void
  onSave: () => void
  onEditChange: (v: { paid_amount?: string; unit_price?: string }) => void
}

function ReviewRow({
  item, isEditing, isUpdating, editValues, onEdit, onCancel, onSave, onEditChange
}: ReviewRowProps) {
  const hasBlocking = item.review_reasons.includes('blocking_flock_closure')

  return (
    <Card className={`transition-colors ${hasBlocking ? 'border-red-300 bg-red-50/30' : ''}`}>
      <CardContent className="py-3 px-4">
        <div className="flex items-start justify-between gap-3">
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-2 flex-wrap">
              <span className="font-medium text-slate-800 text-sm">{item.description}</span>
              <span className="text-xs text-slate-400">
                {item.type === 'expense' ? 'مصروف' : 'بيع'} • {item.flock_name ?? '—'} • {item.entry_date ?? '—'}
              </span>
            </div>

            <div className="mt-1.5 flex flex-wrap gap-1">
              {item.review_reasons.map((r) => (
                <span
                  key={r}
                  className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium ${REASON_COLORS[r]}`}
                >
                  {r === 'blocking_flock_closure' && (
                    <AlertTriangle className="me-1 h-3 w-3" />
                  )}
                  {REASON_LABELS[r]}
                </span>
              ))}
            </div>

            {isEditing ? (
              <div className="mt-3 flex flex-wrap items-center gap-2">
                <div className="flex flex-col gap-1">
                  <label className="text-xs text-slate-500">المدفوع</label>
                  <Input
                    type="number"
                    min={0}
                    value={editValues.paid_amount ?? ''}
                    onChange={(e) => onEditChange({ ...editValues, paid_amount: e.target.value })}
                    className="h-8 w-28 text-sm"
                  />
                </div>
                {item.type === 'expense' && (
                  <div className="flex flex-col gap-1">
                    <label className="text-xs text-slate-500">سعر الوحدة</label>
                    <Input
                      type="number"
                      min={0}
                      value={editValues.unit_price ?? ''}
                      onChange={(e) => onEditChange({ ...editValues, unit_price: e.target.value })}
                      className="h-8 w-28 text-sm"
                    />
                  </div>
                )}
                <div className="flex gap-2 items-end pb-0.5">
                  <Button size="sm" onClick={onSave} disabled={isUpdating}>
                    {isUpdating ? <Loader2 className="h-4 w-4 animate-spin" /> : 'حفظ'}
                  </Button>
                  <Button size="sm" variant="outline" onClick={onCancel}>إلغاء</Button>
                </div>
              </div>
            ) : (
              <div className="mt-2 flex items-center gap-4 text-xs text-slate-500">
                <span>الإجمالي: <strong className="text-slate-700">{formatNumber(item.total_amount)}</strong></span>
                <span>المدفوع: <strong className="text-slate-700">{formatNumber(item.paid_amount)}</strong></span>
                <span>المتبقي: <strong className="text-red-600">{formatNumber(item.remaining_amount)}</strong></span>
              </div>
            )}
          </div>

          {!isEditing && (
            <button
              onClick={onEdit}
              className="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 hover:border-emerald-400 hover:text-emerald-600 transition-colors"
            >
              تسديد / تعديل
            </button>
          )}
        </div>
      </CardContent>
    </Card>
  )
}
```

- [ ] **Step 2: Commit**

```bash
git add frontend/src/components/accounting/ReviewQueueTab.tsx
git commit -m "feat(accounting): add ReviewQueueTab component"
```

---

## Task 7: صفحة /accounting

**Files:**
- Create: `frontend/src/app/(farm)/accounting/page.tsx`
- Modify: `frontend/src/lib/roles.ts`
- Modify: `frontend/src/components/layout/MoreMenu.tsx`

- [ ] **Step 1: إنشاء الصفحة**

أنشئ `frontend/src/app/(farm)/accounting/page.tsx`:

```tsx
'use client'

import { Suspense } from 'react'
import { useSearchParams } from 'next/navigation'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/Tabs'
import { AccountingReportTab } from '@/components/reports/tabs/AccountingReportTab'
import { ReviewQueueTab } from '@/components/accounting/ReviewQueueTab'
import { Skeleton } from '@/components/ui/Skeleton'

function AccountingPageInner() {
  const searchParams = useSearchParams()

  const tab       = searchParams.get('tab') ?? 'summary'
  const filter    = searchParams.get('filter') ?? undefined
  const flockId   = searchParams.get('flock_id') ?? undefined

  return (
    <div className="space-y-5 pb-20 sm:pb-8" dir="rtl">
      <div>
        <h1 className="text-2xl font-bold text-slate-800">المحاسبة</h1>
        <p className="text-sm text-slate-500 mt-1">ملخص مالي وقائمة الذمم والمراجعة</p>
      </div>

      <Tabs defaultValue={tab}>
        <TabsList>
          <TabsTrigger value="summary">ملخص المحاسبة</TabsTrigger>
          <TabsTrigger value="review">الذمم والمراجعة</TabsTrigger>
        </TabsList>

        <TabsContent value="summary" className="mt-4">
          <AccountingReportTab
            data={undefined}
            isLoading={false}
            filters={{}}
          />
        </TabsContent>

        <TabsContent value="review" className="mt-4">
          <ReviewQueueTab initialFlockId={flockId} initialFilter={filter} />
        </TabsContent>
      </Tabs>
    </div>
  )
}

export default function AccountingPage() {
  return (
    <Suspense fallback={<Skeleton className="h-64 w-full rounded-xl" />}>
      <AccountingPageInner />
    </Suspense>
  )
}
```

**ملاحظة:** `AccountingReportTab` قد تحتاج تعديل props بسيط إذا كانت تتوقع `data`/`isLoading` بشكل مختلف عن الحالي — تحقق من props المطلوبة في `frontend/src/components/reports/tabs/AccountingReportTab.tsx` وعدّل الـ page إذا احتجت.

- [ ] **Step 2: إضافة /accounting لـ roles**

في `frontend/src/lib/roles.ts`، عدّل سطر `farm_admin`:

```typescript
  farm_admin: ['/dashboard', '/flocks', '/inventory', '/sales', '/expenses', '/partners', '/workers', '/workers/new', '/reports', '/accounting'],
```

- [ ] **Step 3: إضافة رابط المحاسبة في MoreMenu**

في `frontend/src/components/layout/MoreMenu.tsx`، عدّل مصفوفة الروابط لإضافة المحاسبة (أضفها قبل التقارير):

```typescript
    { label: 'المحاسبة',  href: '/accounting', icon: Calculator,  color: 'text-violet-600', bg: 'bg-violet-50' },
    { label: 'التقارير',  href: '/reports',     icon: BarChart3,   color: 'text-sky-600',    bg: 'bg-sky-50' },
```

أضف `import { Calculator } from 'lucide-react'` مع بقية الـ imports.

- [ ] **Step 4: Commit**

```bash
git add frontend/src/app/\(farm\)/accounting/ \
        frontend/src/lib/roles.ts \
        frontend/src/components/layout/MoreMenu.tsx
git commit -m "feat(accounting): add /accounting page with summary and review tabs"
```

---

## Task 8: ربط صفحة تفاصيل الفوج

**Files:**
- Modify: `frontend/src/app/(farm)/flocks/[id]/page.tsx`

الهدف: إضافة رابط "عرض السجلات المانعة" يظهر فقط عندما يكون الفوج نشطًا ويوجد `blocking_flock_closure_count > 0`.

- [ ] **Step 1: إضافة query للـ blocking count في الصفحة**

في `frontend/src/app/(farm)/flocks/[id]/page.tsx`، أضف هذه الـ import والـ query:

في قسم imports:
```typescript
import { useQuery } from '@tanstack/react-query'
import { accountingApi } from '@/lib/api/accounting'
import Link from 'next/link'
import { AlertTriangle } from 'lucide-react'
```

داخل الـ component الرئيسي (بعد `const { data: flock }` query الحالي)، أضف:

```typescript
  // Query blocking records — only when flock is active
  const flockId = resolvedParams.id  // أو الاسم الصحيح للمتغير في ملفك
  const { data: reviewData } = useQuery({
    queryKey: ['accounting', 'review-queue', { flock_id: flockId, filter: 'blocking' }],
    queryFn: () => accountingApi.getReviewQueue({ flock_id: flockId, filter: 'blocking' }),
    enabled: flock?.status === 'active',
    staleTime: 30_000,
  })
  const blockingCount = reviewData?.summary.blocking_flock_closure_count ?? 0
```

- [ ] **Step 2: إضافة البانر في الـ UI**

في الـ JSX، أضف هذا البانر قبل تبويبات الفوج مباشرة (بعد الـ header cards):

```tsx
{flock?.status === 'active' && blockingCount > 0 && (
  <Link
    href={`/accounting?tab=review&filter=blocking&flock_id=${flock.id}`}
    className="flex items-center gap-3 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700 hover:bg-red-100 transition-colors"
  >
    <AlertTriangle className="h-4 w-4 shrink-0" />
    <span>
      يوجد <strong>{blockingCount}</strong> سجل مالي غير مسدد يمنع إغلاق هذا الفوج
    </span>
    <span className="me-auto text-xs font-medium underline">عرض السجلات المانعة ←</span>
  </Link>
)}
```

- [ ] **Step 3: Commit**

```bash
git add frontend/src/app/\(farm\)/flocks/\[id\]/page.tsx
git commit -m "feat(flock): show blocking records banner linking to /accounting review tab"
```

---

## Task 9: تشغيل الاختبارات الكاملة والتحقق

- [ ] **Step 1: تشغيل جميع الاختبارات**

```bash
cd backend && php artisan test 2>&1 | tail -20
```

Expected: جميع الاختبارات تنجح (لا failures جديدة).

- [ ] **Step 2: التحقق من TypeScript**

```bash
cd frontend && npx tsc --noEmit 2>&1 | head -30
```

Expected: لا أخطاء TypeScript.

- [ ] **Step 3: Commit نهائي**

```bash
git add -A
git commit -m "feat(accounting): complete review queue tab implementation"
```

---

## Self-Review: Spec Coverage

| المتطلب | Task المُنفِّذة |
|---|---|
| مكان التبويب: داخل /accounting لا /reports | Task 7 |
| summary keys موحدة | Task 1 (service) + Task 5 (client types) |
| missing_payment_status محفوظ في العقد | Task 1 (attachReasons) + Task 5 (types) |
| ReviewQueueService: mapping بعد DB | Task 1 |
| summary counts = نفس منطق الجدول | Task 1 (buildSummary يعمل على نفس qualifyingRows) |
| فرق review inclusion vs blocking | Task 1 (attachReasons) + Task 4 (فحص الإغلاق منفصل) |
| PATCH يعيد احتساب remaining + status + reasons | Task 1 (updateRecord) + Task 3 (اختبارات) |
| UpdateReviewItemRequest مرتبط بـ route param | Task 2 (type من route, ليس body) |
| Badge labels عربية موحدة | Task 5 (REASON_LABELS) + Task 6 (REASON_COLORS) |
| رابط الفوج → /accounting?tab=review&filter=blocking | Task 8 |
| منع إغلاق الفوج backend (unpaid/partial + missing_price) | Task 4 |
| اختبارات backend | Tasks 3 + 4 |

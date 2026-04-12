# Farm Admin Dashboard Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build the full farm admin dashboard: flock info card, quick-entry card (mortality/feed/medicine/expense), operational placeholder card, and day-summary card — all wired to real backend APIs.

**Architecture:** Backend adds 5 new endpoints (inventory items, feed-log, medicine-log, expense, today-summary) following the existing Action → Controller → Resource pattern. Frontend rebuilds `dashboard/page.tsx` into 3 focused child components. Data flows: page fetches active flock + today-summary, passes down as props, child components post and trigger a `onSuccess` callback to re-fetch summary.

**Tech Stack:** Laravel 11 + Sanctum + PHPUnit (SQLite in tests) · Next.js App Router `'use client'` · Zustand · Tailwind RTL Arabic

---

## File Map

**New backend:**
- `database/seeders/ExpenseCategorySeeder.php`
- `database/factories/ItemTypeFactory.php`
- `database/factories/ItemFactory.php`
- `database/factories/WarehouseFactory.php`
- `database/factories/WarehouseItemFactory.php`
- `database/factories/ExpenseCategoryFactory.php`
- `app/Http/Controllers/Api/Inventory/InventoryController.php`
- `app/Http/Controllers/Api/FeedLog/FeedLogController.php`
- `app/Http/Controllers/Api/MedicineLog/MedicineLogController.php`
- `app/Http/Controllers/Api/Expense/FlockExpenseController.php`
- `app/Http/Requests/FeedLog/StoreFeedLogRequest.php`
- `app/Http/Requests/MedicineLog/StoreMedicineLogRequest.php`
- `app/Http/Requests/Expense/StoreFlockExpenseRequest.php`
- `app/Actions/FeedLog/CreateFeedLogAction.php`
- `app/Actions/MedicineLog/CreateMedicineLogAction.php`
- `app/Actions/Expense/CreateFlockExpenseAction.php`
- `app/Actions/Flock/TodaySummaryAction.php`
- `tests/Feature/FeedLog/FeedLogCreateTest.php`
- `tests/Feature/MedicineLog/MedicineLogCreateTest.php`
- `tests/Feature/Expense/FlockExpenseCreateTest.php`
- `tests/Feature/Flock/TodaySummaryTest.php`
- `tests/Feature/Inventory/InventoryItemsTest.php`

**Modified backend:**
- `app/Http/Resources/FlockResource.php` — add `total_mortality`, `remaining_count`
- `app/Actions/Flock/ListFlocksAction.php` — add `withSum('mortalities', 'quantity')`
- `app/Actions/Flock/ShowFlockAction.php` — add `withSum('mortalities', 'quantity')`
- `routes/api.php` — new routes
- `database/seeders/DatabaseSeeder.php` — call ExpenseCategorySeeder

**New frontend:**
- `src/types/dashboard.ts`
- `src/lib/api/inventory.ts`
- `src/lib/api/quick-entry.ts`
- `src/app/(farm)/dashboard/FlockInfoCard.tsx`
- `src/app/(farm)/dashboard/QuickEntryCard.tsx`
- `src/app/(farm)/dashboard/DaySummaryCard.tsx`

**Modified frontend:**
- `src/types/flock.ts` — add `total_mortality`, `remaining_count`
- `src/lib/api/flocks.ts` — add `todaySummary`
- `src/app/(farm)/dashboard/page.tsx` — full rebuild

---

## Task 1: Enrich FlockResource with `remaining_count`

**Files:**
- Modify: `app/Http/Resources/FlockResource.php`
- Modify: `app/Actions/Flock/ListFlocksAction.php`
- Modify: `app/Actions/Flock/ShowFlockAction.php`
- Test: `tests/Feature/Flock/FlockRemainingCountTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/Flock/FlockRemainingCountTest.php`:

```php
<?php

namespace Tests\Feature\Flock;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\FlockMortality;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlockRemainingCountTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsMember(Farm $farm): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    public function test_flock_list_includes_remaining_count(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id, 'initial_count' => 5000]);

        FlockMortality::factory()->create(['flock_id' => $flock->id, 'farm_id' => $farm->id, 'quantity' => 100]);
        FlockMortality::factory()->create(['flock_id' => $flock->id, 'farm_id' => $farm->id, 'quantity' => 50]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson('/api/flocks')
            ->assertStatus(200)
            ->assertJsonPath('data.0.total_mortality', 150)
            ->assertJsonPath('data.0.remaining_count', 4850);
    }

    public function test_flock_show_includes_remaining_count(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id, 'initial_count' => 3000]);

        FlockMortality::factory()->create(['flock_id' => $flock->id, 'farm_id' => $farm->id, 'quantity' => 200]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/flocks/{$flock->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.total_mortality', 200)
            ->assertJsonPath('data.remaining_count', 2800);
    }

    public function test_remaining_count_is_initial_count_when_no_mortalities(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id, 'initial_count' => 2000]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson('/api/flocks')
            ->assertStatus(200)
            ->assertJsonPath('data.0.total_mortality', 0)
            ->assertJsonPath('data.0.remaining_count', 2000);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
cd backend && php artisan test tests/Feature/Flock/FlockRemainingCountTest.php
```
Expected: FAIL — `total_mortality` key not found in response.

**Step 3: Update ListFlocksAction**

```php
// app/Actions/Flock/ListFlocksAction.php
public function execute(int $farmId): Collection
{
    return Flock::where('farm_id', $farmId)
        ->withSum('mortalities', 'quantity')
        ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
        ->orderByDesc('start_date')
        ->get();
}
```

**Step 4: Update ShowFlockAction**

```php
// app/Actions/Flock/ShowFlockAction.php
public function execute(int $farmId, int $flockId): Flock
{
    $flock = Flock::where('id', $flockId)
        ->where('farm_id', $farmId)
        ->withSum('mortalities', 'quantity')
        ->first();

    if (! $flock) {
        throw new \Exception('الفوج غير موجود', 404);
    }

    return $flock;
}
```

**Step 5: Update FlockResource**

Add two fields to the `toArray()` array in `app/Http/Resources/FlockResource.php`:

```php
// Add after 'current_age_days':
$totalMortality = (int) ($this->mortalities_sum_quantity ?? 0);

// In the returned array, add:
'total_mortality' => $totalMortality,
'remaining_count' => $this->initial_count - $totalMortality,
```

Full updated `toArray()`:
```php
public function toArray(Request $request): array
{
    $agedays = null;
    if (in_array($this->status, ['active', 'draft'])) {
        $agedays = (int) Carbon::parse($this->start_date)->diffInDays(Carbon::today());
    }

    $totalMortality = (int) ($this->mortalities_sum_quantity ?? 0);

    return [
        'id'               => $this->id,
        'farm_id'          => $this->farm_id,
        'name'             => $this->name,
        'status'           => $this->status,
        'start_date'       => $this->start_date->toDateString(),
        'end_date'         => $this->close_date?->toDateString(),
        'initial_count'    => $this->initial_count,
        'current_age_days' => $agedays,
        'total_mortality'  => $totalMortality,
        'remaining_count'  => $this->initial_count - $totalMortality,
        'notes'            => $this->notes,
        'created_at'       => $this->created_at->toISOString(),
        'updated_at'       => $this->updated_at->toISOString(),
    ];
}
```

**Step 6: Run test to verify it passes**

```bash
cd backend && php artisan test tests/Feature/Flock/FlockRemainingCountTest.php
```
Expected: 3 tests PASS.

**Step 7: Run all tests to verify no regressions**

```bash
cd backend && php artisan test
```
Expected: All existing tests + 3 new = green.

**Step 8: Commit**

```bash
cd backend
git add app/Http/Resources/FlockResource.php \
        app/Actions/Flock/ListFlocksAction.php \
        app/Actions/Flock/ShowFlockAction.php \
        tests/Feature/Flock/FlockRemainingCountTest.php
git commit -m "feat: add total_mortality and remaining_count to FlockResource"
```

---

## Task 2: System Expense Categories Seeder + All New Factories

**Files:**
- Create: `database/seeders/ExpenseCategorySeeder.php`
- Create: `database/factories/ItemTypeFactory.php`
- Create: `database/factories/ItemFactory.php`
- Create: `database/factories/WarehouseFactory.php`
- Create: `database/factories/WarehouseItemFactory.php`
- Create: `database/factories/ExpenseCategoryFactory.php`
- Modify: `database/seeders/DatabaseSeeder.php`

**Step 1: Create ExpenseCategorySeeder**

```php
<?php
// database/seeders/ExpenseCategorySeeder.php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'water',   'name' => 'مياه'],
            ['code' => 'bedding', 'name' => 'فرشة'],
            ['code' => 'other',   'name' => 'أخرى'],
        ];

        foreach ($categories as $cat) {
            ExpenseCategory::firstOrCreate(
                ['code' => $cat['code'], 'farm_id' => null],
                [
                    'name'      => $cat['name'],
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }
    }
}
```

**Step 2: Add to DatabaseSeeder**

```php
// database/seeders/DatabaseSeeder.php
public function run(): void
{
    $this->call([
        ExpenseCategorySeeder::class,
        DevUsersSeeder::class,
    ]);
}
```

**Step 3: Create ItemTypeFactory**

```php
<?php
// database/factories/ItemTypeFactory.php

namespace Database\Factories;

use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id'   => null,
            'name'      => $this->faker->word(),
            'code'      => $this->faker->unique()->slug(1),
            'is_system' => true,
            'is_active' => true,
        ];
    }

    public function feed(): static
    {
        return $this->state(['code' => 'feed', 'name' => 'أعلاف']);
    }

    public function medicine(): static
    {
        return $this->state(['code' => 'medicine', 'name' => 'أدوية']);
    }
}
```

**Step 4: Create ItemFactory**

```php
<?php
// database/factories/ItemFactory.php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\ItemType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id'       => Farm::factory(),
            'item_type_id'  => ItemType::factory(),
            'name'          => $this->faker->word() . ' ' . $this->faker->randomNumber(2),
            'input_unit'    => 'كيس',
            'unit_value'    => 50,
            'content_unit'  => 'كغ',
            'minimum_stock' => null,
            'default_cost'  => null,
            'status'        => 'active',
            'notes'         => null,
            'created_by'    => null,
            'updated_by'    => null,
        ];
    }

    public function forFarm(Farm $farm): static
    {
        return $this->state(['farm_id' => $farm->id]);
    }
}
```

**Step 5: Create WarehouseFactory**

```php
<?php
// database/factories/WarehouseFactory.php

namespace Database\Factories;

use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id'    => Farm::factory(),
            'name'       => 'المستودع الرئيسي',
            'location'   => null,
            'is_active'  => true,
            'notes'      => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function forFarm(Farm $farm): static
    {
        return $this->state(['farm_id' => $farm->id]);
    }
}
```

**Step 6: Create WarehouseItemFactory**

```php
<?php
// database/factories/WarehouseItemFactory.php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Item;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id'          => Farm::factory(),
            'warehouse_id'     => Warehouse::factory(),
            'item_id'          => Item::factory(),
            'current_quantity' => 1000,
            'average_cost'     => null,
            'status'           => 'active',
        ];
    }
}
```

**Step 7: Create ExpenseCategoryFactory**

```php
<?php
// database/factories/ExpenseCategoryFactory.php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id'    => null,
            'name'       => $this->faker->word(),
            'code'       => $this->faker->unique()->slug(1),
            'is_system'  => true,
            'is_active'  => true,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function water(): static
    {
        return $this->state(['code' => 'water', 'name' => 'مياه']);
    }

    public function bedding(): static
    {
        return $this->state(['code' => 'bedding', 'name' => 'فرشة']);
    }

    public function other(): static
    {
        return $this->state(['code' => 'other', 'name' => 'أخرى']);
    }
}
```

**Step 8: Add `HasFactory` to Warehouse and Expense models if missing**

Check `app/Models/Warehouse.php` — if it does not already have `use HasFactory;`, add it:
```php
use Illuminate\Database\Eloquent\Factories\HasFactory;
// and in the class body:
use HasFactory;
```

Do the same for `app/Models/Expense.php`.

**Step 9: Verify seeder runs**

```bash
cd backend && php artisan db:seed --class=ExpenseCategorySeeder
```
Expected: No errors. Check DB has 3 rows in `expense_categories` with codes `water`, `bedding`, `other`.

**Step 10: Commit**

```bash
cd backend
git add database/seeders/ExpenseCategorySeeder.php \
        database/seeders/DatabaseSeeder.php \
        database/factories/ItemTypeFactory.php \
        database/factories/ItemFactory.php \
        database/factories/WarehouseFactory.php \
        database/factories/WarehouseItemFactory.php \
        database/factories/ExpenseCategoryFactory.php
git commit -m "feat: add expense category seeder and model factories for inventory/expense"
```

---

## Task 3: Inventory Items Endpoint `GET /api/inventory/items`

**Files:**
- Create: `app/Http/Controllers/Api/Inventory/InventoryController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Inventory/InventoryItemsTest.php`

**Step 1: Write the failing test**

```php
<?php
// tests/Feature/Inventory/InventoryItemsTest.php

namespace Tests\Feature\Inventory;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryItemsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsMember(Farm $farm): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    public function test_returns_all_active_items_for_farm(): void
    {
        $farm     = Farm::factory()->create();
        $user     = $this->actingAsMember($farm);
        $feedType = ItemType::factory()->feed()->create();
        Item::factory()->forFarm($farm)->create(['name' => 'علف أ', 'item_type_id' => $feedType->id]);
        Item::factory()->forFarm($farm)->create(['name' => 'علف ب', 'item_type_id' => $feedType->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson('/api/inventory/items')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_filters_by_feed_type(): void
    {
        $farm     = Farm::factory()->create();
        $user     = $this->actingAsMember($farm);
        $feedType = ItemType::factory()->feed()->create();
        $medType  = ItemType::factory()->medicine()->create();

        Item::factory()->forFarm($farm)->create(['name' => 'علف أ', 'item_type_id' => $feedType->id]);
        Item::factory()->forFarm($farm)->create(['name' => 'دواء أ', 'item_type_id' => $medType->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson('/api/inventory/items?type=feed')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'علف أ');
    }

    public function test_filters_by_medicine_type(): void
    {
        $farm    = Farm::factory()->create();
        $user    = $this->actingAsMember($farm);
        $medType = ItemType::factory()->medicine()->create();

        Item::factory()->forFarm($farm)->create(['item_type_id' => $medType->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson('/api/inventory/items?type=medicine')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type_code', 'medicine');
    }

    public function test_does_not_return_items_from_other_farms(): void
    {
        $farm1    = Farm::factory()->create();
        $farm2    = Farm::factory()->create();
        $user     = $this->actingAsMember($farm1);
        $feedType = ItemType::factory()->feed()->create();

        Item::factory()->create(['farm_id' => $farm2->id, 'item_type_id' => $feedType->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm1->id])
            ->getJson('/api/inventory/items')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_requires_authentication(): void
    {
        $farm = Farm::factory()->create();
        $this->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson('/api/inventory/items')
            ->assertStatus(401);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
cd backend && php artisan test tests/Feature/Inventory/InventoryItemsTest.php
```
Expected: FAIL — route not found (404).

**Step 3: Create InventoryController**

```php
<?php
// app/Http/Controllers/Api/Inventory/InventoryController.php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    // ── GET /api/inventory/items?type=feed|medicine ───────────────────────────

    public function items(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $type   = $request->query('type');

        $query = Item::where('farm_id', $farmId)
            ->where('status', 'active')
            ->with(['itemType:id,code,name']);

        if ($type) {
            $query->whereHas('itemType', fn ($q) => $q->where('code', $type));
        }

        $items = $query->orderBy('name')->get();

        return response()->json([
            'data' => $items->map(fn (Item $item) => [
                'id'           => $item->id,
                'name'         => $item->name,
                'input_unit'   => $item->input_unit,
                'content_unit' => $item->content_unit,
                'unit_value'   => (float) $item->unit_value,
                'type_code'    => $item->itemType?->code,
            ]),
        ]);
    }
}
```

**Step 4: Add route to api.php**

Inside the `auth:sanctum` + `farm.scope` + `farm.active` middleware group, add:

```php
// ── V1-D: Inventory ──────────────────────────────────────────────────────────
Route::prefix('inventory')->group(function (): void {
    Route::get('/items', [InventoryController::class, 'items']);
});
```

Also add the import at the top of `routes/api.php`:
```php
use App\Http\Controllers\Api\Inventory\InventoryController;
```

**Step 5: Run test to verify it passes**

```bash
cd backend && php artisan test tests/Feature/Inventory/InventoryItemsTest.php
```
Expected: 5 tests PASS.

**Step 6: Run full test suite**

```bash
cd backend && php artisan test
```
Expected: All green.

**Step 7: Commit**

```bash
cd backend
git add app/Http/Controllers/Api/Inventory/InventoryController.php routes/api.php \
        tests/Feature/Inventory/InventoryItemsTest.php
git commit -m "feat: add inventory items endpoint with type filter"
```

---

## Task 4: Feed Log Endpoint `POST /api/flocks/{flock}/feed-logs`

**Files:**
- Create: `app/Http/Requests/FeedLog/StoreFeedLogRequest.php`
- Create: `app/Actions/FeedLog/CreateFeedLogAction.php`
- Create: `app/Http/Controllers/Api/FeedLog/FeedLogController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/FeedLog/FeedLogCreateTest.php`

**Step 1: Write the failing test**

```php
<?php
// tests/Feature/FeedLog/FeedLogCreateTest.php

namespace Tests\Feature\FeedLog;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedLogCreateTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsMember(Farm $farm): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    private function makeFeedItem(Farm $farm): Item
    {
        $feedType = ItemType::factory()->feed()->create();
        return Item::factory()->forFarm($farm)->create(['item_type_id' => $feedType->id]);
    }

    public function test_creates_feed_log_successfully(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $item  = $this->makeFeedItem($farm);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/feed-logs", [
                'item_id'  => $item->id,
                'quantity' => 50,
                'notes'    => 'تغذية صباحية',
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'تم تسجيل العلف بنجاح');

        $this->assertDatabaseHas('flock_feed_logs', [
            'flock_id'   => $flock->id,
            'item_id'    => $item->id,
            'quantity'   => 50,
            'created_by' => $user->id,
        ]);
    }

    public function test_deducts_from_inventory_when_warehouse_exists(): void
    {
        $farm      = Farm::factory()->create();
        $user      = $this->actingAsMember($farm);
        $flock     = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $item      = $this->makeFeedItem($farm);
        $warehouse = Warehouse::factory()->forFarm($farm)->create();
        WarehouseItem::factory()->create([
            'farm_id'          => $farm->id,
            'warehouse_id'     => $warehouse->id,
            'item_id'          => $item->id,
            'current_quantity' => 500,
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/feed-logs", ['item_id' => $item->id, 'quantity' => 100])
            ->assertStatus(201);

        $this->assertDatabaseHas('warehouse_items', [
            'item_id'          => $item->id,
            'warehouse_id'     => $warehouse->id,
            'current_quantity' => 400,
        ]);

        $this->assertDatabaseHas('inventory_transactions', [
            'item_id'       => $item->id,
            'flock_id'      => $flock->id,
            'direction'     => 'out',
            'source_module' => 'flock_feed',
        ]);
    }

    public function test_creates_log_even_without_warehouse(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $item  = $this->makeFeedItem($farm);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/feed-logs", ['item_id' => $item->id, 'quantity' => 30])
            ->assertStatus(201);

        $this->assertDatabaseHas('flock_feed_logs', ['flock_id' => $flock->id, 'inventory_transaction_id' => null]);
    }

    public function test_cannot_log_feed_on_closed_flock(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->closed()->create(['farm_id' => $farm->id]);
        $item  = $this->makeFeedItem($farm);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/feed-logs", ['item_id' => $item->id, 'quantity' => 10])
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن تسجيل علف على فوج غير نشط');
    }

    public function test_rejects_item_from_another_farm(): void
    {
        $farm1 = Farm::factory()->create();
        $farm2 = Farm::factory()->create();
        $user  = $this->actingAsMember($farm1);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm1->id]);
        $item  = $this->makeFeedItem($farm2);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm1->id])
            ->postJson("/api/flocks/{$flock->id}/feed-logs", ['item_id' => $item->id, 'quantity' => 10])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['item_id']);
    }

    public function test_requires_authentication(): void
    {
        $farm  = Farm::factory()->create();
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $this->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/feed-logs", ['item_id' => 1, 'quantity' => 10])
            ->assertStatus(401);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
cd backend && php artisan test tests/Feature/FeedLog/FeedLogCreateTest.php
```
Expected: FAIL — route not found.

**Step 3: Create StoreFeedLogRequest**

```php
<?php
// app/Http/Requests/FeedLog/StoreFeedLogRequest.php

namespace App\Http\Requests\FeedLog;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeedLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $farmId = $this->attributes->get('farm_id');

        return [
            'item_id'    => ['required', 'integer', "exists:items,id,farm_id,{$farmId},status,active"],
            'quantity'   => ['required', 'numeric', 'min:0.001'],
            'unit_label' => ['nullable', 'string', 'max:50'],
            'notes'      => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'item_id.required' => 'يجب اختيار صنف العلف',
            'item_id.exists'   => 'الصنف غير موجود في مخزون هذه المزرعة',
            'quantity.required' => 'الكمية مطلوبة',
            'quantity.min'      => 'الكمية يجب أن تكون أكبر من صفر',
        ];
    }
}
```

**Step 4: Create CreateFeedLogAction**

```php
<?php
// app/Actions/FeedLog/CreateFeedLogAction.php

namespace App\Actions\FeedLog;

use App\Models\Flock;
use App\Models\FlockFeedLog;
use App\Models\InventoryTransaction;
use App\Models\WarehouseItem;

class CreateFeedLogAction
{
    /**
     * @param  array<string, mixed>  $data  validated: item_id, quantity, unit_label?, notes?
     * @throws \Exception 422 if flock is not active
     */
    public function execute(Flock $flock, int $userId, array $data): FlockFeedLog
    {
        if ($flock->status !== 'active') {
            throw new \Exception('لا يمكن تسجيل علف على فوج غير نشط', 422);
        }

        // ── Inventory deduction (optional — skipped if no warehouse) ───────────
        $warehouseItem = WarehouseItem::where('item_id', $data['item_id'])
            ->whereHas('warehouse', fn ($q) => $q->where('farm_id', $flock->farm_id)->where('is_active', true))
            ->first();

        $inventoryTransactionId = null;

        if ($warehouseItem) {
            $txn = InventoryTransaction::create([
                'farm_id'           => $flock->farm_id,
                'warehouse_id'      => $warehouseItem->warehouse_id,
                'item_id'           => $data['item_id'],
                'flock_id'          => $flock->id,
                'transaction_date'  => now()->toDateString(),
                'transaction_type'  => 'out',
                'direction'         => 'out',
                'source_module'     => 'flock_feed',
                'computed_quantity' => $data['quantity'],
                'created_by'        => $userId,
                'updated_by'        => $userId,
            ]);

            $warehouseItem->decrement('current_quantity', $data['quantity']);
            $inventoryTransactionId = $txn->id;
        }

        return FlockFeedLog::create([
            'farm_id'                  => $flock->farm_id,
            'flock_id'                 => $flock->id,
            'item_id'                  => $data['item_id'],
            'entry_date'               => now()->toDateString(),
            'quantity'                 => $data['quantity'],
            'unit_label'               => $data['unit_label'] ?? null,
            'notes'                    => $data['notes'] ?? null,
            'worker_id'                => $userId,
            'inventory_transaction_id' => $inventoryTransactionId,
            'editable_until'           => now()->addMinutes(15),
            'created_by'               => $userId,
            'updated_by'               => $userId,
        ]);
    }
}
```

**Step 5: Create FeedLogController**

```php
<?php
// app/Http/Controllers/Api/FeedLog/FeedLogController.php

namespace App\Http\Controllers\Api\FeedLog;

use App\Actions\FeedLog\CreateFeedLogAction;
use App\Actions\Flock\ShowFlockAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\FeedLog\StoreFeedLogRequest;
use Illuminate\Http\JsonResponse;

class FeedLogController extends Controller
{
    public function __construct(
        private readonly ShowFlockAction     $showFlockAction,
        private readonly CreateFeedLogAction $createAction,
    ) {}

    // ── POST /api/flocks/{flock}/feed-logs ────────────────────────────────────

    public function store(StoreFeedLogRequest $request, int $flockId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $userId = $request->user()->id;

        try {
            $flock   = $this->showFlockAction->execute($farmId, $flockId);
            $feedLog = $this->createAction->execute($flock, $userId, $request->validated());
        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            return response()->json(
                ['message' => $e->getMessage()],
                $code >= 400 && $code < 600 ? $code : 422
            );
        }

        return response()->json([
            'message' => 'تم تسجيل العلف بنجاح',
            'data'    => [
                'id'       => $feedLog->id,
                'item_id'  => $feedLog->item_id,
                'quantity' => $feedLog->quantity,
            ],
        ], 201);
    }
}
```

**Step 6: Add route to api.php**

Inside the existing `flocks/{flock}` area, after mortalities, add:

```php
// ── V1-E: Feed Logs ───────────────────────────────────────────────────────────
Route::post('flocks/{flock}/feed-logs', [FeedLogController::class, 'store']);
```

Add import:
```php
use App\Http\Controllers\Api\FeedLog\FeedLogController;
```

**Step 7: Run test to verify it passes**

```bash
cd backend && php artisan test tests/Feature/FeedLog/FeedLogCreateTest.php
```
Expected: 6 tests PASS.

**Step 8: Run full test suite**

```bash
cd backend && php artisan test
```
Expected: All green.

**Step 9: Commit**

```bash
cd backend
git add app/Http/Requests/FeedLog/ app/Actions/FeedLog/ \
        app/Http/Controllers/Api/FeedLog/ routes/api.php \
        tests/Feature/FeedLog/
git commit -m "feat: add feed log endpoint with optional inventory deduction"
```

---

## Task 5: Medicine Log Endpoint `POST /api/flocks/{flock}/medicine-logs`

**Files:**
- Create: `app/Http/Requests/MedicineLog/StoreMedicineLogRequest.php`
- Create: `app/Actions/MedicineLog/CreateMedicineLogAction.php`
- Create: `app/Http/Controllers/Api/MedicineLog/MedicineLogController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/MedicineLog/MedicineLogCreateTest.php`

**Step 1: Write the failing test**

```php
<?php
// tests/Feature/MedicineLog/MedicineLogCreateTest.php

namespace Tests\Feature\MedicineLog;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicineLogCreateTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsMember(Farm $farm): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    private function makeMedicineItem(Farm $farm): Item
    {
        $medType = ItemType::factory()->medicine()->create();
        return Item::factory()->forFarm($farm)->create(['item_type_id' => $medType->id]);
    }

    public function test_creates_medicine_log_successfully(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $item  = $this->makeMedicineItem($farm);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/medicine-logs", [
                'item_id'  => $item->id,
                'quantity' => 5,
                'notes'    => 'جرعة وقاية',
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'تم تسجيل الدواء بنجاح');

        $this->assertDatabaseHas('flock_medicines', [
            'flock_id'   => $flock->id,
            'item_id'    => $item->id,
            'quantity'   => 5,
            'created_by' => $user->id,
        ]);
    }

    public function test_cannot_log_medicine_on_closed_flock(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->closed()->create(['farm_id' => $farm->id]);
        $item  = $this->makeMedicineItem($farm);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/medicine-logs", ['item_id' => $item->id, 'quantity' => 2])
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن تسجيل دواء على فوج غير نشط');
    }

    public function test_rejects_item_from_another_farm(): void
    {
        $farm1 = Farm::factory()->create();
        $farm2 = Farm::factory()->create();
        $user  = $this->actingAsMember($farm1);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm1->id]);
        $item  = $this->makeMedicineItem($farm2);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm1->id])
            ->postJson("/api/flocks/{$flock->id}/medicine-logs", ['item_id' => $item->id, 'quantity' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['item_id']);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
cd backend && php artisan test tests/Feature/MedicineLog/MedicineLogCreateTest.php
```
Expected: FAIL — route not found.

**Step 3: Create StoreMedicineLogRequest**

```php
<?php
// app/Http/Requests/MedicineLog/StoreMedicineLogRequest.php

namespace App\Http\Requests\MedicineLog;

use Illuminate\Foundation\Http\FormRequest;

class StoreMedicineLogRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $farmId = $this->attributes->get('farm_id');

        return [
            'item_id'    => ['required', 'integer', "exists:items,id,farm_id,{$farmId},status,active"],
            'quantity'   => ['required', 'numeric', 'min:0.001'],
            'unit_label' => ['nullable', 'string', 'max:50'],
            'notes'      => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'item_id.required' => 'يجب اختيار صنف الدواء',
            'item_id.exists'   => 'الصنف غير موجود في مخزون هذه المزرعة',
            'quantity.required' => 'الكمية مطلوبة',
            'quantity.min'      => 'الكمية يجب أن تكون أكبر من صفر',
        ];
    }
}
```

**Step 4: Create CreateMedicineLogAction**

```php
<?php
// app/Actions/MedicineLog/CreateMedicineLogAction.php

namespace App\Actions\MedicineLog;

use App\Models\Flock;
use App\Models\FlockMedicine;
use App\Models\InventoryTransaction;
use App\Models\WarehouseItem;

class CreateMedicineLogAction
{
    /**
     * @throws \Exception 422 if flock is not active
     */
    public function execute(Flock $flock, int $userId, array $data): FlockMedicine
    {
        if ($flock->status !== 'active') {
            throw new \Exception('لا يمكن تسجيل دواء على فوج غير نشط', 422);
        }

        $warehouseItem = WarehouseItem::where('item_id', $data['item_id'])
            ->whereHas('warehouse', fn ($q) => $q->where('farm_id', $flock->farm_id)->where('is_active', true))
            ->first();

        $inventoryTransactionId = null;

        if ($warehouseItem) {
            $txn = InventoryTransaction::create([
                'farm_id'           => $flock->farm_id,
                'warehouse_id'      => $warehouseItem->warehouse_id,
                'item_id'           => $data['item_id'],
                'flock_id'          => $flock->id,
                'transaction_date'  => now()->toDateString(),
                'transaction_type'  => 'out',
                'direction'         => 'out',
                'source_module'     => 'flock_medicine',
                'computed_quantity' => $data['quantity'],
                'created_by'        => $userId,
                'updated_by'        => $userId,
            ]);

            $warehouseItem->decrement('current_quantity', $data['quantity']);
            $inventoryTransactionId = $txn->id;
        }

        return FlockMedicine::create([
            'farm_id'                  => $flock->farm_id,
            'flock_id'                 => $flock->id,
            'item_id'                  => $data['item_id'],
            'entry_date'               => now()->toDateString(),
            'quantity'                 => $data['quantity'],
            'unit_label'               => $data['unit_label'] ?? null,
            'notes'                    => $data['notes'] ?? null,
            'worker_id'                => $userId,
            'inventory_transaction_id' => $inventoryTransactionId,
            'editable_until'           => now()->addMinutes(15),
            'created_by'               => $userId,
            'updated_by'               => $userId,
        ]);
    }
}
```

**Step 5: Create MedicineLogController**

```php
<?php
// app/Http/Controllers/Api/MedicineLog/MedicineLogController.php

namespace App\Http\Controllers\Api\MedicineLog;

use App\Actions\Flock\ShowFlockAction;
use App\Actions\MedicineLog\CreateMedicineLogAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\MedicineLog\StoreMedicineLogRequest;
use Illuminate\Http\JsonResponse;

class MedicineLogController extends Controller
{
    public function __construct(
        private readonly ShowFlockAction          $showFlockAction,
        private readonly CreateMedicineLogAction  $createAction,
    ) {}

    public function store(StoreMedicineLogRequest $request, int $flockId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $userId = $request->user()->id;

        try {
            $flock   = $this->showFlockAction->execute($farmId, $flockId);
            $medicine = $this->createAction->execute($flock, $userId, $request->validated());
        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            return response()->json(
                ['message' => $e->getMessage()],
                $code >= 400 && $code < 600 ? $code : 422
            );
        }

        return response()->json([
            'message' => 'تم تسجيل الدواء بنجاح',
            'data'    => ['id' => $medicine->id, 'item_id' => $medicine->item_id, 'quantity' => $medicine->quantity],
        ], 201);
    }
}
```

**Step 6: Add route to api.php**

```php
use App\Http\Controllers\Api\MedicineLog\MedicineLogController;
// In the farm-scoped routes group:
Route::post('flocks/{flock}/medicine-logs', [MedicineLogController::class, 'store']);
```

**Step 7: Run tests and commit**

```bash
cd backend && php artisan test tests/Feature/MedicineLog/MedicineLogCreateTest.php
# Expected: 3 PASS
php artisan test
# Expected: All green
git add app/Http/Requests/MedicineLog/ app/Actions/MedicineLog/ \
        app/Http/Controllers/Api/MedicineLog/ routes/api.php \
        tests/Feature/MedicineLog/
git commit -m "feat: add medicine log endpoint with optional inventory deduction"
```

---

## Task 6: Flock Expense Endpoint `POST /api/flocks/{flock}/expenses`

**Files:**
- Create: `app/Http/Requests/Expense/StoreFlockExpenseRequest.php`
- Create: `app/Actions/Expense/CreateFlockExpenseAction.php`
- Create: `app/Http/Controllers/Api/Expense/FlockExpenseController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Expense/FlockExpenseCreateTest.php`

**Step 1: Write the failing test**

```php
<?php
// tests/Feature/Expense/FlockExpenseCreateTest.php

namespace Tests\Feature\Expense;

use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlockExpenseCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed system expense categories needed by the action
        ExpenseCategory::factory()->water()->create();
        ExpenseCategory::factory()->bedding()->create();
        ExpenseCategory::factory()->other()->create();
    }

    private function actingAsMember(Farm $farm): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    public function test_creates_expense_successfully(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/expenses", [
                'expense_type' => 'water',
                'quantity'     => 10,
                'unit_price'   => 15,
                'total_amount' => 150,
                'notes'        => 'مياه شرب',
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'تم تسجيل المصروف بنجاح');

        $this->assertDatabaseHas('expenses', [
            'flock_id'     => $flock->id,
            'expense_type' => 'water',
            'total_amount' => 150,
            'payment_status' => 'paid',
            'created_by'   => $user->id,
        ]);
    }

    public function test_creates_bedding_expense(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/expenses", [
                'expense_type' => 'bedding',
                'total_amount' => 500,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('expenses', ['flock_id' => $flock->id, 'expense_type' => 'bedding']);
    }

    public function test_cannot_add_expense_on_closed_flock(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->closed()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/expenses", ['expense_type' => 'water', 'total_amount' => 100])
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن تسجيل مصروف على فوج غير نشط');
    }

    public function test_rejects_invalid_expense_type(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/expenses", ['expense_type' => 'invalid', 'total_amount' => 100])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['expense_type']);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
cd backend && php artisan test tests/Feature/Expense/FlockExpenseCreateTest.php
```
Expected: FAIL — route not found.

**Step 3: Create StoreFlockExpenseRequest**

```php
<?php
// app/Http/Requests/Expense/StoreFlockExpenseRequest.php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

class StoreFlockExpenseRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'expense_type' => ['required', 'string', 'in:water,bedding,other'],
            'quantity'     => ['nullable', 'numeric', 'min:0'],
            'unit_price'   => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'notes'        => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'expense_type.required' => 'نوع المصروف مطلوب',
            'expense_type.in'       => 'نوع المصروف يجب أن يكون: مياه أو فرشة أو أخرى',
            'total_amount.required' => 'المبلغ الإجمالي مطلوب',
            'total_amount.min'      => 'المبلغ يجب أن يكون أكبر من أو يساوي صفر',
        ];
    }
}
```

**Step 4: Create CreateFlockExpenseAction**

```php
<?php
// app/Actions/Expense/CreateFlockExpenseAction.php

namespace App\Actions\Expense;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Flock;

class CreateFlockExpenseAction
{
    /**
     * @throws \Exception 422 if flock is not active or category missing
     */
    public function execute(Flock $flock, int $userId, array $data): Expense
    {
        if ($flock->status !== 'active') {
            throw new \Exception('لا يمكن تسجيل مصروف على فوج غير نشط', 422);
        }

        $category = ExpenseCategory::where('code', $data['expense_type'])
            ->where('is_system', true)
            ->whereNull('farm_id')
            ->first();

        if (! $category) {
            throw new \Exception('تصنيف المصروف غير موجود في النظام', 422);
        }

        return Expense::create([
            'farm_id'             => $flock->farm_id,
            'flock_id'            => $flock->id,
            'expense_category_id' => $category->id,
            'entry_date'          => now()->toDateString(),
            'expense_type'        => $data['expense_type'],
            'quantity'            => $data['quantity'] ?? null,
            'unit_price'          => $data['unit_price'] ?? null,
            'total_amount'        => $data['total_amount'],
            'paid_amount'         => $data['total_amount'],
            'remaining_amount'    => 0,
            'payment_status'      => 'paid',
            'notes'               => $data['notes'] ?? null,
            'worker_id'           => $userId,
            'created_by'          => $userId,
            'updated_by'          => $userId,
        ]);
    }
}
```

**Step 5: Create FlockExpenseController**

```php
<?php
// app/Http/Controllers/Api/Expense/FlockExpenseController.php

namespace App\Http\Controllers\Api\Expense;

use App\Actions\Expense\CreateFlockExpenseAction;
use App\Actions\Flock\ShowFlockAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreFlockExpenseRequest;
use Illuminate\Http\JsonResponse;

class FlockExpenseController extends Controller
{
    public function __construct(
        private readonly ShowFlockAction          $showFlockAction,
        private readonly CreateFlockExpenseAction $createAction,
    ) {}

    public function store(StoreFlockExpenseRequest $request, int $flockId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $userId = $request->user()->id;

        try {
            $flock   = $this->showFlockAction->execute($farmId, $flockId);
            $expense = $this->createAction->execute($flock, $userId, $request->validated());
        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            return response()->json(
                ['message' => $e->getMessage()],
                $code >= 400 && $code < 600 ? $code : 422
            );
        }

        return response()->json([
            'message' => 'تم تسجيل المصروف بنجاح',
            'data'    => ['id' => $expense->id, 'expense_type' => $expense->expense_type, 'total_amount' => $expense->total_amount],
        ], 201);
    }
}
```

**Step 6: Add route to api.php**

```php
use App\Http\Controllers\Api\Expense\FlockExpenseController;
// In farm-scoped group:
Route::post('flocks/{flock}/expenses', [FlockExpenseController::class, 'store']);
```

**Step 7: Run tests and commit**

```bash
cd backend && php artisan test tests/Feature/Expense/FlockExpenseCreateTest.php
# Expected: 4 PASS
php artisan test
# Expected: All green
git add app/Http/Requests/Expense/ app/Actions/Expense/ \
        app/Http/Controllers/Api/Expense/ routes/api.php \
        tests/Feature/Expense/
git commit -m "feat: add flock expense endpoint (water/bedding/other)"
```

---

## Task 7: Today Summary Endpoint `GET /api/flocks/{flock}/today-summary`

**Files:**
- Create: `app/Actions/Flock/TodaySummaryAction.php`
- Modify: `app/Http/Controllers/Api/Flock/FlockController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Flock/TodaySummaryTest.php`

**Step 1: Write the failing test**

```php
<?php
// tests/Feature/Flock/TodaySummaryTest.php

namespace Tests\Feature\Flock;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\FlockFeedLog;
use App\Models\FlockMedicine;
use App\Models\FlockMortality;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodaySummaryTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsMember(Farm $farm): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    public function test_returns_empty_summary_when_no_entries_today(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/flocks/{$flock->id}/today-summary")
            ->assertStatus(200)
            ->assertJsonPath('data.date', now()->toDateString())
            ->assertJsonPath('data.mortalities.total', 0)
            ->assertJsonPath('data.feed.total', 0)
            ->assertJsonPath('data.medicines.total', 0)
            ->assertJsonPath('data.expenses.total', 0);
    }

    public function test_aggregates_mortalities_for_today(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        FlockMortality::factory()->create(['flock_id' => $flock->id, 'farm_id' => $farm->id, 'quantity' => 10, 'entry_date' => now()->toDateString()]);
        FlockMortality::factory()->create(['flock_id' => $flock->id, 'farm_id' => $farm->id, 'quantity' => 5,  'entry_date' => now()->toDateString()]);
        // Yesterday — should NOT appear
        FlockMortality::factory()->create(['flock_id' => $flock->id, 'farm_id' => $farm->id, 'quantity' => 99, 'entry_date' => now()->subDay()->toDateString()]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/flocks/{$flock->id}/today-summary")
            ->assertStatus(200)
            ->assertJsonPath('data.mortalities.total', 15)
            ->assertJsonCount(2, 'data.mortalities.entries');
    }

    public function test_aggregates_feed_for_today(): void
    {
        $farm     = Farm::factory()->create();
        $user     = $this->actingAsMember($farm);
        $flock    = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $feedType = ItemType::factory()->feed()->create();
        $item     = Item::factory()->forFarm($farm)->create(['item_type_id' => $feedType->id, 'name' => 'علف أ']);

        FlockFeedLog::factory()->create([
            'flock_id'   => $flock->id,
            'farm_id'    => $farm->id,
            'item_id'    => $item->id,
            'quantity'   => 50,
            'entry_date' => now()->toDateString(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/flocks/{$flock->id}/today-summary")
            ->assertStatus(200)
            ->assertJsonPath('data.feed.total', 50)
            ->assertJsonPath('data.feed.entries.0.item_name', 'علف أ');
    }

    public function test_aggregates_expenses_for_today(): void
    {
        $farm     = Farm::factory()->create();
        $user     = $this->actingAsMember($farm);
        $flock    = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $category = ExpenseCategory::factory()->water()->create();

        Expense::factory()->create([
            'farm_id'             => $farm->id,
            'flock_id'            => $flock->id,
            'expense_category_id' => $category->id,
            'expense_type'        => 'water',
            'total_amount'        => 200,
            'paid_amount'         => 200,
            'remaining_amount'    => 0,
            'payment_status'      => 'paid',
            'entry_date'          => now()->toDateString(),
            'created_by'          => $user->id,
            'updated_by'          => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/flocks/{$flock->id}/today-summary")
            ->assertStatus(200)
            ->assertJsonPath('data.expenses.total', 200)
            ->assertJsonPath('data.expenses.entries.0.type', 'water');
    }

    public function test_requires_authentication(): void
    {
        $farm  = Farm::factory()->create();
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $this->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/flocks/{$flock->id}/today-summary")
            ->assertStatus(401);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
cd backend && php artisan test tests/Feature/Flock/TodaySummaryTest.php
```
Expected: FAIL — route not found.

**Step 3: Create factories needed for test**

Create `database/factories/FlockFeedLogFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Flock;
use App\Models\Item;
use App\Models\ItemType;
use Illuminate\Database\Eloquent\Factories\Factory;

class FlockFeedLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id'                  => Farm::factory(),
            'flock_id'                 => Flock::factory(),
            'item_id'                  => Item::factory(),
            'entry_date'               => now()->toDateString(),
            'quantity'                 => $this->faker->randomFloat(2, 10, 500),
            'unit_label'               => null,
            'notes'                    => null,
            'worker_id'                => null,
            'inventory_transaction_id' => null,
            'editable_until'           => now()->addMinutes(15),
            'created_by'               => null,
            'updated_by'               => null,
        ];
    }
}
```

Create `database/factories/ExpenseFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\Flock;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 50, 1000);
        return [
            'farm_id'             => Farm::factory(),
            'flock_id'            => Flock::factory(),
            'expense_category_id' => ExpenseCategory::factory(),
            'entry_date'          => now()->toDateString(),
            'expense_type'        => 'other',
            'quantity'            => null,
            'unit_price'          => null,
            'total_amount'        => $amount,
            'paid_amount'         => $amount,
            'remaining_amount'    => 0,
            'payment_status'      => 'paid',
            'notes'               => null,
            'created_by'          => null,
            'updated_by'          => null,
        ];
    }
}
```

**Step 4: Create TodaySummaryAction**

```php
<?php
// app/Actions/Flock/TodaySummaryAction.php

namespace App\Actions\Flock;

use App\Models\Expense;
use App\Models\Flock;
use App\Models\FlockFeedLog;
use App\Models\FlockMedicine;
use App\Models\FlockMortality;

class TodaySummaryAction
{
    public function execute(Flock $flock): array
    {
        $today = now()->toDateString();

        $mortalities = FlockMortality::where('flock_id', $flock->id)
            ->where('entry_date', $today)
            ->get(['quantity', 'reason']);

        $feedLogs = FlockFeedLog::where('flock_id', $flock->id)
            ->where('entry_date', $today)
            ->with('item:id,name,content_unit')
            ->get(['item_id', 'quantity', 'unit_label']);

        $medicines = FlockMedicine::where('flock_id', $flock->id)
            ->where('entry_date', $today)
            ->with('item:id,name,content_unit')
            ->get(['item_id', 'quantity', 'unit_label']);

        $expenses = Expense::where('flock_id', $flock->id)
            ->where('entry_date', $today)
            ->get(['expense_type', 'total_amount']);

        return [
            'date'       => $today,
            'mortalities' => [
                'entries' => $mortalities->map(fn ($m) => [
                    'quantity' => (int) $m->quantity,
                    'reason'   => $m->reason,
                ])->values()->toArray(),
                'total' => (int) $mortalities->sum('quantity'),
            ],
            'feed' => [
                'entries' => $feedLogs->map(fn ($f) => [
                    'item_name'  => $f->item?->name,
                    'quantity'   => (float) $f->quantity,
                    'unit_label' => $f->unit_label ?? $f->item?->content_unit,
                ])->values()->toArray(),
                'total' => (float) $feedLogs->sum('quantity'),
            ],
            'medicines' => [
                'entries' => $medicines->map(fn ($m) => [
                    'item_name'  => $m->item?->name,
                    'quantity'   => (float) $m->quantity,
                    'unit_label' => $m->unit_label ?? $m->item?->content_unit,
                ])->values()->toArray(),
                'total' => (float) $medicines->sum('quantity'),
            ],
            'expenses' => [
                'entries' => $expenses->map(fn ($e) => [
                    'type'         => $e->expense_type,
                    'total_amount' => (float) $e->total_amount,
                ])->values()->toArray(),
                'total' => (float) $expenses->sum('total_amount'),
            ],
        ];
    }
}
```

**Step 5: Add `todaySummary` method to FlockController**

Add this constructor injection and method to `app/Http/Controllers/Api/Flock/FlockController.php`:

```php
// Add to constructor:
private readonly TodaySummaryAction $todaySummaryAction,

// Add import at top:
use App\Actions\Flock\TodaySummaryAction;

// Add method:
// ── GET /api/flocks/{flock}/today-summary ─────────────────────────────────
public function todaySummary(Request $request, int $flockId): JsonResponse
{
    $farmId = $request->attributes->get('farm_id');

    try {
        $flock   = $this->showAction->execute($farmId, $flockId);
        $summary = $this->todaySummaryAction->execute($flock);
    } catch (\Exception $e) {
        return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 404);
    }

    return response()->json(['data' => $summary]);
}
```

**Step 6: Add route**

```php
// In the flocks prefix group:
Route::get('/{flock}/today-summary', [FlockController::class, 'todaySummary']);
```

**Step 7: Run tests and commit**

```bash
cd backend && php artisan test tests/Feature/Flock/TodaySummaryTest.php
# Expected: 5 PASS
php artisan test
# Expected: All green
git add app/Actions/Flock/TodaySummaryAction.php \
        app/Http/Controllers/Api/Flock/FlockController.php \
        database/factories/FlockFeedLogFactory.php \
        database/factories/ExpenseFactory.php \
        routes/api.php \
        tests/Feature/Flock/TodaySummaryTest.php
git commit -m "feat: add today-summary endpoint for active flock dashboard"
```

---

## Task 8: Frontend Types + API Clients

**Files:**
- Create: `frontend/src/types/dashboard.ts`
- Modify: `frontend/src/types/flock.ts`
- Create: `frontend/src/lib/api/inventory.ts`
- Create: `frontend/src/lib/api/quick-entry.ts`
- Modify: `frontend/src/lib/api/flocks.ts`

**Step 1: Update `src/types/flock.ts` — add new fields**

Add `total_mortality` and `remaining_count` to the `Flock` interface:

```typescript
// frontend/src/types/flock.ts
export type FlockStatus = 'active' | 'draft' | 'closed' | 'cancelled'

export interface Flock {
  id: number
  farm_id: number
  name: string
  status: FlockStatus
  start_date: string
  end_date: string | null
  initial_count: number
  current_age_days: number | null
  total_mortality: number
  remaining_count: number
  notes: string | null
  created_at: string
  updated_at: string
}

export interface CreateFlockPayload {
  name: string
  start_date: string
  initial_count: number
  notes?: string
}

export interface FlockListResponse {
  data: Flock[]
  meta?: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}
```

**Step 2: Create `src/types/dashboard.ts`**

```typescript
// frontend/src/types/dashboard.ts

export interface InventoryItem {
  id: number
  name: string
  input_unit: string
  content_unit: string
  unit_value: number
  type_code: string | null
}

export interface MortalityEntry {
  quantity: number
  reason: string | null
}

export interface FeedEntry {
  item_name: string | null
  quantity: number
  unit_label: string | null
}

export interface ExpenseEntry {
  type: 'water' | 'bedding' | 'other'
  total_amount: number
}

export interface TodaySummarySection<T> {
  entries: T[]
  total: number
}

export interface TodaySummary {
  date: string
  mortalities: TodaySummarySection<MortalityEntry>
  feed: TodaySummarySection<FeedEntry>
  medicines: TodaySummarySection<FeedEntry>
  expenses: TodaySummarySection<ExpenseEntry>
}
```

**Step 3: Create `src/lib/api/inventory.ts`**

```typescript
// frontend/src/lib/api/inventory.ts
import { apiClient } from './client'
import type { InventoryItem } from '@/types/dashboard'

export const inventoryApi = {
  listItems: (type?: 'feed' | 'medicine') =>
    apiClient
      .get<{ data: InventoryItem[] }>('/inventory/items', { params: type ? { type } : {} })
      .then((r) => r.data),
}
```

**Step 4: Create `src/lib/api/quick-entry.ts`**

```typescript
// frontend/src/lib/api/quick-entry.ts
import { apiClient } from './client'

export const quickEntryApi = {
  createMortality: (
    flockId: number,
    payload: { entry_date: string; quantity: number; reason?: string; notes?: string },
  ) =>
    apiClient
      .post<{ message: string }>(`/flocks/${flockId}/mortalities`, payload)
      .then((r) => r.data),

  createFeedLog: (
    flockId: number,
    payload: { item_id: number; quantity: number; unit_label?: string; notes?: string },
  ) =>
    apiClient
      .post<{ message: string }>(`/flocks/${flockId}/feed-logs`, payload)
      .then((r) => r.data),

  createMedicineLog: (
    flockId: number,
    payload: { item_id: number; quantity: number; unit_label?: string; notes?: string },
  ) =>
    apiClient
      .post<{ message: string }>(`/flocks/${flockId}/medicine-logs`, payload)
      .then((r) => r.data),

  createExpense: (
    flockId: number,
    payload: {
      expense_type: 'water' | 'bedding' | 'other'
      quantity?: number
      unit_price?: number
      total_amount: number
      notes?: string
    },
  ) =>
    apiClient
      .post<{ message: string }>(`/flocks/${flockId}/expenses`, payload)
      .then((r) => r.data),
}
```

**Step 5: Update `src/lib/api/flocks.ts` — add todaySummary**

```typescript
// frontend/src/lib/api/flocks.ts
import { apiClient } from './client'
import type { CreateFlockPayload, Flock, FlockListResponse } from '@/types/flock'
import type { TodaySummary } from '@/types/dashboard'

export const flocksApi = {
  list: () =>
    apiClient.get<FlockListResponse>('/flocks').then((r) => r.data),

  get: (id: number) =>
    apiClient.get<{ data: Flock }>(`/flocks/${id}`).then((r) => r.data),

  create: (payload: CreateFlockPayload) =>
    apiClient.post<{ data: Flock; message: string }>('/flocks', payload).then((r) => r.data),

  update: (id: number, payload: Partial<CreateFlockPayload>) =>
    apiClient.put<{ data: Flock; message: string }>(`/flocks/${id}`, payload).then((r) => r.data),

  todaySummary: (flockId: number) =>
    apiClient.get<{ data: TodaySummary }>(`/flocks/${flockId}/today-summary`).then((r) => r.data),
}
```

**Step 6: Commit**

```bash
cd frontend
git add src/types/flock.ts src/types/dashboard.ts \
        src/lib/api/inventory.ts src/lib/api/quick-entry.ts src/lib/api/flocks.ts
git commit -m "feat: add dashboard types and quick-entry/inventory API clients"
```

---

## Task 9: Dashboard Child Components

**Files:**
- Create: `frontend/src/app/(farm)/dashboard/FlockInfoCard.tsx`
- Create: `frontend/src/app/(farm)/dashboard/QuickEntryCard.tsx`
- Create: `frontend/src/app/(farm)/dashboard/DaySummaryCard.tsx`

**Step 1: Create `FlockInfoCard.tsx`**

```tsx
// frontend/src/app/(farm)/dashboard/FlockInfoCard.tsx
'use client'

import Link from 'next/link'
import { Bird, Clock, Hash, AlertTriangle } from 'lucide-react'
import { formatDate, formatNumber } from '@/lib/utils'
import type { Flock } from '@/types/flock'

interface Props {
  flock: Flock
}

const STATUS_LABEL: Record<string, string> = {
  active:    'نشط',
  draft:     'مسودة',
  closed:    'مغلق',
  cancelled: 'ملغى',
}

const STATUS_COLOR: Record<string, string> = {
  active:    'bg-green-50 text-green-700 border-green-200',
  draft:     'bg-amber-50 text-amber-700 border-amber-200',
  closed:    'bg-slate-100 text-slate-600 border-slate-200',
  cancelled: 'bg-red-50 text-red-600 border-red-200',
}

export function FlockInfoCard({ flock }: Props) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-5">
      <div className="mb-4 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Bird className="h-5 w-5 text-green-600" />
          <h2 className="text-base font-semibold text-slate-800">معلومات الفوج</h2>
        </div>
        <span className={`rounded-full border px-2.5 py-0.5 text-xs font-medium ${STATUS_COLOR[flock.status] ?? STATUS_COLOR.closed}`}>
          {STATUS_LABEL[flock.status] ?? flock.status}
        </span>
      </div>

      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div>
          <p className="text-xs text-slate-500">اسم الفوج</p>
          <Link href={`/flocks/${flock.id}`} className="mt-0.5 block font-semibold text-slate-800 hover:text-primary-600">
            {flock.name}
          </Link>
        </div>
        <div>
          <p className="text-xs text-slate-500">تاريخ البدء</p>
          <p className="mt-0.5 font-semibold text-slate-800">{formatDate(flock.start_date)}</p>
        </div>
        <div>
          <p className="flex items-center gap-1 text-xs text-slate-500">
            <Clock className="h-3 w-3" /> العمر
          </p>
          <p className="mt-0.5 font-semibold text-slate-800">
            {flock.current_age_days !== null ? `${flock.current_age_days} يوم` : '—'}
          </p>
        </div>
        <div>
          <p className="flex items-center gap-1 text-xs text-slate-500">
            <Hash className="h-3 w-3" /> العدد الأولي
          </p>
          <p className="mt-0.5 font-semibold text-slate-800">{formatNumber(flock.initial_count)}</p>
        </div>
        <div>
          <p className="text-xs text-slate-500">إجمالي النفوق</p>
          <p className="mt-0.5 font-semibold text-red-600">{formatNumber(flock.total_mortality)}</p>
        </div>
        <div>
          <p className="text-xs text-slate-500">المتبقي (حي)</p>
          <p className="mt-0.5 font-semibold text-green-700">{formatNumber(flock.remaining_count)}</p>
        </div>
      </div>

      {flock.status === 'closed' || flock.status === 'cancelled' ? (
        <div className="mt-4 flex items-center gap-2 rounded-lg bg-amber-50 px-4 py-2.5 text-sm text-amber-700">
          <AlertTriangle className="h-4 w-4 shrink-0" />
          <span>الفوج مغلق — لا يمكن إضافة سجلات جديدة</span>
        </div>
      ) : null}
    </div>
  )
}
```

**Step 2: Create `QuickEntryCard.tsx`**

```tsx
// frontend/src/app/(farm)/dashboard/QuickEntryCard.tsx
'use client'

import { useEffect, useState } from 'react'
import { quickEntryApi } from '@/lib/api/quick-entry'
import { inventoryApi } from '@/lib/api/inventory'
import { CheckCircle, AlertCircle } from 'lucide-react'
import type { Flock } from '@/types/flock'
import type { InventoryItem } from '@/types/dashboard'

type Tab = 'mortality' | 'feed' | 'medicine' | 'expense'

const TABS: { id: Tab; label: string }[] = [
  { id: 'mortality', label: 'نفوق' },
  { id: 'feed',      label: 'علف' },
  { id: 'medicine',  label: 'دواء' },
  { id: 'expense',   label: 'مصروف' },
]

const EXPENSE_TYPES = [
  { value: 'water',   label: 'مياه' },
  { value: 'bedding', label: 'فرشة' },
  { value: 'other',   label: 'أخرى' },
]

interface Props {
  flock: Flock
  onSuccess: () => void   // refetch today-summary after entry
}

export function QuickEntryCard({ flock, onSuccess }: Props) {
  const [activeTab, setActiveTab]   = useState<Tab>('mortality')
  const [loading, setLoading]       = useState(false)
  const [success, setSuccess]       = useState(false)
  const [error, setError]           = useState<string | null>(null)
  const [feedItems, setFeedItems]   = useState<InventoryItem[]>([])
  const [medItems, setMedItems]     = useState<InventoryItem[]>([])

  // Mortality form
  const [mQty, setMQty]       = useState('')
  const [mReason, setMReason] = useState('')
  const [mNotes, setMNotes]   = useState('')

  // Feed form
  const [fItemId, setFItemId] = useState('')
  const [fQty, setFQty]       = useState('')
  const [fNotes, setFNotes]   = useState('')

  // Medicine form
  const [dItemId, setDItemId] = useState('')
  const [dQty, setDQty]       = useState('')
  const [dNotes, setDNotes]   = useState('')

  // Expense form
  const [eType, setEType]   = useState<'water' | 'bedding' | 'other'>('water')
  const [eQty, setEQty]     = useState('')
  const [ePrice, setEPrice] = useState('')
  const [eNotes, setENotes] = useState('')
  const eTotal = eQty && ePrice ? (parseFloat(eQty) * parseFloat(ePrice)).toFixed(2) : ''

  useEffect(() => {
    inventoryApi.listItems('feed').then((r) => setFeedItems(r.data)).catch(() => {})
    inventoryApi.listItems('medicine').then((r) => setMedItems(r.data)).catch(() => {})
  }, [])

  const clearFeedback = () => { setSuccess(false); setError(null) }

  const handleSubmit = async () => {
    clearFeedback()
    setLoading(true)
    try {
      const today = new Date().toISOString().slice(0, 10)

      if (activeTab === 'mortality') {
        await quickEntryApi.createMortality(flock.id, {
          entry_date: today,
          quantity:   parseInt(mQty),
          reason:     mReason || undefined,
          notes:      mNotes || undefined,
        })
        setMQty(''); setMReason(''); setMNotes('')
      }

      if (activeTab === 'feed') {
        await quickEntryApi.createFeedLog(flock.id, {
          item_id:  parseInt(fItemId),
          quantity: parseFloat(fQty),
          notes:    fNotes || undefined,
        })
        setFItemId(''); setFQty(''); setFNotes('')
      }

      if (activeTab === 'medicine') {
        await quickEntryApi.createMedicineLog(flock.id, {
          item_id:  parseInt(dItemId),
          quantity: parseFloat(dQty),
          notes:    dNotes || undefined,
        })
        setDItemId(''); setDQty(''); setDNotes('')
      }

      if (activeTab === 'expense') {
        await quickEntryApi.createExpense(flock.id, {
          expense_type: eType,
          quantity:     eQty ? parseFloat(eQty) : undefined,
          unit_price:   ePrice ? parseFloat(ePrice) : undefined,
          total_amount: parseFloat(eTotal || '0'),
          notes:        eNotes || undefined,
        })
        setEQty(''); setEPrice(''); setENotes('')
      }

      setSuccess(true)
      onSuccess()
      setTimeout(() => setSuccess(false), 3000)
    } catch (err: unknown) {
      const axiosError = err as { response?: { data?: { message?: string } } }
      setError(axiosError?.response?.data?.message ?? 'حدث خطأ أثناء الحفظ')
    } finally {
      setLoading(false)
    }
  }

  const inputCls = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-100'
  const labelCls = 'block text-xs font-medium text-slate-600 mb-1'

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-5">
      <h2 className="mb-4 text-base font-semibold text-slate-800">إدخال سريع</h2>

      {/* Tabs */}
      <div className="mb-5 flex gap-1 rounded-lg bg-slate-100 p-1">
        {TABS.map((t) => (
          <button
            key={t.id}
            onClick={() => { setActiveTab(t.id); clearFeedback() }}
            className={`flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition ${
              activeTab === t.id
                ? 'bg-white text-slate-900 shadow-sm'
                : 'text-slate-500 hover:text-slate-700'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {/* Mortality */}
      {activeTab === 'mortality' && (
        <div className="space-y-3">
          <div>
            <label className={labelCls}>عدد النفوق *</label>
            <input type="number" min="1" className={inputCls} value={mQty} onChange={(e) => setMQty(e.target.value)} placeholder="0" />
          </div>
          <div>
            <label className={labelCls}>السبب</label>
            <input type="text" className={inputCls} value={mReason} onChange={(e) => setMReason(e.target.value)} placeholder="اختياري" />
          </div>
          <div>
            <label className={labelCls}>ملاحظات</label>
            <textarea className={inputCls} rows={2} value={mNotes} onChange={(e) => setMNotes(e.target.value)} placeholder="اختياري" />
          </div>
        </div>
      )}

      {/* Feed */}
      {activeTab === 'feed' && (
        <div className="space-y-3">
          <div>
            <label className={labelCls}>نوع العلف *</label>
            <select className={inputCls} value={fItemId} onChange={(e) => setFItemId(e.target.value)}>
              <option value="">— اختر صنف —</option>
              {feedItems.map((i) => (
                <option key={i.id} value={i.id}>{i.name} ({i.content_unit})</option>
              ))}
            </select>
            {feedItems.length === 0 && (
              <p className="mt-1 text-xs text-slate-400">لا توجد أصناف علف في المخزون</p>
            )}
          </div>
          <div>
            <label className={labelCls}>الكمية *</label>
            <input type="number" min="0.001" step="0.001" className={inputCls} value={fQty} onChange={(e) => setFQty(e.target.value)} placeholder="0" />
          </div>
          <div>
            <label className={labelCls}>ملاحظات</label>
            <textarea className={inputCls} rows={2} value={fNotes} onChange={(e) => setFNotes(e.target.value)} />
          </div>
        </div>
      )}

      {/* Medicine */}
      {activeTab === 'medicine' && (
        <div className="space-y-3">
          <div>
            <label className={labelCls}>نوع الدواء *</label>
            <select className={inputCls} value={dItemId} onChange={(e) => setDItemId(e.target.value)}>
              <option value="">— اختر صنف —</option>
              {medItems.map((i) => (
                <option key={i.id} value={i.id}>{i.name} ({i.content_unit})</option>
              ))}
            </select>
            {medItems.length === 0 && (
              <p className="mt-1 text-xs text-slate-400">لا توجد أصناف أدوية في المخزون</p>
            )}
          </div>
          <div>
            <label className={labelCls}>الكمية *</label>
            <input type="number" min="0.001" step="0.001" className={inputCls} value={dQty} onChange={(e) => setDQty(e.target.value)} placeholder="0" />
          </div>
          <div>
            <label className={labelCls}>ملاحظات</label>
            <textarea className={inputCls} rows={2} value={dNotes} onChange={(e) => setDNotes(e.target.value)} />
          </div>
        </div>
      )}

      {/* Expense */}
      {activeTab === 'expense' && (
        <div className="space-y-3">
          <div>
            <label className={labelCls}>نوع المصروف *</label>
            <select className={inputCls} value={eType} onChange={(e) => setEType(e.target.value as typeof eType)}>
              {EXPENSE_TYPES.map((t) => (
                <option key={t.value} value={t.value}>{t.label}</option>
              ))}
            </select>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className={labelCls}>الكمية</label>
              <input type="number" min="0" step="0.01" className={inputCls} value={eQty} onChange={(e) => setEQty(e.target.value)} placeholder="0" />
            </div>
            <div>
              <label className={labelCls}>سعر الوحدة</label>
              <input type="number" min="0" step="0.01" className={inputCls} value={ePrice} onChange={(e) => setEPrice(e.target.value)} placeholder="0" />
            </div>
          </div>
          <div>
            <label className={labelCls}>الإجمالي</label>
            <input
              type="number"
              className={`${inputCls} bg-slate-50`}
              value={eTotal}
              onChange={(e) => {
                // Allow manual override if qty/price not set
                if (!eQty || !ePrice) {
                  // The eTotal is derived — handle direct input as a workaround by resetting qty/price
                }
              }}
              readOnly={!!(eQty && ePrice)}
              placeholder="محسوب تلقائياً"
            />
          </div>
          <div>
            <label className={labelCls}>ملاحظات</label>
            <textarea className={inputCls} rows={2} value={eNotes} onChange={(e) => setENotes(e.target.value)} />
          </div>
        </div>
      )}

      {/* Feedback */}
      {success && (
        <div className="mt-3 flex items-center gap-2 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700">
          <CheckCircle className="h-4 w-4" /> تم الحفظ بنجاح
        </div>
      )}
      {error && (
        <div className="mt-3 flex items-center gap-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
          <AlertCircle className="h-4 w-4" /> {error}
        </div>
      )}

      {/* Submit */}
      <button
        onClick={handleSubmit}
        disabled={loading}
        className="mt-4 w-full rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700 disabled:opacity-50"
      >
        {loading ? 'جاري الحفظ...' : 'حفظ'}
      </button>
    </div>
  )
}
```

**Step 3: Create `DaySummaryCard.tsx`**

```tsx
// frontend/src/app/(farm)/dashboard/DaySummaryCard.tsx
'use client'

import { formatNumber } from '@/lib/utils'
import type { TodaySummary } from '@/types/dashboard'

interface Props {
  summary: TodaySummary | null
  loading: boolean
}

const EXPENSE_LABEL: Record<string, string> = {
  water:   'مياه',
  bedding: 'فرشة',
  other:   'أخرى',
}

function SumLine({ values, unit }: { values: number[]; unit?: string }) {
  if (values.length === 0) return <span className="text-slate-400">—</span>
  const total = values.reduce((a, b) => a + b, 0)
  const parts = values.map((v) => formatNumber(v)).join('+')
  const totalStr = formatNumber(total)
  return (
    <span className="font-semibold text-slate-800">
      {values.length > 1 ? `${parts}=${totalStr}` : totalStr}
      {unit ? ` ${unit}` : ''}
    </span>
  )
}

export function DaySummaryCard({ summary, loading }: Props) {
  if (loading) {
    return (
      <div className="rounded-xl border border-slate-200 bg-white p-5">
        <h2 className="mb-4 text-base font-semibold text-slate-800">ملخص اليوم</h2>
        <div className="space-y-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="h-6 animate-pulse rounded bg-slate-100" />
          ))}
        </div>
      </div>
    )
  }

  if (!summary) return null

  const mortalityValues = summary.mortalities.entries.map((e) => e.quantity)
  const feedValues      = summary.feed.entries.map((e) => e.quantity)
  const medValues       = summary.medicines.entries.map((e) => e.quantity)
  const expenseValues   = summary.expenses.entries.map((e) => e.total_amount)

  // Feed unit label — use first entry's unit_label
  const feedUnit    = summary.feed.entries[0]?.unit_label ?? ''
  const medUnit     = summary.medicines.entries[0]?.unit_label ?? ''

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-5">
      <h2 className="mb-4 text-base font-semibold text-slate-800">ملخص اليوم</h2>

      <div className="divide-y divide-slate-100">
        {/* Mortalities */}
        <div className="flex items-center justify-between py-2.5 text-sm">
          <span className="text-slate-500">النفوق</span>
          <SumLine values={mortalityValues} />
        </div>

        {/* Feed */}
        <div className="flex items-center justify-between py-2.5 text-sm">
          <span className="text-slate-500">العلف</span>
          {summary.feed.entries.length > 0 ? (
            <div className="text-left space-y-0.5">
              {summary.feed.entries.map((e, i) => (
                <div key={i} className="text-xs text-slate-600">
                  {e.item_name}: <span className="font-medium">{formatNumber(e.quantity)} {e.unit_label}</span>
                </div>
              ))}
            </div>
          ) : (
            <span className="text-slate-400">—</span>
          )}
        </div>

        {/* Medicine */}
        <div className="flex items-center justify-between py-2.5 text-sm">
          <span className="text-slate-500">الدواء</span>
          {summary.medicines.entries.length > 0 ? (
            <div className="text-left space-y-0.5">
              {summary.medicines.entries.map((e, i) => (
                <div key={i} className="text-xs text-slate-600">
                  {e.item_name}: <span className="font-medium">{formatNumber(e.quantity)} {e.unit_label}</span>
                </div>
              ))}
            </div>
          ) : (
            <span className="text-slate-400">—</span>
          )}
        </div>

        {/* Expenses */}
        <div className="flex items-center justify-between py-2.5 text-sm">
          <span className="text-slate-500">المصاريف</span>
          {summary.expenses.entries.length > 0 ? (
            <div className="text-left space-y-0.5">
              {summary.expenses.entries.map((e, i) => (
                <div key={i} className="text-xs text-slate-600">
                  {EXPENSE_LABEL[e.type] ?? e.type}: <span className="font-medium">{formatNumber(e.total_amount)}</span>
                </div>
              ))}
              {summary.expenses.entries.length > 1 && (
                <div className="text-xs font-semibold text-slate-800">
                  الإجمالي: {formatNumber(summary.expenses.total)}
                </div>
              )}
            </div>
          ) : (
            <span className="text-slate-400">—</span>
          )}
        </div>
      </div>
    </div>
  )
}
```

**Step 4: Commit**

```bash
cd frontend
git add src/app/\(farm\)/dashboard/FlockInfoCard.tsx \
        src/app/\(farm\)/dashboard/QuickEntryCard.tsx \
        src/app/\(farm\)/dashboard/DaySummaryCard.tsx
git commit -m "feat: add dashboard child components (FlockInfoCard, QuickEntryCard, DaySummaryCard)"
```

---

## Task 10: Dashboard Page — Full Rebuild

**Files:**
- Modify: `frontend/src/app/(farm)/dashboard/page.tsx`

**Step 1: Read the current page.tsx** (already done — it's the file with stat cards and active flock panel)

**Step 2: Overwrite `dashboard/page.tsx` with the full rebuilt version**

```tsx
// frontend/src/app/(farm)/dashboard/page.tsx
'use client'

import { useEffect, useState, useCallback } from 'react'
import Link from 'next/link'
import { Bird, Plus } from 'lucide-react'
import { flocksApi } from '@/lib/api/flocks'
import { useFarmStore } from '@/stores/farm.store'
import { useCurrentRole } from '@/lib/roles'
import type { Flock } from '@/types/flock'
import type { TodaySummary } from '@/types/dashboard'
import { FlockInfoCard } from './FlockInfoCard'
import { QuickEntryCard } from './QuickEntryCard'
import { DaySummaryCard } from './DaySummaryCard'

export default function DashboardPage() {
  const { currentFarm } = useFarmStore()
  const role            = useCurrentRole()

  const [flocks, setFlocks]       = useState<Flock[]>([])
  const [summary, setSummary]     = useState<TodaySummary | null>(null)
  const [loadingFlocks, setLoadingFlocks] = useState(true)
  const [loadingSummary, setLoadingSummary] = useState(false)
  const [error, setError]         = useState<string | null>(null)

  const canCreate = role === 'farm_admin' || role === 'super_admin'

  // ── Fetch flocks ─────────────────────────────────────────────────────────────
  const fetchFlocks = useCallback(() => {
    if (!currentFarm) { setLoadingFlocks(false); return }
    setLoadingFlocks(true)
    flocksApi
      .list()
      .then((res) => setFlocks(res.data))
      .catch(() => setError('تعذّر تحميل بيانات الأفواج'))
      .finally(() => setLoadingFlocks(false))
  }, [currentFarm])

  useEffect(() => { fetchFlocks() }, [fetchFlocks])

  // ── Fetch today summary ───────────────────────────────────────────────────────
  const activeFlock = flocks.find((f) => f.status === 'active')

  const fetchSummary = useCallback(() => {
    if (!activeFlock) return
    setLoadingSummary(true)
    flocksApi
      .todaySummary(activeFlock.id)
      .then((res) => setSummary(res.data))
      .catch(() => {}) // Non-critical — summary can be absent
      .finally(() => setLoadingSummary(false))
  }, [activeFlock?.id])

  useEffect(() => { fetchSummary() }, [fetchSummary])

  // ── Loading skeleton ──────────────────────────────────────────────────────────
  if (loadingFlocks) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div className="h-8 w-40 animate-pulse rounded bg-slate-100" />
        </div>
        <div className="h-40 animate-pulse rounded-xl bg-slate-100" />
        <div className="h-64 animate-pulse rounded-xl bg-slate-100" />
      </div>
    )
  }

  // ── Error state ───────────────────────────────────────────────────────────────
  if (error) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-slate-900">لوحة التحكم</h1>
        <div className="rounded-xl border border-red-200 bg-red-50 p-5 text-sm text-red-700">{error}</div>
      </div>
    )
  }

  // ── No active flock ───────────────────────────────────────────────────────────
  if (!activeFlock) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-slate-900">لوحة التحكم</h1>
            {currentFarm && <p className="mt-0.5 text-sm text-slate-500">{currentFarm.name}</p>}
          </div>
          {canCreate && (
            <Link
              href="/flocks/new"
              className="flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-700"
            >
              <Plus className="h-4 w-4" />
              فوج جديد
            </Link>
          )}
        </div>
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white py-20 text-center">
          <Bird className="mb-4 h-12 w-12 text-slate-300" />
          <h3 className="text-lg font-semibold text-slate-700">لا يوجد فوج نشط حالياً</h3>
          <p className="mt-1 text-sm text-slate-500">
            {flocks.length > 0 ? 'جميع الأفواج مغلقة أو مسودة' : 'لم يتم إنشاء أي فوج بعد'}
          </p>
          {canCreate && (
            <Link
              href="/flocks/new"
              className="mt-5 flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-700"
            >
              <Plus className="h-4 w-4" />
              إنشاء فوج جديد
            </Link>
          )}
        </div>
      </div>
    )
  }

  // ── Active flock dashboard ────────────────────────────────────────────────────
  const isEntryAllowed = activeFlock.status === 'active'

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">لوحة التحكم</h1>
          {currentFarm && <p className="mt-0.5 text-sm text-slate-500">{currentFarm.name}</p>}
        </div>
        {canCreate && (
          <Link
            href="/flocks/new"
            className="flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-700"
          >
            <Plus className="h-4 w-4" />
            فوج جديد
          </Link>
        )}
      </div>

      {/* Flock Info Card */}
      <FlockInfoCard flock={activeFlock} />

      {/* Quick Entry + Day Summary (side by side on md+) */}
      <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
        {/* Quick Entry — only if flock is active */}
        {isEntryAllowed ? (
          <QuickEntryCard flock={activeFlock} onSuccess={fetchSummary} />
        ) : (
          <div className="flex items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-500">
            الإدخال غير متاح — الفوج مغلق
          </div>
        )}

        {/* Day Summary */}
        <DaySummaryCard summary={summary} loading={loadingSummary} />
      </div>

      {/* Operational placeholder */}
      <div className="rounded-xl border border-slate-200 bg-white p-5">
        <h2 className="mb-3 text-base font-semibold text-slate-800">المعلومات التشغيلية</h2>
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 text-sm">
          <div>
            <p className="text-xs text-slate-500">العمر</p>
            <p className="mt-0.5 font-semibold text-slate-800">
              {activeFlock.current_age_days !== null ? `${activeFlock.current_age_days} يوم` : '—'}
            </p>
          </div>
          <div>
            <p className="text-xs text-slate-500">التعتيم</p>
            <p className="mt-0.5 font-semibold text-slate-400">لا توجد بيانات</p>
          </div>
          <div>
            <p className="text-xs text-slate-500">درجة الحرارة</p>
            <p className="mt-0.5 font-semibold text-slate-400">لا توجد بيانات</p>
          </div>
        </div>
      </div>
    </div>
  )
}
```

**Step 3: Verify the page renders correctly**

Start the dev server and navigate to `/dashboard` as a farm_admin user. Check:
- [ ] Flock info card shows name, age, initial count, total mortality, remaining count
- [ ] Quick entry card shows 4 tabs
- [ ] Mortality tab: enter 5, submit → success toast → day summary updates to show 5
- [ ] Feed tab: shows "لا توجد أصناف علف" if no inventory items
- [ ] Expense tab: total auto-calculates when qty × price entered
- [ ] Day summary card shows entries with format
- [ ] Empty state (no active flock) shows correctly

**Step 4: Commit**

```bash
cd frontend
git add src/app/\(farm\)/dashboard/page.tsx
git commit -m "feat: rebuild dashboard page with real data, quick-entry, and day summary"
```

---

## Final Verification

Run the full backend test suite one last time:

```bash
cd backend && php artisan test
```
Expected: All tests green (existing 51 + new ~20 = ~71).

Check the frontend builds without TypeScript errors:

```bash
cd frontend && npx tsc --noEmit
```
Expected: No errors.

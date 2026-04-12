# Mortalities API + Frontend Tab — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Mortalities (النفوق) daily-operation module: a Laravel API for listing and creating flock mortality records, plus a live tab in the flock detail page that shows the list and an add-record form.

**Architecture:** Nested under flocks (`/api/flocks/{flock}/mortalities`), farm-scoped via the existing `farm.scope` + `farm.active` middleware stack. The controller re-uses `ShowFlockAction` to verify farm ownership. Frontend is a single `MortalitiesTab` client component imported into the existing flock detail page, replacing the placeholder.

**Tech Stack:** Laravel 11 + Sanctum + PHPUnit (backend) · Next.js 16 App Router + Axios + TypeScript (frontend)

---

## Codebase context (read before starting any task)

### Key existing files

| File | Role |
|------|------|
| `backend/app/Models/FlockMortality.php` | Eloquent model — table `flock_mortalities`, fillable already set |
| `backend/app/Models/Flock.php` | Has `mortalities()` HasMany relation |
| `backend/app/Actions/Flock/ShowFlockAction.php` | Verifies flock belongs to farm, throws Exception(404) if not |
| `backend/app/Http/Controllers/Api/Flock/FlockController.php` | Pattern to copy for the new controller |
| `backend/routes/api.php` | Farm-scoped middleware group to add new routes into |
| `backend/database/factories/FlockFactory.php` | States: `draft()`, `active()`, `closed()`, `cancelled()` |
| `backend/database/factories/FarmUserFactory.php` | Creates `farm_users` records (no `role` column in DB — roles via Spatie only) |
| `frontend/src/app/(farm)/flocks/[id]/page.tsx` | Detail page; has `TabPlaceholder` for mortalities tab — replace it |
| `frontend/src/types/flock.ts` | Existing types; add `Mortality` type to new file |
| `frontend/src/lib/api/flocks.ts` | Pattern to copy for `mortalitiesApi` |

### flock_mortalities table columns

```
id, farm_id, flock_id, entry_date (date), quantity (int > 0),
reason (varchar 190, nullable), notes (text, nullable),
worker_id (FK users, nullable), editable_until (timestampTz, nullable),
created_by (FK users, nullable), updated_by (FK users, nullable),
created_at, updated_at
```

### Business rules

- Only `active` flocks accept new mortality entries (422 if draft/closed/cancelled).
- `quantity` must be `> 0`.
- `entry_date` required, format `Y-m-d`.
- `reason` optional, max 190 chars.
- `notes` optional, text.
- On create: set `worker_id = created_by = updated_by = $userId`, `editable_until = now() + 15 min`.
- Farm ownership verified by reusing `ShowFlockAction`.

### Route to add

Inside the existing `Route::middleware(['auth:sanctum', 'farm.scope', 'farm.active'])` group in `backend/routes/api.php`:

```php
// V1-C: Mortalities
Route::prefix('flocks/{flock}/mortalities')->group(function (): void {
    Route::get('/',  [MortalityController::class, 'index']);
    Route::post('/', [MortalityController::class, 'store']);
});
```

---

## File map

**Backend — create:**
- `backend/app/Http/Resources/FlockMortalityResource.php`
- `backend/app/Http/Requests/Mortality/StoreMortalityRequest.php`
- `backend/app/Actions/Mortality/ListMortalitiesAction.php`
- `backend/app/Actions/Mortality/CreateMortalityAction.php`
- `backend/app/Http/Controllers/Api/Mortality/MortalityController.php`
- `backend/database/factories/FlockMortalityFactory.php`
- `backend/tests/Feature/Mortality/MortalityListTest.php`
- `backend/tests/Feature/Mortality/MortalityCreateTest.php`

**Backend — modify:**
- `backend/routes/api.php` — add two new routes

**Frontend — create:**
- `frontend/src/types/mortality.ts`
- `frontend/src/lib/api/mortalities.ts`
- `frontend/src/components/flocks/tabs/MortalitiesTab.tsx`

**Frontend — modify:**
- `frontend/src/app/(farm)/flocks/[id]/page.tsx` — import + use `MortalitiesTab`

---

## Task 1: FlockMortalityFactory + FlockMortalityResource

**Files:**
- Create: `backend/database/factories/FlockMortalityFactory.php`
- Create: `backend/app/Http/Resources/FlockMortalityResource.php`

- [ ] **Step 1: Create FlockMortalityFactory**

```php
<?php
// backend/database/factories/FlockMortalityFactory.php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FlockMortality>
 */
class FlockMortalityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id'        => Farm::factory(),
            'flock_id'       => Flock::factory(),
            'entry_date'     => now()->toDateString(),
            'quantity'       => $this->faker->numberBetween(1, 50),
            'reason'         => null,
            'notes'          => null,
            'worker_id'      => null,
            'editable_until' => now()->addMinutes(15),
            'created_by'     => null,
            'updated_by'     => null,
        ];
    }

    public function forFlock(Flock $flock): static
    {
        return $this->state([
            'farm_id'  => $flock->farm_id,
            'flock_id' => $flock->id,
        ]);
    }

    public function withReason(string $reason): static
    {
        return $this->state(['reason' => $reason]);
    }
}
```

- [ ] **Step 2: Create FlockMortalityResource**

```php
<?php
// backend/app/Http/Resources/FlockMortalityResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlockMortalityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'flock_id'   => $this->flock_id,
            'entry_date' => $this->entry_date?->toDateString(),
            'quantity'   => $this->quantity,
            'reason'     => $this->reason,
            'notes'      => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
```

- [ ] **Step 3: Verify files are syntactically valid**

```bash
cd backend
php -l app/Http/Resources/FlockMortalityResource.php
php -l database/factories/FlockMortalityFactory.php
```

Expected: `No syntax errors detected` for both files.

- [ ] **Step 4: Commit**

```bash
cd backend
git add app/Http/Resources/FlockMortalityResource.php
git add database/factories/FlockMortalityFactory.php
git commit -m "feat(mortalities): add FlockMortalityResource and FlockMortalityFactory"
```

---

## Task 2: List Mortalities — Action + Controller + Route + Tests

**Files:**
- Create: `backend/app/Actions/Mortality/ListMortalitiesAction.php`
- Create: `backend/app/Http/Controllers/Api/Mortality/MortalityController.php` (index only — store added in Task 3)
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/Mortality/MortalityListTest.php`

- [ ] **Step 1: Create ListMortalitiesAction**

```php
<?php
// backend/app/Actions/Mortality/ListMortalitiesAction.php

namespace App\Actions\Mortality;

use App\Models\FlockMortality;
use Illuminate\Database\Eloquent\Collection;

class ListMortalitiesAction
{
    /**
     * Return all mortality records for a given flock, newest first.
     *
     * @return Collection<int, FlockMortality>
     */
    public function execute(int $flockId): Collection
    {
        return FlockMortality::where('flock_id', $flockId)
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }
}
```

- [ ] **Step 2: Create MortalityController (index only)**

```php
<?php
// backend/app/Http/Controllers/Api/Mortality/MortalityController.php

namespace App\Http\Controllers\Api\Mortality;

use App\Actions\Flock\ShowFlockAction;
use App\Actions\Mortality\ListMortalitiesAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\FlockMortalityResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MortalityController extends Controller
{
    public function __construct(
        private readonly ShowFlockAction       $showFlockAction,
        private readonly ListMortalitiesAction $listAction,
    ) {}

    // ── GET /api/flocks/{flock}/mortalities ───────────────────────────────────

    public function index(Request $request, int $flockId): ResourceCollection|JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        try {
            $this->showFlockAction->execute($farmId, $flockId);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $mortalities = $this->listAction->execute($flockId);

        return FlockMortalityResource::collection($mortalities);
    }

    // store() added in Task 3
}
```

- [ ] **Step 3: Add routes to api.php**

Open `backend/routes/api.php`. Add inside the `Route::middleware(['auth:sanctum', 'farm.scope', 'farm.active'])` group, after the flocks group:

```php
use App\Http\Controllers\Api\Mortality\MortalityController;

// ── V1-C: Mortalities ────────────────────────────────────────────────────────
Route::prefix('flocks/{flock}/mortalities')->group(function (): void {
    Route::get('/',  [MortalityController::class, 'index']);
    Route::post('/', [MortalityController::class, 'store']);
});
```

The `use` statement goes at the top of the file with the other `use` statements.

Full updated `backend/routes/api.php`:

```php
<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Flock\FlockController;
use App\Http\Controllers\Api\Mortality\MortalityController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login',            [AuthController::class, 'login']);
    Route::post('register-request', [AuthController::class, 'registerRequest']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout',   [AuthController::class, 'logout']);
        Route::get('me',        [AuthController::class, 'me']);
        Route::put('me',        [AuthController::class, 'updateProfile']);
        Route::put('password',  [AuthController::class, 'changePassword']);
    });
});

Route::middleware(['auth:sanctum', 'farm.scope', 'farm.active'])->group(function (): void {

    // ── V1-B: Flocks ─────────────────────────────────────────────────────────
    Route::prefix('flocks')->group(function (): void {
        Route::get('/',         [FlockController::class, 'index']);
        Route::post('/',        [FlockController::class, 'store']);
        Route::get('/{flock}',  [FlockController::class, 'show']);
        Route::put('/{flock}',  [FlockController::class, 'update']);
    });

    // ── V1-C: Mortalities ────────────────────────────────────────────────────
    Route::prefix('flocks/{flock}/mortalities')->group(function (): void {
        Route::get('/',  [MortalityController::class, 'index']);
        Route::post('/', [MortalityController::class, 'store']);
    });
});
```

- [ ] **Step 4: Write MortalityListTest**

```php
<?php
// backend/tests/Feature/Mortality/MortalityListTest.php

namespace Tests\Feature\Mortality;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\FlockMortality;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MortalityListTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsMember(Farm $farm): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->create([
            'farm_id' => $farm->id,
            'user_id' => $user->id,
            'status'  => 'active',
        ]);
        return $user;
    }

    private function withFarm(Farm $farm): array
    {
        return ['X-Farm-Id' => $farm->id];
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_list_returns_mortalities_for_flock(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        FlockMortality::factory()->forFlock($flock)->create(['quantity' => 5]);
        FlockMortality::factory()->forFlock($flock)->create(['quantity' => 10]);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->getJson("/api/flocks/{$flock->id}/mortalities")
            ->assertStatus(200);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_list_returns_empty_array_when_no_mortalities(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->getJson("/api/flocks/{$flock->id}/mortalities")
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_list_sorted_by_entry_date_descending(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $old = FlockMortality::factory()->forFlock($flock)->create([
            'entry_date' => now()->subDays(5)->toDateString(),
        ]);
        $new = FlockMortality::factory()->forFlock($flock)->create([
            'entry_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->getJson("/api/flocks/{$flock->id}/mortalities")
            ->assertStatus(200);

        $this->assertEquals($new->id, $response->json('data.0.id'));
        $this->assertEquals($old->id, $response->json('data.1.id'));
    }

    public function test_list_does_not_return_other_flock_mortalities(): void
    {
        $farm   = Farm::factory()->create();
        $user   = $this->actingAsMember($farm);
        $flock1 = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $flock2 = Flock::factory()->closed()->create(['farm_id' => $farm->id]);

        FlockMortality::factory()->forFlock($flock1)->create();
        FlockMortality::factory()->forFlock($flock2)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->getJson("/api/flocks/{$flock1->id}/mortalities")
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_list_returns_404_for_wrong_farm(): void
    {
        $farm1 = Farm::factory()->create();
        $farm2 = Farm::factory()->create();
        $user  = $this->actingAsMember($farm1);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm2->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm1))
            ->getJson("/api/flocks/{$flock->id}/mortalities")
            ->assertStatus(404);
    }

    public function test_list_returns_expected_fields(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        FlockMortality::factory()->forFlock($flock)->withReason('مرض')->create([
            'quantity' => 7,
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->getJson("/api/flocks/{$flock->id}/mortalities")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'flock_id', 'entry_date', 'quantity', 'reason', 'notes', 'created_at', 'updated_at'],
                ],
            ])
            ->assertJsonPath('data.0.quantity', 7)
            ->assertJsonPath('data.0.reason', 'مرض');
    }

    public function test_list_requires_authentication(): void
    {
        $farm  = Farm::factory()->create();
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->withHeaders($this->withFarm($farm))
            ->getJson("/api/flocks/{$flock->id}/mortalities")
            ->assertStatus(401);
    }
}
```

- [ ] **Step 5: Run tests to verify they fail before implementation**

```bash
cd backend
php artisan test tests/Feature/Mortality/MortalityListTest.php --no-coverage 2>&1 | tail -20
```

Expected: Tests fail (likely `ReflectionException` for missing `store` method or routing errors — that's fine, the route exists and index is implemented, tests should mostly pass already OR fail because `store` method is missing from controller. If `store` is missing the route binding will fail; add a stub to the controller).

If controller constructor injection fails due to missing `store()`, add this stub to `MortalityController.php` temporarily (will be replaced in Task 3):

```php
public function store(Request $request, int $flockId): JsonResponse
{
    return response()->json(['message' => 'not implemented'], 501);
}
```

- [ ] **Step 6: Run tests — expect all 6 to pass**

```bash
cd backend
php artisan test tests/Feature/Mortality/MortalityListTest.php --no-coverage
```

Expected output:
```
PASS  Tests\Feature\Mortality\MortalityListTest
✓ list returns mortalities for flock
✓ list returns empty array when no mortalities
✓ list sorted by entry date descending
✓ list does not return other flock mortalities
✓ list returns 404 for wrong farm
✓ list returns expected fields
✓ list requires authentication

Tests:  7 passed
```

- [ ] **Step 7: Run full test suite to check no regressions**

```bash
cd backend
php artisan test --no-coverage 2>&1 | tail -5
```

Expected: All previous tests + 7 new ones pass.

- [ ] **Step 8: Commit**

```bash
cd backend
git add app/Actions/Mortality/ListMortalitiesAction.php
git add app/Http/Controllers/Api/Mortality/MortalityController.php
git add routes/api.php
git add tests/Feature/Mortality/MortalityListTest.php
git commit -m "feat(mortalities): list endpoint with 7 passing tests"
```

---

## Task 3: Create Mortality — Request + Action + Controller store() + Tests

**Files:**
- Create: `backend/app/Http/Requests/Mortality/StoreMortalityRequest.php`
- Create: `backend/app/Actions/Mortality/CreateMortalityAction.php`
- Modify: `backend/app/Http/Controllers/Api/Mortality/MortalityController.php` (replace stub store)
- Create: `backend/tests/Feature/Mortality/MortalityCreateTest.php`

- [ ] **Step 1: Write MortalityCreateTest (failing first)**

```php
<?php
// backend/tests/Feature/Mortality/MortalityCreateTest.php

namespace Tests\Feature\Mortality;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MortalityCreateTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsMember(Farm $farm): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->create([
            'farm_id' => $farm->id,
            'user_id' => $user->id,
            'status'  => 'active',
        ]);
        return $user;
    }

    private function withFarm(Farm $farm): array
    {
        return ['X-Farm-Id' => $farm->id];
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'entry_date' => now()->toDateString(),
            'quantity'   => 10,
        ], $overrides);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_create_mortality_successfully(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson("/api/flocks/{$flock->id}/mortalities", $this->validPayload())
            ->assertStatus(201)
            ->assertJsonPath('message', 'تم تسجيل النفوق بنجاح')
            ->assertJsonPath('data.quantity', 10)
            ->assertJsonPath('data.flock_id', $flock->id);

        $this->assertDatabaseHas('flock_mortalities', [
            'flock_id'   => $flock->id,
            'quantity'   => 10,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'worker_id'  => $user->id,
        ]);
    }

    public function test_create_with_reason_and_notes(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson("/api/flocks/{$flock->id}/mortalities", $this->validPayload([
                'reason' => 'مرض تنفسي',
                'notes'  => 'تم عزل المريض',
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.reason', 'مرض تنفسي')
            ->assertJsonPath('data.notes', 'تم عزل المريض');
    }

    public function test_create_sets_editable_until_15_minutes_from_now(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $before = now()->addMinutes(14);
        $after  = now()->addMinutes(16);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson("/api/flocks/{$flock->id}/mortalities", $this->validPayload())
            ->assertStatus(201);

        $record = \App\Models\FlockMortality::first();
        $this->assertNotNull($record->editable_until);
        $this->assertTrue($record->editable_until->between($before, $after));
    }

    public function test_cannot_create_on_closed_flock(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->closed()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson("/api/flocks/{$flock->id}/mortalities", $this->validPayload())
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن تسجيل نفوق على فوج مغلق أو ملغى');
    }

    public function test_cannot_create_on_cancelled_flock(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->cancelled()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson("/api/flocks/{$flock->id}/mortalities", $this->validPayload())
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن تسجيل نفوق على فوج مغلق أو ملغى');
    }

    public function test_cannot_create_on_draft_flock(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->draft()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson("/api/flocks/{$flock->id}/mortalities", $this->validPayload())
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن تسجيل نفوق على فوج غير نشط');
    }

    public function test_create_with_zero_quantity_returns_422(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson("/api/flocks/{$flock->id}/mortalities", $this->validPayload(['quantity' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_create_without_entry_date_returns_422(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson("/api/flocks/{$flock->id}/mortalities", ['quantity' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['entry_date']);
    }

    public function test_create_returns_404_for_wrong_farm(): void
    {
        $farm1 = Farm::factory()->create();
        $farm2 = Farm::factory()->create();
        $user  = $this->actingAsMember($farm1);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm2->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm1))
            ->postJson("/api/flocks/{$flock->id}/mortalities", $this->validPayload())
            ->assertStatus(404);
    }

    public function test_create_requires_authentication(): void
    {
        $farm  = Farm::factory()->create();
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->withHeaders($this->withFarm($farm))
            ->postJson("/api/flocks/{$flock->id}/mortalities", $this->validPayload())
            ->assertStatus(401);
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail (expected)**

```bash
cd backend
php artisan test tests/Feature/Mortality/MortalityCreateTest.php --no-coverage 2>&1 | tail -20
```

Expected: Tests fail (store returns 501 or validation errors not implemented).

- [ ] **Step 3: Create StoreMortalityRequest**

```php
<?php
// backend/app/Http/Requests/Mortality/StoreMortalityRequest.php

namespace App\Http\Requests\Mortality;

use Illuminate\Foundation\Http\FormRequest;

class StoreMortalityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date_format:Y-m-d'],
            'quantity'   => ['required', 'integer', 'min:1'],
            'reason'     => ['nullable', 'string', 'max:190'],
            'notes'      => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'entry_date.required'    => 'تاريخ الإدخال مطلوب',
            'entry_date.date_format' => 'صيغة التاريخ يجب أن تكون YYYY-MM-DD',
            'quantity.required'      => 'الكمية مطلوبة',
            'quantity.integer'       => 'الكمية يجب أن تكون عدداً صحيحاً',
            'quantity.min'           => 'الكمية يجب أن تكون أكبر من صفر',
        ];
    }
}
```

- [ ] **Step 4: Create CreateMortalityAction**

```php
<?php
// backend/app/Actions/Mortality/CreateMortalityAction.php

namespace App\Actions\Mortality;

use App\Models\Flock;
use App\Models\FlockMortality;

class CreateMortalityAction
{
    /**
     * @param  array<string, mixed>  $data  (validated: entry_date, quantity, reason?, notes?)
     *
     * @throws \Exception 422 if flock is not active
     */
    public function execute(Flock $flock, int $userId, array $data): FlockMortality
    {
        if (in_array($flock->status, ['closed', 'cancelled'])) {
            throw new \Exception('لا يمكن تسجيل نفوق على فوج مغلق أو ملغى', 422);
        }

        if ($flock->status !== 'active') {
            throw new \Exception('لا يمكن تسجيل نفوق على فوج غير نشط', 422);
        }

        return FlockMortality::create([
            'farm_id'        => $flock->farm_id,
            'flock_id'       => $flock->id,
            'entry_date'     => $data['entry_date'],
            'quantity'       => $data['quantity'],
            'reason'         => $data['reason'] ?? null,
            'notes'          => $data['notes'] ?? null,
            'worker_id'      => $userId,
            'editable_until' => now()->addMinutes(15),
            'created_by'     => $userId,
            'updated_by'     => $userId,
        ]);
    }
}
```

- [ ] **Step 5: Replace store() stub in MortalityController**

Update `backend/app/Http/Controllers/Api/Mortality/MortalityController.php` to the full version:

```php
<?php
// backend/app/Http/Controllers/Api/Mortality/MortalityController.php

namespace App\Http\Controllers\Api\Mortality;

use App\Actions\Flock\ShowFlockAction;
use App\Actions\Mortality\CreateMortalityAction;
use App\Actions\Mortality\ListMortalitiesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mortality\StoreMortalityRequest;
use App\Http\Resources\FlockMortalityResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MortalityController extends Controller
{
    public function __construct(
        private readonly ShowFlockAction       $showFlockAction,
        private readonly ListMortalitiesAction $listAction,
        private readonly CreateMortalityAction $createAction,
    ) {}

    // ── GET /api/flocks/{flock}/mortalities ───────────────────────────────────

    public function index(Request $request, int $flockId): ResourceCollection|JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        try {
            $this->showFlockAction->execute($farmId, $flockId);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $mortalities = $this->listAction->execute($flockId);

        return FlockMortalityResource::collection($mortalities);
    }

    // ── POST /api/flocks/{flock}/mortalities ──────────────────────────────────

    public function store(StoreMortalityRequest $request, int $flockId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $userId = $request->user()->id;

        try {
            $flock    = $this->showFlockAction->execute($farmId, $flockId);
            $mortality = $this->createAction->execute($flock, $userId, $request->validated());
        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            return response()->json(
                ['message' => $e->getMessage()],
                $code >= 400 && $code < 600 ? $code : 422
            );
        }

        return response()->json([
            'message' => 'تم تسجيل النفوق بنجاح',
            'data'    => new FlockMortalityResource($mortality),
        ], 201);
    }
}
```

- [ ] **Step 6: Run create tests — expect all 9 to pass**

```bash
cd backend
php artisan test tests/Feature/Mortality/MortalityCreateTest.php --no-coverage
```

Expected:
```
PASS  Tests\Feature\Mortality\MortalityCreateTest
✓ create mortality successfully
✓ create with reason and notes
✓ create sets editable until 15 minutes from now
✓ cannot create on closed flock
✓ cannot create on cancelled flock
✓ cannot create on draft flock
✓ create with zero quantity returns 422
✓ create without entry date returns 422
✓ create returns 404 for wrong farm
✓ create requires authentication

Tests:  10 passed
```

- [ ] **Step 7: Run full test suite**

```bash
cd backend
php artisan test --no-coverage 2>&1 | tail -5
```

Expected: All 97+ tests pass (81 old + 7 list + ~10 create).

- [ ] **Step 8: Commit**

```bash
cd backend
git add app/Http/Requests/Mortality/StoreMortalityRequest.php
git add app/Actions/Mortality/CreateMortalityAction.php
git add app/Http/Controllers/Api/Mortality/MortalityController.php
git add tests/Feature/Mortality/MortalityCreateTest.php
git commit -m "feat(mortalities): create endpoint with 10 passing tests"
```

---

## Task 4: Frontend — Types + API Client + MortalitiesTab + Wire

**Files:**
- Create: `frontend/src/types/mortality.ts`
- Create: `frontend/src/lib/api/mortalities.ts`
- Create: `frontend/src/components/flocks/tabs/MortalitiesTab.tsx`
- Modify: `frontend/src/app/(farm)/flocks/[id]/page.tsx`

### Context for the implementer

The flock detail page is at `frontend/src/app/(farm)/flocks/[id]/page.tsx`.
It currently has `TABS` array and uses `<TabPlaceholder tab={activeTab} />` for all tab content.
The goal is: when `activeTab === 'mortalities'`, render `<MortalitiesTab flockId={flockId} flockStatus={flock.status} />` instead of the placeholder.

`FlockStatus` is `'active' | 'draft' | 'closed' | 'cancelled'` (from `@/types/flock`).

The `MortalitiesTab` component must be `'use client'` — it fetches data and handles a form.

The API endpoint is:
- `GET /api/flocks/{id}/mortalities` → `{ data: Mortality[] }`
- `POST /api/flocks/{id}/mortalities` → body: `{ entry_date, quantity, reason?, notes? }` → `{ message, data: Mortality }`

The tab shows:
1. If flock is `active`: an inline form at the top (entry_date, quantity, reason, notes) + submit button
2. A list of mortality records (entry_date, quantity, reason) sorted newest first
3. Loading spinner while fetching
4. Error banner if fetch fails
5. Empty state if no records

- [ ] **Step 1: Create `frontend/src/types/mortality.ts`**

```typescript
// frontend/src/types/mortality.ts

export interface Mortality {
  id: number
  flock_id: number
  entry_date: string      // "YYYY-MM-DD"
  quantity: number
  reason: string | null
  notes: string | null
  created_at: string
  updated_at: string
}

export interface CreateMortalityPayload {
  entry_date: string
  quantity: number
  reason?: string
  notes?: string
}
```

- [ ] **Step 2: Create `frontend/src/lib/api/mortalities.ts`**

```typescript
// frontend/src/lib/api/mortalities.ts

import { apiClient } from './client'
import type { CreateMortalityPayload, Mortality } from '@/types/mortality'

export const mortalitiesApi = {
  list: (flockId: number) =>
    apiClient
      .get<{ data: Mortality[] }>(`/flocks/${flockId}/mortalities`)
      .then((r) => r.data),

  create: (flockId: number, payload: CreateMortalityPayload) =>
    apiClient
      .post<{ data: Mortality; message: string }>(`/flocks/${flockId}/mortalities`, payload)
      .then((r) => r.data),
}
```

- [ ] **Step 3: Create `frontend/src/components/flocks/tabs/MortalitiesTab.tsx`**

```tsx
// frontend/src/components/flocks/tabs/MortalitiesTab.tsx
'use client'

import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { AlertCircle, Plus } from 'lucide-react'
import { mortalitiesApi } from '@/lib/api/mortalities'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { formatDate } from '@/lib/utils'
import type { FlockStatus } from '@/types/flock'
import type { Mortality } from '@/types/mortality'

// ── Schema ────────────────────────────────────────────────────────────────────
const schema = z.object({
  entry_date: z
    .string()
    .min(1, 'تاريخ الإدخال مطلوب')
    .regex(/^\d{4}-\d{2}-\d{2}$/, 'صيغة التاريخ غير صحيحة'),
  quantity: z
    .number({ invalid_type_error: 'يجب إدخال رقم صحيح' })
    .int()
    .positive('الكمية يجب أن تكون أكبر من صفر'),
  reason: z.string().max(190).optional().or(z.literal('')),
  notes: z.string().optional().or(z.literal('')),
})
type FormData = z.infer<typeof schema>

// ── Props ─────────────────────────────────────────────────────────────────────
interface MortalitiesTabProps {
  flockId: number
  flockStatus: FlockStatus
}

// ── Component ─────────────────────────────────────────────────────────────────
export function MortalitiesTab({ flockId, flockStatus }: MortalitiesTabProps) {
  const [mortalities, setMortalities] = useState<Mortality[]>([])
  const [loading, setLoading]         = useState(true)
  const [fetchError, setFetchError]   = useState<string | null>(null)
  const [serverError, setServerError] = useState<string | null>(null)
  const [showForm, setShowForm]       = useState(false)

  const canAdd = flockStatus === 'active'

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: { entry_date: new Date().toISOString().split('T')[0] },
  })

  // ── Fetch ─────────────────────────────────────────────────────────────────
  const fetchMortalities = () => {
    setLoading(true)
    mortalitiesApi
      .list(flockId)
      .then((res) => setMortalities(res.data))
      .catch(() => setFetchError('تعذّر تحميل سجلات النفوق'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    fetchMortalities()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [flockId])

  // ── Submit ────────────────────────────────────────────────────────────────
  const onSubmit = async (data: FormData) => {
    setServerError(null)
    try {
      await mortalitiesApi.create(flockId, {
        entry_date: data.entry_date,
        quantity:   data.quantity,
        reason:     data.reason || undefined,
        notes:      data.notes  || undefined,
      })
      reset({ entry_date: new Date().toISOString().split('T')[0] })
      setShowForm(false)
      fetchMortalities()
    } catch (err: unknown) {
      const axiosErr = err as {
        response?: { data?: { message?: string; errors?: Record<string, string[]> } }
      }
      const first = axiosErr?.response?.data?.errors
        ? Object.values(axiosErr.response.data.errors)[0]?.[0]
        : null
      setServerError(first ?? axiosErr?.response?.data?.message ?? 'حدث خطأ غير متوقع')
    }
  }

  // ── Render ────────────────────────────────────────────────────────────────
  if (loading) {
    return (
      <div className="flex items-center justify-center py-20 text-slate-400">
        <span className="text-sm">جارٍ التحميل...</span>
      </div>
    )
  }

  if (fetchError) {
    return (
      <div className="m-4 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">
        <AlertCircle className="h-5 w-5 shrink-0" />
        <p className="text-sm">{fetchError}</p>
      </div>
    )
  }

  return (
    <div className="p-4 space-y-4">
      {/* Add button */}
      {canAdd && !showForm && (
        <div className="flex justify-end">
          <Button size="sm" onClick={() => setShowForm(true)}>
            <Plus className="me-1.5 h-4 w-4" />
            تسجيل نفوق
          </Button>
        </div>
      )}

      {/* Inline form */}
      {canAdd && showForm && (
        <form
          onSubmit={handleSubmit(onSubmit)}
          className="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3"
          noValidate
        >
          <h3 className="text-sm font-semibold text-slate-700">تسجيل نفوق جديد</h3>

          <div className="grid grid-cols-2 gap-3">
            <Input
              {...register('entry_date')}
              id="entry_date"
              label="التاريخ"
              type="date"
              error={errors.entry_date?.message}
              required
            />
            <Input
              {...register('quantity', { valueAsNumber: true })}
              id="quantity"
              label="العدد"
              type="number"
              min={1}
              placeholder="مثال: 5"
              error={errors.quantity?.message}
              required
            />
          </div>

          <Input
            {...register('reason')}
            id="reason"
            label="السبب"
            placeholder="مثال: مرض تنفسي"
            error={errors.reason?.message}
          />

          <div className="flex flex-col gap-1">
            <label htmlFor="notes" className="text-sm font-medium text-slate-700">
              ملاحظات <span className="text-xs text-slate-400">(اختياري)</span>
            </label>
            <textarea
              {...register('notes')}
              id="notes"
              rows={2}
              className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
            />
          </div>

          {serverError && (
            <p className="text-sm text-red-600">{serverError}</p>
          )}

          <div className="flex gap-2 justify-end">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => { setShowForm(false); setServerError(null) }}
            >
              إلغاء
            </Button>
            <Button type="submit" size="sm" loading={isSubmitting}>
              حفظ
            </Button>
          </div>
        </form>
      )}

      {/* List */}
      {mortalities.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-16 text-center text-slate-400">
          <p className="text-base font-medium text-slate-600">لا توجد سجلات نفوق</p>
          {canAdd && (
            <p className="mt-1 text-sm">اضغط «تسجيل نفوق» لإضافة أول سجل</p>
          )}
        </div>
      ) : (
        <div className="overflow-x-auto rounded-xl border border-slate-200">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-4 py-3 text-start font-medium text-slate-600">التاريخ</th>
                <th className="px-4 py-3 text-start font-medium text-slate-600">العدد</th>
                <th className="px-4 py-3 text-start font-medium text-slate-600">السبب</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 bg-white">
              {mortalities.map((m) => (
                <tr key={m.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3 text-slate-700">{formatDate(m.entry_date)}</td>
                  <td className="px-4 py-3 font-medium text-red-700">{m.quantity}</td>
                  <td className="px-4 py-3 text-slate-500">{m.reason ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
```

- [ ] **Step 4: Update `frontend/src/app/(farm)/flocks/[id]/page.tsx`**

Add these imports at the top (after existing imports):

```typescript
import { MortalitiesTab } from '@/components/flocks/tabs/MortalitiesTab'
```

Replace the tab content section. Find this block in the current page:

```tsx
        {/* Tab content */}
        <div className="mt-0 rounded-b-xl rounded-tr-xl border border-t-0 border-slate-200 bg-white">
          <TabPlaceholder tab={activeTab} />
        </div>
```

Replace it with:

```tsx
        {/* Tab content */}
        <div className="mt-0 rounded-b-xl rounded-tr-xl border border-t-0 border-slate-200 bg-white">
          {activeTab === 'mortalities' ? (
            <MortalitiesTab flockId={flockId} flockStatus={flock.status} />
          ) : (
            <TabPlaceholder tab={activeTab} />
          )}
        </div>
```

- [ ] **Step 5: Verify TypeScript**

```bash
cd frontend
npx tsc --noEmit 2>&1
echo "EXIT:$?"
```

Expected: `EXIT:0` with no errors.

- [ ] **Step 6: Commit frontend**

```bash
cd frontend
git add src/types/mortality.ts
git add src/lib/api/mortalities.ts
git add src/components/flocks/tabs/MortalitiesTab.tsx
git add src/app/'(farm)'/flocks/'[id]'/page.tsx
git commit -m "feat(mortalities): MortalitiesTab with list + create form"
```

---

## Self-Review

**1. Spec coverage:**

| Requirement | Covered by |
|-------------|-----------|
| List mortalities for flock | Task 2 (ListMortalitiesAction + MortalityListTest) |
| Create mortality (active flocks only) | Task 3 (CreateMortalityAction + MortalityCreateTest) |
| quantity > 0 validation | Task 3 (StoreMortalityRequest + test) |
| entry_date required | Task 3 (StoreMortalityRequest + test) |
| reason optional | Task 3 (test_create_with_reason_and_notes) |
| editable_until = now + 15 min | Task 3 (CreateMortalityAction + test) |
| created_by / updated_by / worker_id = userId | Task 3 (assertDatabaseHas) |
| 404 for wrong farm | Tasks 2 + 3 (tests) |
| 401 without auth | Tasks 2 + 3 (tests) |
| Frontend: list display | Task 4 (MortalitiesTab table) |
| Frontend: create form for active flocks | Task 4 (MortalitiesTab form) |
| Frontend: loading state | Task 4 (loading check) |
| Frontend: error state | Task 4 (fetchError + serverError) |
| Frontend: empty state | Task 4 (mortalities.length === 0) |
| TypeScript clean | Task 4 (tsc --noEmit) |

**2. Placeholder scan:** No TBD, TODO, or vague steps — all steps have complete code.

**3. Type consistency:**
- `Mortality` defined in `src/types/mortality.ts` — used in `mortalitiesApi`, `MortalitiesTab`
- `CreateMortalityPayload` used in `mortalitiesApi.create()` and `MortalitiesTab` `onSubmit`
- `FlockStatus` imported from `@/types/flock` in `MortalitiesTab` — matches existing definition
- `FlockMortalityResource` fields (`id, flock_id, entry_date, quantity, reason, notes, created_at, updated_at`) match `Mortality` interface exactly
- Backend `forFlock()` factory method consistent across both test files
- `withReason()` factory method defined in Task 1 and used in Task 2 tests ✓

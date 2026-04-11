<?php

namespace Tests\Feature\Middleware;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CheckFarmActiveMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // مسار اختباري يمر عبر كلا الـ middlewares معاً (كما هو في الـ production)
        Route::middleware(['auth:sanctum', 'farm.scope', 'farm.active'])
            ->get('/_test/farm-active', fn () => response()->json(['ok' => true]));
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    private function memberRequest(User $user, Farm $farm): \Illuminate\Testing\TestResponse
    {
        FarmUser::firstOrCreate(
            ['farm_id' => $farm->id, 'user_id' => $user->id],
            ['status' => 'active', 'is_primary' => true],
        );

        return $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => (string) $farm->id])
            ->getJson('/_test/farm-active');
    }

    // ── active ────────────────────────────────────────────────────────────────

    public function test_active_farm_passes_through(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['status' => 'active']);

        $this->memberRequest($user, $farm)
            ->assertStatus(200)
            ->assertJsonPath('ok', true);
    }

    // ── pending_setup ─────────────────────────────────────────────────────────

    public function test_pending_setup_farm_returns_403(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->pendingSetup()->create();

        $this->memberRequest($user, $farm)
            ->assertStatus(403)
            ->assertJsonPath('message', 'هذه المزرعة قيد الإعداد ولم تُفعَّل بعد');
    }

    // ── suspended ─────────────────────────────────────────────────────────────

    public function test_suspended_farm_returns_403(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->suspended()->create();

        $this->memberRequest($user, $farm)
            ->assertStatus(403)
            ->assertJsonPath('message', 'هذه المزرعة موقوفة، يرجى التواصل مع المسؤول');
    }

    // ── farm model is stored in request attributes ────────────────────────────

    public function test_farm_model_stored_in_request_attributes(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['status' => 'active', 'name' => 'مزرعة الاختبار']);

        // نضيف مسار مؤقت يُرجع اسم المزرعة من attributes
        Route::middleware(['auth:sanctum', 'farm.scope', 'farm.active'])
            ->get('/_test/farm-from-attr', function (\Illuminate\Http\Request $request) {
                return response()->json(['farm_name' => $request->attributes->get('farm')->name]);
            });

        FarmUser::create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => (string) $farm->id])
            ->getJson('/_test/farm-from-attr')
            ->assertStatus(200)
            ->assertJsonPath('farm_name', 'مزرعة الاختبار');
    }
}

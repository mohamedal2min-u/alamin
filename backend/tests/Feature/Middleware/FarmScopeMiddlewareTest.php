<?php

namespace Tests\Feature\Middleware;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FarmScopeMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // مسار اختباري يمر عبر farm.scope فقط (لعزل اختبارات هذا الـ middleware)
        Route::middleware(['auth:sanctum', 'farm.scope'])
            ->get('/_test/farm-scope', fn () => response()->json(['ok' => true]));
    }

    // ── X-Farm-Id غائب ────────────────────────────────────────────────────────

    public function test_missing_farm_id_header_returns_400(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/_test/farm-scope')
            ->assertStatus(400)
            ->assertJsonPath('message', 'يجب تحديد المزرعة عبر header: X-Farm-Id');
    }

    // ── X-Farm-Id غير رقمي ────────────────────────────────────────────────────

    public function test_non_numeric_farm_id_returns_400(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => 'abc'])
            ->getJson('/_test/farm-scope')
            ->assertStatus(400);
    }

    public function test_zero_farm_id_returns_400(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => '0'])
            ->getJson('/_test/farm-scope')
            ->assertStatus(400);
    }

    // ── مستخدم عادي — غير عضو ────────────────────────────────────────────────

    public function test_non_member_user_returns_403(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => (string) $farm->id])
            ->getJson('/_test/farm-scope')
            ->assertStatus(403)
            ->assertJsonPath('message', 'ليس لديك صلاحية الوصول لهذه المزرعة');
    }

    public function test_inactive_member_returns_403(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create();

        FarmUser::create([
            'farm_id' => $farm->id,
            'user_id' => $user->id,
            'status'  => 'inactive',
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => (string) $farm->id])
            ->getJson('/_test/farm-scope')
            ->assertStatus(403);
    }

    // ── مستخدم عادي — عضو نشط ────────────────────────────────────────────────

    public function test_active_member_passes_through(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create();

        FarmUser::create([
            'farm_id' => $farm->id,
            'user_id' => $user->id,
            'status'  => 'active',
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => (string) $farm->id])
            ->getJson('/_test/farm-scope')
            ->assertStatus(200)
            ->assertJsonPath('ok', true);
    }

    // ── super_admin ───────────────────────────────────────────────────────────

    public function test_super_admin_can_access_any_existing_farm(): void
    {
        $admin = User::factory()->create();
        $farm  = Farm::factory()->create();

        // super_admin role بدون team context (farm_id = null)
        setPermissionsTeamId(null);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        $this->actingAs($admin, 'sanctum')
            ->withHeaders(['X-Farm-Id' => (string) $farm->id])
            ->getJson('/_test/farm-scope')
            ->assertStatus(200);
    }

    public function test_super_admin_with_nonexistent_farm_returns_404(): void
    {
        $admin = User::factory()->create();

        setPermissionsTeamId(null);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        $this->actingAs($admin, 'sanctum')
            ->withHeaders(['X-Farm-Id' => '99999'])
            ->getJson('/_test/farm-scope')
            ->assertStatus(404)
            ->assertJsonPath('message', 'المزرعة غير موجودة');
    }

    // ── بدون مصادقة ──────────────────────────────────────────────────────────

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/_test/farm-scope')
            ->assertStatus(401);
    }
}

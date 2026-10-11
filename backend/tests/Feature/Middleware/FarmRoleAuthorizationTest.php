<?php

namespace Tests\Feature\Middleware;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmRoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function memberWithRole(Farm $farm, string $role): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->withRole($role)->create([
            'farm_id' => $farm->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        return $user;
    }

    private function as(User $user, Farm $farm): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'sanctum')->withHeaders(['X-Farm-Id' => $farm->id]);
    }

    public function test_accountant_cannot_create_flock_or_manage_staff(): void
    {
        $farm = Farm::factory()->create();
        $accountant = $this->memberWithRole($farm, 'accountant');

        $this->as($accountant, $farm)->postJson('/api/flocks', [
            'name' => 'فوج اختبار', 'start_date' => '2026-01-01', 'initial_count' => 10,
        ])->assertStatus(403);

        $this->as($accountant, $farm)->postJson('/api/workers', [
            'name' => 'a', 'whatsapp' => '123456789', 'password' => 'password1',
        ])->assertStatus(403);

        $this->as($accountant, $farm)->getJson('/api/workers')->assertStatus(403);
        $this->as($accountant, $farm)->deleteJson('/api/workers/1')->assertStatus(403);
    }

    public function test_accountant_cannot_touch_stock_or_payments(): void
    {
        $farm = Farm::factory()->create();
        $accountant = $this->memberWithRole($farm, 'accountant');
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->as($accountant, $farm)->postJson("/api/flocks/{$flock->id}/sales", [])->assertStatus(403);
        $this->as($accountant, $farm)->patchJson('/api/sales/1/payment', [])->assertStatus(403);
        $this->as($accountant, $farm)->postJson('/api/inventory/items', [])->assertStatus(403);
        $this->as($accountant, $farm)->postJson('/api/inventory/transactions', [])->assertStatus(403);
        $this->as($accountant, $farm)->postJson('/api/expenses', [])->assertStatus(403);
        $this->as($accountant, $farm)->putJson("/api/flocks/{$flock->id}", ['name' => 'z'])->assertStatus(403);
    }

    public function test_daily_entries_are_admin_only(): void
    {
        $farm = Farm::factory()->create();
        $accountant = $this->memberWithRole($farm, 'accountant');
        $admin = $this->memberWithRole($farm, 'farm_admin');
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $payload = ['quantity' => 3, 'entry_date' => now()->toDateString()];

        $this->as($accountant, $farm)->postJson("/api/flocks/{$flock->id}/mortalities", $payload)->assertStatus(403);
        $this->as($admin, $farm)->postJson("/api/flocks/{$flock->id}/mortalities", $payload)->assertStatus(201);
    }

    public function test_partner_is_read_only(): void
    {
        $farm = Farm::factory()->create();
        $partner = $this->memberWithRole($farm, 'partner');
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->as($partner, $farm)->getJson('/api/flocks')->assertOk();
        $this->as($partner, $farm)
            ->postJson("/api/flocks/{$flock->id}/mortalities", ['quantity' => 3, 'entry_date' => now()->toDateString()])
            ->assertStatus(403);
        $this->as($partner, $farm)->postJson('/api/partners', [])->assertStatus(403);
    }

    public function test_farm_admin_in_one_farm_has_no_rights_in_another(): void
    {
        $farmA = Farm::factory()->create();
        $farmB = Farm::factory()->create();
        $admin = $this->memberWithRole($farmA, 'farm_admin');
        FarmUser::factory()->withRole('accountant')->create([
            'farm_id' => $farmB->id, 'user_id' => $admin->id, 'status' => 'active', 'is_primary' => false,
        ]);

        $payload = ['name' => 'فوج اختبار', 'start_date' => '2026-01-01', 'initial_count' => 10];

        $this->as($admin, $farmA)->postJson('/api/flocks', $payload)->assertStatus(201);
        $this->as($admin, $farmB)->postJson('/api/flocks', $payload)->assertStatus(403);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', ['login' => 'nobody@example.com', 'password' => 'wrong-pass']);
        }

        $this->postJson('/api/auth/login', ['login' => 'nobody@example.com', 'password' => 'wrong-pass'])
            ->assertStatus(429);
    }

    public function test_flock_error_response_does_not_leak_internals(): void
    {
        $this->assertStringNotContainsString(
            "'debug'",
            file_get_contents(app_path('Http/Controllers/Api/Flock/FlockController.php'))
        );
    }

    public function test_supervisor_does_operations_but_never_prices_or_payments(): void
    {
        $farm = Farm::factory()->create();
        $supervisor = $this->memberWithRole($farm, 'supervisor');
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        // مالك المزرعة يبقى مسجَّلاً عليها (الشريك الأساسي) حتى لو لم يعد دوره مدير مزرعة
        $farm->update(['admin_user_id' => $supervisor->id]);

        // يسجّل النفوق
        $this->as($supervisor, $farm)
            ->postJson("/api/flocks/{$flock->id}/mortalities", ['quantity' => 3, 'entry_date' => now()->toDateString()])
            ->assertStatus(201);

        // يرى قائمة المراجعة والأسعار (قراءة)
        $this->as($supervisor, $farm)->getJson('/api/accounting/review-queue')->assertOk();
        // قائمة الشركاء وبياناتهم (واتساب/بريد/حصص) ليست من شأنه
        $this->as($supervisor, $farm)->getJson('/api/partners')->assertStatus(403);

        // لا يحدد سعراً ولا يسجّل دفعة ولا يعدّل الشركاء ولا الحسابات
        $this->as($supervisor, $farm)->patchJson('/api/accounting/review-queue/expense/1', ['unit_price' => 5])->assertStatus(403);
        $this->as($supervisor, $farm)->patchJson('/api/accounting/review-queue/expense/1', ['paid_amount' => 5])->assertStatus(403);
        $partner = \App\Models\Partner::create(['farm_id' => $farm->id, 'name' => 'شريك', 'whatsapp' => '0999', 'status' => 'active']);
        $this->as($supervisor, $farm)->patchJson("/api/partners/{$partner->id}/share", ['share_percent' => 10])->assertStatus(403);
        $this->as($supervisor, $farm)->patchJson('/api/sales/1/payment', [])->assertStatus(403);
        $this->as($supervisor, $farm)->getJson('/api/workers')->assertStatus(403);
        $this->as($supervisor, $farm)->getJson('/api/accounting/price-audit')->assertStatus(403);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** ثغرات أمنية مُصلَحة: لا يجب أن تعود */
class SecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    private function member(Farm $farm, ?string $role = null): User
    {
        $u = User::factory()->create();
        $f = FarmUser::factory();
        if ($role) {
            $f = $f->withRole($role);
        }
        $f->create(['farm_id' => $farm->id, 'user_id' => $u->id, 'status' => 'active']);

        return $u;
    }

    private function as(User $u, Farm $farm): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($u, 'sanctum')->withHeaders(['X-Farm-Id' => $farm->id]);
    }

    public function test_farm_admin_cannot_adopt_or_take_over_another_farms_account(): void
    {
        Role::findOrCreate('partner', 'web');
        $farmA = Farm::factory()->create();
        $farmB = Farm::factory()->create();
        $adminA = $this->member($farmA, 'farm_admin');
        $farmA->update(['admin_user_id' => $adminA->id]);
        $victim = $this->member($farmB, 'farm_admin');
        $victim->update(['whatsapp' => '963999000111', 'password' => Hash::make('victim-pass'), 'status' => 'active']);

        $this->as($adminA, $farmA)->postJson('/api/partners', ['name' => 'x', 'whatsapp' => '963999000111', 'password' => 'whatever1', 'share_percent' => 0])
            ->assertStatus(422);

        $victim->refresh();
        $this->assertSame('active', $victim->status);
        $this->assertTrue(Hash::check('victim-pass', $victim->password));
        $this->assertFalse(FarmUser::where('farm_id', $farmA->id)->where('user_id', $victim->id)->exists());
    }

    public function test_partner_update_never_touches_an_account_shared_with_another_farm(): void
    {
        Role::findOrCreate('partner', 'web');
        $farmA = Farm::factory()->create();
        $farmB = Farm::factory()->create();
        $adminA = $this->member($farmA, 'farm_admin');
        $farmA->update(['admin_user_id' => $adminA->id]);

        // حساب موجود في المزرعتين، مربوط كشريك في A
        $shared = $this->member($farmB, 'farm_admin');
        FarmUser::factory()->create(['farm_id' => $farmA->id, 'user_id' => $shared->id, 'status' => 'active']);
        $shared->update(['password' => Hash::make('own-pass')]);
        $partner = Partner::create(['farm_id' => $farmA->id, 'user_id' => $shared->id, 'name' => 'ش', 'whatsapp' => $shared->whatsapp ?? '1', 'status' => 'active']);

        $this->as($adminA, $farmA)->putJson("/api/partners/{$partner->id}", ['name' => 'ش', 'whatsapp' => '963000', 'password' => 'attacker-pass', 'status' => 'active'])
            ->assertSuccessful();

        $this->assertTrue(Hash::check('own-pass', $shared->fresh()->password));
    }

    public function test_supervisor_cannot_price_through_query_string(): void
    {
        $farm = Farm::factory()->create();
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $sup = $this->member($farm, 'supervisor');
        ExpenseCategory::factory()->create(['code' => 'other', 'is_system' => true, 'farm_id' => null]);

        $this->as($sup, $farm)->postJson("/api/flocks/{$flock->id}/expenses?unit_price=100&total_amount=300&paid_amount=300", [
            'expense_type' => 'other', 'quantity' => 3, 'entry_date' => now()->toDateString(),
        ])->assertCreated();

        $e = Expense::latest('id')->firstOrFail();
        $this->assertTrue($e->unit_price === null || (float) $e->unit_price <= 0);
        $this->assertTrue((float) $e->total_amount <= 0);
    }
}

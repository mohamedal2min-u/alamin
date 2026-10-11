<?php

namespace Tests\Feature\Middleware;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmAdminCannotSetPricesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Farm $farm, bool $alsoAccountant = false): User
    {
        $user = User::factory()->create();
        $factory = FarmUser::factory();
        if ($alsoAccountant) {
            $factory = $factory->alsoWithRole('accountant');
        }
        $factory->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);

        return $user;
    }

    private function systemCategory(): void
    {
        ExpenseCategory::factory()->create(['code' => 'other', 'is_system' => true, 'farm_id' => null]);
    }

    private function as(User $user, Farm $farm): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'sanctum')->withHeaders(['X-Farm-Id' => $farm->id]);
    }

    public function test_price_fields_are_ignored_for_farm_admin_expense(): void
    {
        $farm = Farm::factory()->create();
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $admin = $this->admin($farm);
        $this->systemCategory();

        $this->as($admin, $farm)->postJson("/api/flocks/{$flock->id}/expenses", [
            'expense_type' => 'other', 'quantity' => 3, 'unit_price' => 100, 'total_amount' => 300,
            'entry_date' => now()->toDateString(),
        ])->assertStatus(201);

        $expense = Expense::where('flock_id', $flock->id)->latest('id')->firstOrFail();
        $this->assertNull($expense->unit_price);
        $this->assertEquals(0, $expense->total_amount);
    }

    public function test_price_fields_are_ignored_for_farm_admin_water_log(): void
    {
        $farm = Farm::factory()->create();
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $admin = $this->admin($farm);

        $this->as($admin, $farm)->postJson("/api/flocks/{$flock->id}/water-logs", [
            'quantity' => 5, 'total_amount' => 999, 'entry_date' => now()->toDateString(),
        ])->assertStatus(201);

        $this->assertDatabaseMissing('flock_water_logs', ['flock_id' => $flock->id, 'total_amount' => 999]);
    }

    public function test_sale_line_price_is_ignored_for_farm_admin(): void
    {
        $farm = Farm::factory()->create();
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id, 'initial_count' => 100]);
        $admin = $this->admin($farm);

        $this->as($admin, $farm)->postJson("/api/flocks/{$flock->id}/sales", [
            'sale_date' => now()->toDateString(),
            'items' => [['birds_count' => 10, 'total_weight_kg' => 20, 'unit_price_per_kg' => 3]],
        ])->assertStatus(201);

        $this->assertDatabaseMissing('sale_items', ['unit_price_per_kg' => 3]);
    }

    public function test_flock_creation_by_farm_admin_creates_an_unpriced_chicks_expense(): void
    {
        $farm = Farm::factory()->create();
        $admin = $this->admin($farm);

        $this->as($admin, $farm)->postJson('/api/flocks', [
            'name' => 'فوج جديد', 'start_date' => '2026-10-01', 'initial_count' => 1000, 'chick_unit_price' => 2,
        ])->assertStatus(201)->assertJsonPath('data.chick_unit_price', 0);

        $this->assertDatabaseHas('expenses', ['farm_id' => $farm->id, 'quantity' => 1000, 'unit_price' => null, 'total_amount' => 0]);
    }

    public function test_user_with_both_roles_can_still_set_prices(): void
    {
        $farm = Farm::factory()->create();
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $both = $this->admin($farm, alsoAccountant: true);
        $this->systemCategory();

        $this->as($both, $farm)->postJson("/api/flocks/{$flock->id}/expenses", [
            'expense_type' => 'other', 'quantity' => 3, 'unit_price' => 100, 'total_amount' => 300,
            'entry_date' => now()->toDateString(),
        ])->assertStatus(201);

        $this->assertDatabaseHas('expenses', ['flock_id' => $flock->id, 'unit_price' => 100, 'total_amount' => 300]);
    }

    public function test_supervisor_prices_are_ignored_too(): void
    {
        $farm = Farm::factory()->create();
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $user = User::factory()->create();
        FarmUser::factory()->withRole('supervisor')->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);
        $this->systemCategory();

        $this->as($user, $farm)->postJson("/api/flocks/{$flock->id}/expenses", [
            'expense_type' => 'other', 'quantity' => 3, 'unit_price' => 100, 'total_amount' => 300,
            'entry_date' => now()->toDateString(),
        ])->assertStatus(201);

        $expense = Expense::where('flock_id', $flock->id)->latest('id')->firstOrFail();
        $this->assertNull($expense->unit_price);
        $this->assertEquals(0, $expense->total_amount);
    }
}

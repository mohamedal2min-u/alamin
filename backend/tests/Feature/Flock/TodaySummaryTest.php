<?php

namespace Tests\Feature\Flock;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\FlockFeedLog;
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

    public function test_returns_empty_summary_when_no_entries(): void
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
        // Yesterday — must NOT appear
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

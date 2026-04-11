<?php

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

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

    public function test_remaining_count_equals_initial_when_no_mortalities(): void
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

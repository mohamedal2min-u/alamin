<?php

namespace Tests\Feature\Flock;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlockShowTest extends TestCase
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

    public function test_show_returns_flock_data(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create([
            'farm_id' => $farm->id,
            'name'    => 'فوج الاختبار',
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->getJson("/api/flocks/{$flock->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $flock->id)
            ->assertJsonPath('data.name', 'فوج الاختبار')
            ->assertJsonPath('data.farm_id', $farm->id)
            ->assertJsonStructure([
                'data' => [
                    'id', 'farm_id', 'name', 'status', 'start_date', 'end_date',
                    'initial_count', 'current_age_days', 'notes', 'created_at', 'updated_at',
                ],
            ]);
    }

    public function test_show_returns_404_for_wrong_farm(): void
    {
        $farm1 = Farm::factory()->create();
        $farm2 = Farm::factory()->create();
        $user  = $this->actingAsMember($farm1);

        // الفوج ينتمي لمزرعة مختلفة
        $flock = Flock::factory()->create(['farm_id' => $farm2->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm1))
            ->getJson("/api/flocks/{$flock->id}")
            ->assertStatus(404);
    }

    public function test_show_returns_404_for_nonexistent_flock(): void
    {
        $farm = Farm::factory()->create();
        $user = $this->actingAsMember($farm);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->getJson('/api/flocks/99999')
            ->assertStatus(404);
    }

    public function test_show_active_flock_has_age_days(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create([
            'farm_id'    => $farm->id,
            'start_date' => now()->subDays(20)->toDateString(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->getJson("/api/flocks/{$flock->id}")
            ->assertStatus(200);

        $this->assertEquals(20, $response->json('data.current_age_days'));
    }

    public function test_show_closed_flock_has_end_date(): void
    {
        $farm      = Farm::factory()->create();
        $user      = $this->actingAsMember($farm);
        $closeDate = now()->subDays(3)->toDateString();
        $flock     = Flock::factory()->closed()->create([
            'farm_id'    => $farm->id,
            'close_date' => $closeDate,
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->getJson("/api/flocks/{$flock->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.end_date', $closeDate)
            ->assertJsonPath('data.current_age_days', null);
    }

    public function test_show_requires_authentication(): void
    {
        $farm  = Farm::factory()->create();
        $flock = Flock::factory()->create(['farm_id' => $farm->id]);

        $this->withHeaders($this->withFarm($farm))
            ->getJson("/api/flocks/{$flock->id}")
            ->assertStatus(401);
    }
}

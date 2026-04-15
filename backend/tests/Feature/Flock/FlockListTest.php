<?php

namespace Tests\Feature\Flock;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlockListTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function actingAsMember(Farm $farm, string $role = 'farm_admin'): User
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

    public function test_list_returns_all_farm_flocks(): void
    {
        $farm = Farm::factory()->create();
        $user = $this->actingAsMember($farm);

        Flock::factory()->active()->create(['farm_id' => $farm->id, 'name' => 'فوج نشط']);
        Flock::factory()->closed()->create(['farm_id' => $farm->id, 'name' => 'فوج مغلق']);
        Flock::factory()->draft()->create(['farm_id' => $farm->id, 'name' => 'مسودة']);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->getJson('/api/flocks')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_list_does_not_return_other_farm_flocks(): void
    {
        $farm  = Farm::factory()->create();
        $other = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);

        Flock::factory()->create(['farm_id' => $farm->id]);
        Flock::factory()->create(['farm_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->getJson('/api/flocks')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_list_active_flock_appears_first(): void
    {
        $farm = Farm::factory()->create();
        $user = $this->actingAsMember($farm);

        Flock::factory()->closed()->create(['farm_id' => $farm->id]);
        $active = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->getJson('/api/flocks')
            ->assertStatus(200);

        $this->assertEquals($active->id, $response->json('data.0.id'));
    }

    public function test_list_resource_has_expected_fields(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->getJson('/api/flocks')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'farm_id', 'name', 'status', 'start_date', 'end_date',
                     'initial_count', 'current_age_days', 'notes', 'created_at', 'updated_at'],
                ],
            ]);
    }

    public function test_active_flock_has_computed_age_days(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);

        // فوج بدأ قبل 10 أيام
        Flock::factory()->active()->create([
            'farm_id'    => $farm->id,
            'start_date' => now()->subDays(10)->toDateString(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->getJson('/api/flocks')
            ->assertStatus(200);

        // الـ resource يحسب العمر كـ diffInDays + 1 (يوم البدء = اليوم الأول)
        $this->assertEquals(11, $response->json('data.0.current_age_days'));
    }

    public function test_closed_flock_has_null_age_days(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        Flock::factory()->closed()->create(['farm_id' => $farm->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->getJson('/api/flocks')
            ->assertStatus(200);

        $this->assertNull($response->json('data.0.current_age_days'));
    }

    public function test_list_requires_authentication(): void
    {
        $farm = Farm::factory()->create();

        $this->withHeaders($this->withFarm($farm))
            ->getJson('/api/flocks')
            ->assertStatus(401);
    }
}

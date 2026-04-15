<?php

namespace Tests\Feature\Flock;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlockCloseTest extends TestCase
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

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_close_active_flock_uses_today_as_default_date(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->putJson("/api/flocks/{$flock->id}", ['status' => 'closed'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');

        $flock->refresh();
        $this->assertEquals('closed', $flock->status);
        $this->assertEquals(now()->toDateString(), $flock->close_date->toDateString());
    }

    public function test_close_flock_with_custom_close_date(): void
    {
        $farm      = Farm::factory()->create();
        $user      = $this->actingAsMember($farm);
        $flock     = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $closeDate = now()->subDays(3)->toDateString();

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->putJson("/api/flocks/{$flock->id}", [
                'status'     => 'closed',
                'close_date' => $closeDate,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');

        $flock->refresh();
        $this->assertEquals('closed', $flock->status);
        $this->assertEquals($closeDate, $flock->close_date->toDateString());
    }

    public function test_close_date_cannot_be_in_the_future(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->putJson("/api/flocks/{$flock->id}", [
                'status'     => 'closed',
                'close_date' => now()->addDay()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['close_date']);
    }

    public function test_close_date_accepts_today(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->putJson("/api/flocks/{$flock->id}", [
                'status'     => 'closed',
                'close_date' => now()->toDateString(),
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_draft_flock_cannot_be_closed_directly(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->draft()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->putJson("/api/flocks/{$flock->id}", ['status' => 'closed'])
            ->assertStatus(422);
    }

    public function test_closed_flock_cannot_transition_to_active(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->closed()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->putJson("/api/flocks/{$flock->id}", ['status' => 'active'])
            ->assertStatus(422);
    }

    public function test_close_requires_authentication(): void
    {
        $farm  = Farm::factory()->create();
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->withHeaders(['X-Farm-Id' => $farm->id])
            ->putJson("/api/flocks/{$flock->id}", ['status' => 'closed'])
            ->assertStatus(401);
    }
}

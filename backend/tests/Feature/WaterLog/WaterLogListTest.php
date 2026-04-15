<?php

namespace Tests\Feature\WaterLog;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\FlockWaterLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaterLogListTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsMember(Farm $farm): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    public function test_list_returns_water_logs_for_flock(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        FlockWaterLog::factory()->count(3)->create([
            'farm_id'  => $farm->id,
            'flock_id' => $flock->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/flocks/{$flock->id}/water-logs")
            ->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'flock_id', 'entry_date', 'quantity',
                        'unit_label', 'notes', 'created_at',
                    ],
                ],
            ]);
    }

    public function test_list_does_not_return_other_flock_logs(): void
    {
        $farm   = Farm::factory()->create();
        $user   = $this->actingAsMember($farm);
        $flock1 = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $flock2 = Flock::factory()->closed()->create(['farm_id' => $farm->id]);

        FlockWaterLog::factory()->create(['farm_id' => $farm->id, 'flock_id' => $flock1->id]);
        FlockWaterLog::factory()->create(['farm_id' => $farm->id, 'flock_id' => $flock2->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/flocks/{$flock1->id}/water-logs")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_list_is_ordered_newest_first(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        FlockWaterLog::factory()->create([
            'farm_id'    => $farm->id,
            'flock_id'   => $flock->id,
            'entry_date' => now()->subDays(5)->toDateString(),
        ]);
        FlockWaterLog::factory()->create([
            'farm_id'    => $farm->id,
            'flock_id'   => $flock->id,
            'entry_date' => now()->subDays(1)->toDateString(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/flocks/{$flock->id}/water-logs")
            ->assertStatus(200);

        $dates = collect($response->json('data'))->pluck('entry_date')->values();
        $this->assertTrue($dates[0] >= $dates[1], 'الأحدث يجب أن يكون أولاً');
    }

    public function test_list_returns_empty_for_flock_with_no_logs(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/flocks/{$flock->id}/water-logs")
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_list_returns_404_for_wrong_farm(): void
    {
        $farm1 = Farm::factory()->create();
        $farm2 = Farm::factory()->create();
        $user  = $this->actingAsMember($farm1);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm2->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm1->id])
            ->getJson("/api/flocks/{$flock->id}/water-logs")
            ->assertStatus(404);
    }

    public function test_list_requires_authentication(): void
    {
        $farm  = Farm::factory()->create();
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/flocks/{$flock->id}/water-logs")
            ->assertStatus(401);
    }
}

<?php

namespace Tests\Feature\Flock;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\FlockMortality;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlockAgeStopsTest extends TestCase
{
    use RefreshDatabase;

    private function setUpFlockWithNoBirdsLeft(): array
    {
        $farm = Farm::factory()->create();
        $user = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);

        $start = Carbon::today()->subDays(100);
        $flock = Flock::factory()->active()->create([
            'farm_id' => $farm->id, 'start_date' => $start->toDateString(), 'initial_count' => 100,
        ]);

        // آخر 100 دجاجة نفقت/بيعت في اليوم 41
        FlockMortality::create([
            'farm_id' => $farm->id, 'flock_id' => $flock->id, 'quantity' => 100,
            'entry_date' => $start->copy()->addDays(40)->toDateString(), 'created_by' => $user->id,
        ]);

        return [$farm, $user, $flock, $start->copy()->addDays(40)->toDateString()];
    }

    public function test_flock_resource_age_and_last_bird_date_freeze_at_last_bird(): void
    {
        [$farm, $user, $flock, $lastDay] = $this->setUpFlockWithNoBirdsLeft();

        $this->actingAs($user, 'sanctum')->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/flocks/{$flock->id}")
            ->assertOk()
            ->assertJsonPath('data.current_age_days', 41)
            ->assertJsonPath('data.last_bird_date', $lastDay);
    }

    public function test_today_summary_age_freezes_at_last_bird(): void
    {
        [$farm, $user, $flock] = $this->setUpFlockWithNoBirdsLeft();

        $this->actingAs($user, 'sanctum')->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/flocks/{$flock->id}/today-summary")
            ->assertOk()
            ->assertJsonPath('data.flock_info.current_age_days', 41);
    }

    public function test_age_keeps_counting_while_birds_remain(): void
    {
        $farm = Farm::factory()->create();
        $user = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);
        $flock = Flock::factory()->active()->create([
            'farm_id' => $farm->id, 'start_date' => Carbon::today()->subDays(9)->toDateString(), 'initial_count' => 100,
        ]);

        $this->actingAs($user, 'sanctum')->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/flocks/{$flock->id}")
            ->assertOk()
            ->assertJsonPath('data.current_age_days', 10)
            ->assertJsonPath('data.last_bird_date', null);
    }
}

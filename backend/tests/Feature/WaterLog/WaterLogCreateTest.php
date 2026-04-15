<?php

namespace Tests\Feature\WaterLog;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaterLogCreateTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsMember(Farm $farm): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    public function test_creates_water_log_successfully(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/water-logs", [
                'quantity'   => 250.5,
                'unit_label' => 'لتر',
                'notes'      => 'استهلاك طبيعي',
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'تم تسجيل المياه بنجاح')
            ->assertJsonStructure([
                'data' => [
                    'id', 'flock_id', 'entry_date', 'quantity',
                    'unit_label', 'notes', 'created_at',
                ],
            ]);

        $this->assertDatabaseHas('flock_water_logs', [
            'flock_id'   => $flock->id,
            'quantity'   => 250.5,
            'created_by' => $user->id,
        ]);
    }

    public function test_creates_water_log_without_quantity(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/water-logs", [
                'notes' => 'تسجيل بدون كمية',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.quantity', null);
    }

    public function test_cannot_log_water_on_closed_flock(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->closed()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/water-logs", ['quantity' => 100])
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن تسجيل مياه على فوج غير نشط');
    }

    public function test_rejects_invalid_quantity(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/water-logs", ['quantity' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }
}

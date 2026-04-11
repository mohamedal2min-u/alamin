<?php
// backend/tests/Feature/Mortality/MortalityCreateTest.php

namespace Tests\Feature\Mortality;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MortalityCreateTest extends TestCase
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

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'entry_date' => now()->toDateString(),
            'quantity'   => 10,
        ], $overrides);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_create_mortality_successfully(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson("/api/flocks/{$flock->id}/mortalities", $this->validPayload())
            ->assertStatus(201)
            ->assertJsonPath('message', 'تم تسجيل النفوق بنجاح')
            ->assertJsonPath('data.quantity', 10)
            ->assertJsonPath('data.flock_id', $flock->id);

        $this->assertDatabaseHas('flock_mortalities', [
            'flock_id'   => $flock->id,
            'quantity'   => 10,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'worker_id'  => $user->id,
        ]);
    }

    public function test_create_with_reason_and_notes(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson("/api/flocks/{$flock->id}/mortalities", $this->validPayload([
                'reason' => 'مرض تنفسي',
                'notes'  => 'تم عزل المريض',
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.reason', 'مرض تنفسي')
            ->assertJsonPath('data.notes', 'تم عزل المريض');
    }

    public function test_create_sets_editable_until_15_minutes_from_now(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $before = now()->addMinutes(14);
        $after  = now()->addMinutes(16);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson("/api/flocks/{$flock->id}/mortalities", $this->validPayload())
            ->assertStatus(201);

        $record = \App\Models\FlockMortality::first();
        $this->assertNotNull($record->editable_until);
        $this->assertTrue($record->editable_until->between($before, $after));
    }

    public function test_cannot_create_on_closed_flock(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->closed()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson("/api/flocks/{$flock->id}/mortalities", $this->validPayload())
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن تسجيل نفوق على فوج مغلق أو ملغى');
    }

    public function test_cannot_create_on_cancelled_flock(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->cancelled()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson("/api/flocks/{$flock->id}/mortalities", $this->validPayload())
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن تسجيل نفوق على فوج مغلق أو ملغى');
    }

    public function test_cannot_create_on_draft_flock(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->draft()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson("/api/flocks/{$flock->id}/mortalities", $this->validPayload())
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن تسجيل نفوق على فوج غير نشط');
    }

    public function test_create_with_zero_quantity_returns_422(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson("/api/flocks/{$flock->id}/mortalities", $this->validPayload(['quantity' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_create_without_entry_date_returns_422(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson("/api/flocks/{$flock->id}/mortalities", ['quantity' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['entry_date']);
    }

    public function test_create_returns_404_for_wrong_farm(): void
    {
        $farm1 = Farm::factory()->create();
        $farm2 = Farm::factory()->create();
        $user  = $this->actingAsMember($farm1);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm2->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm1))
            ->postJson("/api/flocks/{$flock->id}/mortalities", $this->validPayload())
            ->assertStatus(404);
    }

    public function test_create_requires_authentication(): void
    {
        $farm  = Farm::factory()->create();
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->withHeaders($this->withFarm($farm))
            ->postJson("/api/flocks/{$flock->id}/mortalities", $this->validPayload())
            ->assertStatus(401);
    }
}

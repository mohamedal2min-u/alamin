<?php

namespace Tests\Feature\Flock;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlockCreateTest extends TestCase
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
            'name'          => 'فوج أبريل 2026',
            'start_date'    => now()->toDateString(),
            'initial_count' => 5000,
        ], $overrides);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_create_flock_successfully(): void
    {
        $farm = Farm::factory()->create();
        $user = $this->actingAsMember($farm);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson('/api/flocks', $this->validPayload())
            ->assertStatus(201)
            ->assertJsonPath('message', 'تم إنشاء الفوج بنجاح')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.name', 'فوج أبريل 2026')
            ->assertJsonPath('data.farm_id', $farm->id)
            ->assertJsonPath('data.initial_count', 5000);

        $this->assertDatabaseHas('flocks', [
            'farm_id'    => $farm->id,
            'name'       => 'فوج أبريل 2026',
            'status'     => 'draft',
            'created_by' => $user->id,
        ]);
    }

    public function test_create_sets_created_by(): void
    {
        $farm = Farm::factory()->create();
        $user = $this->actingAsMember($farm);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson('/api/flocks', $this->validPayload())
            ->assertStatus(201);

        $this->assertDatabaseHas('flocks', ['created_by' => $user->id]);
    }

    public function test_create_with_optional_notes(): void
    {
        $farm = Farm::factory()->create();
        $user = $this->actingAsMember($farm);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson('/api/flocks', $this->validPayload(['notes' => 'ملاحظة تجريبية']))
            ->assertStatus(201)
            ->assertJsonPath('data.notes', 'ملاحظة تجريبية');
    }

    public function test_create_without_name_returns_422(): void
    {
        $farm = Farm::factory()->create();
        $user = $this->actingAsMember($farm);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson('/api/flocks', $this->validPayload(['name' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_with_zero_count_returns_422(): void
    {
        $farm = Farm::factory()->create();
        $user = $this->actingAsMember($farm);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson('/api/flocks', $this->validPayload(['initial_count' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['initial_count']);
    }

    public function test_create_without_start_date_returns_422(): void
    {
        $farm = Farm::factory()->create();
        $user = $this->actingAsMember($farm);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson('/api/flocks', ['name' => 'فوج', 'initial_count' => 1000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);
    }

    public function test_create_requires_authentication(): void
    {
        $farm = Farm::factory()->create();

        $this->withHeaders($this->withFarm($farm))
            ->postJson('/api/flocks', $this->validPayload())
            ->assertStatus(401);
    }

    public function test_new_flock_starts_as_draft(): void
    {
        $farm = Farm::factory()->create();
        $user = $this->actingAsMember($farm);

        // حتى لو كان هناك فوج نشط، يمكن إنشاء مسودة جديدة
        Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->postJson('/api/flocks', $this->validPayload())
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');
    }
}

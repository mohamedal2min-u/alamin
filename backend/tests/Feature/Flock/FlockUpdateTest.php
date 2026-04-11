<?php

namespace Tests\Feature\Flock;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlockUpdateTest extends TestCase
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

    public function test_update_name_successfully(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->draft()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->putJson("/api/flocks/{$flock->id}", ['name' => 'اسم جديد'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'اسم جديد')
            ->assertJsonPath('message', 'تم تحديث الفوج بنجاح');

        $this->assertDatabaseHas('flocks', ['id' => $flock->id, 'name' => 'اسم جديد']);
    }

    public function test_transition_draft_to_active(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->draft()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->putJson("/api/flocks/{$flock->id}", ['status' => 'active'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_cannot_activate_when_another_flock_is_active(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);

        // فوج نشط موجود مسبقاً
        Flock::factory()->active()->create(['farm_id' => $farm->id]);

        // مسودة جديدة تحاول الانتقال لنشط
        $draft = Flock::factory()->draft()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->putJson("/api/flocks/{$draft->id}", ['status' => 'active'])
            ->assertStatus(422);
    }

    public function test_transition_active_to_closed(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->putJson("/api/flocks/{$flock->id}", ['status' => 'closed'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');

        // بعد الإغلاق: close_date يجب أن يُضبط
        $this->assertDatabaseHas('flocks', [
            'id'         => $flock->id,
            'status'     => 'closed',
        ]);
        $this->assertNotNull($flock->fresh()->close_date);
    }

    public function test_transition_draft_to_closed_is_not_allowed(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->draft()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->putJson("/api/flocks/{$flock->id}", ['status' => 'closed'])
            ->assertStatus(422);
    }

    public function test_closed_flock_only_notes_can_be_updated(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->closed()->create([
            'farm_id' => $farm->id,
            'name'    => 'الاسم القديم',
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->putJson("/api/flocks/{$flock->id}", [
                'name'  => 'محاولة تغيير الاسم',
                'notes' => 'ملاحظة جديدة',
            ])
            ->assertStatus(200);

        // الاسم يجب أن يظل كما هو
        $this->assertDatabaseHas('flocks', [
            'id'    => $flock->id,
            'name'  => 'الاسم القديم',
            'notes' => 'ملاحظة جديدة',
        ]);
    }

    public function test_update_returns_404_for_wrong_farm(): void
    {
        $farm1 = Farm::factory()->create();
        $farm2 = Farm::factory()->create();
        $user  = $this->actingAsMember($farm1);
        $flock = Flock::factory()->create(['farm_id' => $farm2->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm1))
            ->putJson("/api/flocks/{$flock->id}", ['name' => 'تغيير'])
            ->assertStatus(404);
    }

    public function test_update_sets_updated_by(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->draft()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->withFarm($farm))
            ->putJson("/api/flocks/{$flock->id}", ['name' => 'محدّث'])
            ->assertStatus(200);

        $this->assertDatabaseHas('flocks', [
            'id'         => $flock->id,
            'updated_by' => $user->id,
        ]);
    }

    public function test_update_requires_authentication(): void
    {
        $farm  = Farm::factory()->create();
        $flock = Flock::factory()->create(['farm_id' => $farm->id]);

        $this->withHeaders($this->withFarm($farm))
            ->putJson("/api/flocks/{$flock->id}", ['name' => 'x'])
            ->assertStatus(401);
    }
}

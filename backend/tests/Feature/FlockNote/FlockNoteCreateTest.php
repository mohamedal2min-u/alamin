<?php

namespace Tests\Feature\FlockNote;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlockNoteCreateTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsMember(Farm $farm): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    public function test_creates_note_successfully(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/notes", [
                'note_text' => 'ملاحظة تشغيلية مهمة',
                'note_type' => 'operational',
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'تمت إضافة الملاحظة بنجاح')
            ->assertJsonStructure([
                'data' => [
                    'id', 'flock_id', 'note_type', 'note_text', 'entry_date', 'created_at',
                ],
            ]);

        $this->assertDatabaseHas('flock_notes', [
            'flock_id'   => $flock->id,
            'note_text'  => 'ملاحظة تشغيلية مهمة',
            'note_type'  => 'operational',
            'created_by' => $user->id,
        ]);
    }

    public function test_creates_note_with_default_type(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/notes", [
                'note_text' => 'ملاحظة عامة',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.note_type', 'general');
    }

    public function test_cannot_add_note_on_closed_flock(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->closed()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/notes", ['note_text' => 'ملاحظة'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن إضافة ملاحظة على فوج غير نشط');
    }

    public function test_rejects_empty_note_text(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/notes", ['note_text' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['note_text']);
    }

    public function test_rejects_invalid_note_type(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/notes", [
                'note_text' => 'ملاحظة',
                'note_type' => 'invalid_type',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['note_type']);
    }
}

<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\Idempotency;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdempotencyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function member(Farm $farm, ?User $user = null): User
    {
        $user ??= User::factory()->create();
        FarmUser::factory()->withRole('supervisor')->create([
            'farm_id' => $farm->id,
            'user_id' => $user->id,
            'status'  => 'active',
        ]);

        return $user;
    }

    private function postMortality(User $user, Farm $farm, Flock $flock, ?string $key, array $payload = [])
    {
        $headers = ['X-Farm-Id' => $farm->id];
        if ($key !== null) {
            $headers['Idempotency-Key'] = $key;
        }

        return $this->actingAs($user, 'sanctum')
            ->withHeaders($headers)
            ->postJson("/api/flocks/{$flock->id}/mortalities", array_merge([
                'entry_date' => now()->toDateString(),
                'quantity'   => 7,
            ], $payload));
    }

    public function test_same_key_twice_creates_one_record_and_identical_response(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->member($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $key   = (string) Str::uuid();

        $first  = $this->postMortality($user, $farm, $flock, $key)->assertStatus(201);
        $second = $this->postMortality($user, $farm, $flock, $key)->assertStatus(201);

        $this->assertSame($first->getContent(), $second->getContent());
        $second->assertHeader('Idempotent-Replayed', 'true');
        $this->assertDatabaseCount('flock_mortalities', 1);
        $this->assertDatabaseHas('idempotency_keys', [
            'farm_id'         => $farm->id,
            'user_id'         => $user->id,
            'key'             => $key,
            'response_status' => 201,
        ]);
    }

    public function test_replay_works_for_flock_notes_and_water_logs(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->member($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $key = (string) Str::uuid();
        foreach ([1, 2] as $_) {
            $this->actingAs($user, 'sanctum')
                ->withHeaders(['X-Farm-Id' => $farm->id, 'Idempotency-Key' => $key])
                ->postJson("/api/flocks/{$flock->id}/notes", ['note_text' => 'ملاحظة'])
                ->assertStatus(201);
        }
        $this->assertDatabaseCount('flock_notes', 1);

        $key = (string) Str::uuid();
        foreach ([1, 2] as $_) {
            $this->actingAs($user, 'sanctum')
                ->withHeaders(['X-Farm-Id' => $farm->id, 'Idempotency-Key' => $key])
                ->postJson("/api/flocks/{$flock->id}/water-logs", [
                    'entry_date' => now()->toDateString(),
                    'quantity'   => 3,
                ])
                ->assertSuccessful();
        }
        $this->assertDatabaseCount('flock_water_logs', 1);
    }

    public function test_other_user_cannot_replay_another_users_key(): void
    {
        $farm  = Farm::factory()->create();
        $alice = $this->member($farm);
        $bob   = $this->member($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $key   = (string) Str::uuid();

        $this->postMortality($alice, $farm, $flock, $key)->assertStatus(201);
        $this->postMortality($bob, $farm, $flock, $key)
            ->assertStatus(201)
            ->assertHeaderMissing('Idempotent-Replayed');

        $this->assertDatabaseCount('flock_mortalities', 2);
        $this->assertDatabaseHas('flock_mortalities', ['created_by' => $bob->id]);
    }

    public function test_other_farm_cannot_replay_key(): void
    {
        $farmA  = Farm::factory()->create();
        $farmB  = Farm::factory()->create();
        $user   = $this->member($farmA);
        $this->member($farmB, $user);
        $flockA = Flock::factory()->active()->create(['farm_id' => $farmA->id]);
        $flockB = Flock::factory()->active()->create(['farm_id' => $farmB->id]);
        $key    = (string) Str::uuid();

        $this->postMortality($user, $farmA, $flockA, $key)->assertStatus(201);
        $this->postMortality($user, $farmB, $flockB, $key)
            ->assertStatus(201)
            ->assertHeaderMissing('Idempotent-Replayed')
            ->assertJsonPath('data.flock_id', $flockB->id);

        $this->assertDatabaseCount('flock_mortalities', 2);
    }

    public function test_same_key_with_different_payload_returns_422(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->member($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $key   = (string) Str::uuid();

        $this->postMortality($user, $farm, $flock, $key, ['quantity' => 5])->assertStatus(201);
        $this->postMortality($user, $farm, $flock, $key, ['quantity' => 6])
            ->assertStatus(422)
            ->assertJsonPath('message', 'مفتاح منع التكرار مستخدم لطلب مختلف');

        $this->assertDatabaseCount('flock_mortalities', 1);
    }

    public function test_same_key_on_a_different_route_returns_422(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->member($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $key   = (string) Str::uuid();

        $this->postMortality($user, $farm, $flock, $key)->assertStatus(201);
        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id, 'Idempotency-Key' => $key])
            ->postJson("/api/flocks/{$flock->id}/notes", ['note_text' => 'x'])
            ->assertStatus(422);

        $this->assertDatabaseCount('flock_notes', 0);
    }

    public function test_requests_without_header_behave_as_before(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->member($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->postMortality($user, $farm, $flock, null)->assertStatus(201);
        $this->postMortality($user, $farm, $flock, null)->assertStatus(201);

        $this->assertDatabaseCount('flock_mortalities', 2);
        $this->assertDatabaseCount('idempotency_keys', 0);
    }

    public function test_failed_validation_is_not_stored_and_key_can_be_reused_after_fix(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->member($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $key   = (string) Str::uuid();

        $this->postMortality($user, $farm, $flock, $key, ['quantity' => 0])->assertStatus(422);
        $this->assertDatabaseCount('idempotency_keys', 0);

        $this->postMortality($user, $farm, $flock, $key, ['quantity' => 4])->assertStatus(201);
        $this->assertDatabaseCount('flock_mortalities', 1);
    }

    public function test_invalid_key_is_rejected(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->member($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->postMortality($user, $farm, $flock, 'not-a-uuid')->assertStatus(400);
        $this->assertDatabaseCount('flock_mortalities', 0);
    }

    public function test_in_progress_key_returns_409(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->member($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $key   = (string) Str::uuid();

        // طلب أول ناجح ثم نعيد المفتاح لحالة "قيد التنفيذ" (كأن الطلب الأول لم ينتهِ بعد)
        $this->postMortality($user, $farm, $flock, $key)->assertStatus(201);
        DB::table('idempotency_keys')->update(['response_status' => null, 'response_body' => null]);

        $this->postMortality($user, $farm, $flock, $key)
            ->assertStatus(409)
            ->assertJsonPath('code', 'idempotency_in_progress');
        $this->assertDatabaseCount('flock_mortalities', 1);
    }

    public function test_expired_keys_are_pruned_and_not_replayed(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->member($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $key   = (string) Str::uuid();

        $this->postMortality($user, $farm, $flock, $key)->assertStatus(201);
        DB::table('idempotency_keys')->update(['created_at' => now()->subHours(49)]);

        // بعد 48 ساعة المفتاح لا يُعاد استخدامه
        $this->postMortality($user, $farm, $flock, $key)
            ->assertStatus(201)
            ->assertHeaderMissing('Idempotent-Replayed');
        $this->assertDatabaseCount('flock_mortalities', 2);

        DB::table('idempotency_keys')->update(['created_at' => now()->subHours(49)]);
        $this->assertSame(1, Idempotency::prune());
        $this->assertDatabaseCount('idempotency_keys', 0);
    }
}

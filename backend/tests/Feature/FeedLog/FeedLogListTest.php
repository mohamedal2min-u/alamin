<?php

namespace Tests\Feature\FeedLog;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\FlockFeedLog;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedLogListTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsMember(Farm $farm): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    private function makeFeedItem(Farm $farm): Item
    {
        $feedType = ItemType::factory()->feed()->create();
        return Item::factory()->forFarm($farm)->create(['item_type_id' => $feedType->id]);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_list_returns_feed_logs_for_flock(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $item  = $this->makeFeedItem($farm);

        FlockFeedLog::factory()->count(3)->create([
            'farm_id'  => $farm->id,
            'flock_id' => $flock->id,
            'item_id'  => $item->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/flocks/{$flock->id}/feed-logs")
            ->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'flock_id', 'item_id', 'item_name', 'item_input_unit',
                        'entry_date', 'quantity', 'unit_label', 'notes',
                        'inventory_linked', 'created_at',
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
        $item   = $this->makeFeedItem($farm);

        FlockFeedLog::factory()->create(['farm_id' => $farm->id, 'flock_id' => $flock1->id, 'item_id' => $item->id]);
        FlockFeedLog::factory()->create(['farm_id' => $farm->id, 'flock_id' => $flock2->id, 'item_id' => $item->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/flocks/{$flock1->id}/feed-logs")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_list_is_ordered_newest_first(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $item  = $this->makeFeedItem($farm);

        FlockFeedLog::factory()->create([
            'farm_id'    => $farm->id,
            'flock_id'   => $flock->id,
            'item_id'    => $item->id,
            'entry_date' => now()->subDays(5)->toDateString(),
        ]);
        FlockFeedLog::factory()->create([
            'farm_id'    => $farm->id,
            'flock_id'   => $flock->id,
            'item_id'    => $item->id,
            'entry_date' => now()->subDays(1)->toDateString(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/flocks/{$flock->id}/feed-logs")
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
            ->getJson("/api/flocks/{$flock->id}/feed-logs")
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
            ->getJson("/api/flocks/{$flock->id}/feed-logs")
            ->assertStatus(404);
    }

    public function test_store_returns_full_resource(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $item  = $this->makeFeedItem($farm);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/feed-logs", [
                'item_id'  => $item->id,
                'quantity' => 50,
            ])
            ->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id', 'flock_id', 'item_id', 'item_name', 'item_input_unit',
                    'entry_date', 'quantity', 'unit_label', 'inventory_linked', 'created_at',
                ],
            ])
            ->assertJsonPath('data.quantity', 50)
            ->assertJsonPath('data.inventory_linked', false);
    }

    public function test_list_requires_authentication(): void
    {
        $farm  = Farm::factory()->create();
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/flocks/{$flock->id}/feed-logs")
            ->assertStatus(401);
    }
}

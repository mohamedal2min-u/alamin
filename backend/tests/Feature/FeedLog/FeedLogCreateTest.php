<?php

namespace Tests\Feature\FeedLog;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedLogCreateTest extends TestCase
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

    public function test_creates_feed_log_successfully(): void
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
                'notes'    => 'تغذية صباحية',
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'تم تسجيل العلف بنجاح');

        $this->assertDatabaseHas('flock_feed_logs', [
            'flock_id'   => $flock->id,
            'item_id'    => $item->id,
            'quantity'   => 50,
            'created_by' => $user->id,
        ]);
    }

    public function test_deducts_from_inventory_when_warehouse_exists(): void
    {
        $farm      = Farm::factory()->create();
        $user      = $this->actingAsMember($farm);
        $flock     = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $item      = $this->makeFeedItem($farm);
        $warehouse = Warehouse::factory()->forFarm($farm)->create();
        WarehouseItem::factory()->create([
            'farm_id'          => $farm->id,
            'warehouse_id'     => $warehouse->id,
            'item_id'          => $item->id,
            'current_quantity' => 500,
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/feed-logs", ['item_id' => $item->id, 'quantity' => 100])
            ->assertStatus(201);

        $this->assertDatabaseHas('warehouse_items', [
            'item_id'          => $item->id,
            'warehouse_id'     => $warehouse->id,
            'current_quantity' => 400,
        ]);

        $this->assertDatabaseHas('inventory_transactions', [
            'item_id'       => $item->id,
            'flock_id'      => $flock->id,
            'direction'     => 'out',
            'source_module' => 'flock_feed',
        ]);
    }

    public function test_creates_log_without_warehouse(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $item  = $this->makeFeedItem($farm);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/feed-logs", ['item_id' => $item->id, 'quantity' => 30])
            ->assertStatus(201);

        $this->assertDatabaseHas('flock_feed_logs', [
            'flock_id'                 => $flock->id,
            'inventory_transaction_id' => null,
        ]);
    }

    public function test_cannot_log_feed_on_closed_flock(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->closed()->create(['farm_id' => $farm->id]);
        $item  = $this->makeFeedItem($farm);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/feed-logs", ['item_id' => $item->id, 'quantity' => 10])
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن تسجيل علف على فوج غير نشط');
    }

    public function test_rejects_item_from_another_farm(): void
    {
        $farm1 = Farm::factory()->create();
        $farm2 = Farm::factory()->create();
        $user  = $this->actingAsMember($farm1);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm1->id]);
        $item  = $this->makeFeedItem($farm2);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm1->id])
            ->postJson("/api/flocks/{$flock->id}/feed-logs", ['item_id' => $item->id, 'quantity' => 10])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['item_id']);
    }

    public function test_requires_authentication(): void
    {
        $farm  = Farm::factory()->create();
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $this->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/feed-logs", ['item_id' => 1, 'quantity' => 10])
            ->assertStatus(401);
    }
}

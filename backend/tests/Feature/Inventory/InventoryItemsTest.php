<?php

namespace Tests\Feature\Inventory;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryItemsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsMember(Farm $farm): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    public function test_returns_all_active_items_for_farm(): void
    {
        $farm     = Farm::factory()->create();
        $user     = $this->actingAsMember($farm);
        $feedType = ItemType::factory()->feed()->create();
        Item::factory()->forFarm($farm)->create(['name' => 'علف أ', 'item_type_id' => $feedType->id]);
        Item::factory()->forFarm($farm)->create(['name' => 'علف ب', 'item_type_id' => $feedType->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson('/api/inventory/items')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_filters_by_feed_type(): void
    {
        $farm     = Farm::factory()->create();
        $user     = $this->actingAsMember($farm);
        $feedType = ItemType::factory()->feed()->create();
        $medType  = ItemType::factory()->medicine()->create();

        Item::factory()->forFarm($farm)->create(['name' => 'علف أ', 'item_type_id' => $feedType->id]);
        Item::factory()->forFarm($farm)->create(['name' => 'دواء أ', 'item_type_id' => $medType->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson('/api/inventory/items?type=feed')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'علف أ');
    }

    public function test_filters_by_medicine_type(): void
    {
        $farm    = Farm::factory()->create();
        $user    = $this->actingAsMember($farm);
        $medType = ItemType::factory()->medicine()->create();

        Item::factory()->forFarm($farm)->create(['item_type_id' => $medType->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson('/api/inventory/items?type=medicine')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type_code', 'medicine');
    }

    public function test_does_not_return_items_from_other_farms(): void
    {
        $farm1    = Farm::factory()->create();
        $farm2    = Farm::factory()->create();
        $user     = $this->actingAsMember($farm1);
        $feedType = ItemType::factory()->feed()->create();

        Item::factory()->create(['farm_id' => $farm2->id, 'item_type_id' => $feedType->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm1->id])
            ->getJson('/api/inventory/items')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_requires_authentication(): void
    {
        $farm = Farm::factory()->create();
        $this->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson('/api/inventory/items')
            ->assertStatus(401);
    }
}

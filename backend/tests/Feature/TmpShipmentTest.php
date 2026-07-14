<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Farm;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\FarmUser;
use App\Models\Expense;
use App\Models\InventoryTransaction;

class TmpShipmentTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsMember(Farm $farm): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    public function test_add_shipment_successfully()
    {
        $farm = Farm::factory()->create(['status' => 'active']);
        $user = $this->actingAsMember($farm);
        $warehouse = Warehouse::factory()->create(['farm_id' => $farm->id, 'is_active' => true]);
        $itemType = ItemType::factory()->create(['code' => 'medicine']);
        $item = Item::factory()->create(['farm_id' => $farm->id, 'item_type_id' => $itemType->id, 'status' => 'active', 'unit_value' => 10]);

        $data = [
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'original_quantity' => 5.5,
            'transaction_date' => '2026-07-14',
            'unit_price' => 20,
            'total_amount' => 110,
            'payment_status' => 'paid',
            'paid_amount' => 110,
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson('/api/inventory/transactions', $data);

        if ($response->status() !== 201) {
            dump($response->json());
        }

        $response->assertStatus(201);
        $this->assertDatabaseHas('inventory_transactions', [
            'farm_id' => $farm->id,
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'original_quantity' => 5.5,
            'computed_quantity' => 55, // 5.5 * 10
            'payment_status' => 'paid',
        ]);
    }
}


<?php

namespace Tests\Feature\MedicineLog;

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

class MedicineLogCreateTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsMember(Farm $farm): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    private function makeMedicineItem(Farm $farm): Item
    {
        $medType = ItemType::factory()->medicine()->create();
        return Item::factory()->forFarm($farm)->create(['item_type_id' => $medType->id]);
    }

    public function test_creates_medicine_log_successfully(): void
    {
        $farm      = Farm::factory()->create();
        $user      = $this->actingAsMember($farm);
        $flock     = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $item      = $this->makeMedicineItem($farm);
        $warehouse = Warehouse::where('farm_id', $farm->id)->first();
        WarehouseItem::factory()->create([
            'farm_id'          => $farm->id,
            'warehouse_id'     => $warehouse->id,
            'item_id'          => $item->id,
            'current_quantity' => 1000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/medicine-logs", [
                'item_id'  => $item->id,
                'quantity' => 5,
                'notes'    => 'جرعة وقاية',
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'تم تسجيل الدواء بنجاح');

        $this->assertDatabaseHas('flock_medicines', [
            'flock_id'   => $flock->id,
            'item_id'    => $item->id,
            'quantity'   => 5,
            'created_by' => $user->id,
        ]);
    }

    public function test_cannot_log_medicine_on_closed_flock(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->closed()->create(['farm_id' => $farm->id]);
        $item  = $this->makeMedicineItem($farm);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/medicine-logs", ['item_id' => $item->id, 'quantity' => 2])
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن تسجيل دواء على فوج غير نشط');
    }

    public function test_rejects_quantity_exceeding_available_stock(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        // unit_value = 1 حتى يكون realQty = quantity (بدون ضرب بوحدة التحويل)
        $medType   = ItemType::factory()->medicine()->create();
        $item      = Item::factory()->forFarm($farm)->create([
            'item_type_id' => $medType->id,
            'unit_value'   => 1,
        ]);
        $warehouse = Warehouse::where('farm_id', $farm->id)->first();
        WarehouseItem::factory()->create([
            'farm_id'          => $farm->id,
            'warehouse_id'     => $warehouse->id,
            'item_id'          => $item->id,
            'current_quantity' => 10,
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/medicine-logs", ['item_id' => $item->id, 'quantity' => 15])
            ->assertStatus(422)
            ->assertJsonPath('message', 'الكمية المطلوبة أكبر من المتوفر بالمخزون. المتوفر: 10 ' . $item->input_unit);

        $this->assertDatabaseMissing('flock_medicines', [
            'flock_id' => $flock->id,
            'item_id'  => $item->id,
        ]);

        $this->assertDatabaseHas('warehouse_items', [
            'item_id'          => $item->id,
            'warehouse_id'     => $warehouse->id,
            'current_quantity' => 10,
        ]);
    }

    public function test_rejects_item_from_another_farm(): void
    {
        $farm1 = Farm::factory()->create();
        $farm2 = Farm::factory()->create();
        $user  = $this->actingAsMember($farm1);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm1->id]);
        $item  = $this->makeMedicineItem($farm2);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm1->id])
            ->postJson("/api/flocks/{$flock->id}/medicine-logs", ['item_id' => $item->id, 'quantity' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['item_id']);
    }
}

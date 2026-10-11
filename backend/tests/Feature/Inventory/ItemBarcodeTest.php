<?php

namespace Tests\Feature\Inventory;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ItemBarcodeTest extends TestCase
{
    use RefreshDatabase;

    private function member(Farm $farm, string $role = 'farm_admin'): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->withRole($role)->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    private function as(User $u, Farm $farm)
    {
        return $this->actingAs($u, 'sanctum')->withHeaders(['X-Farm-Id' => $farm->id]);
    }

    public function test_known_barcode_returns_item(): void
    {
        $farm = Farm::factory()->create();
        $type = ItemType::factory()->feed()->create();
        $item = Item::factory()->forFarm($farm)->create(['item_type_id' => $type->id, 'barcode' => '6281234567890']);

        $this->as($this->member($farm), $farm)->getJson('/api/inventory/barcode/6281234567890')
            ->assertOk()->assertJsonPath('data.found', true)->assertJsonPath('data.item_id', $item->id);
    }

    public function test_barcode_of_other_farm_is_not_found(): void
    {
        $farm  = Farm::factory()->create();
        $other = Farm::factory()->create();
        $type  = ItemType::factory()->feed()->create();
        Item::factory()->forFarm($other)->create(['item_type_id' => $type->id, 'barcode' => '111222333']);
        Http::fake(['*' => Http::response(['status' => 0], 200)]);

        $this->as($this->member($farm), $farm)->getJson('/api/inventory/barcode/111222333')
            ->assertOk()->assertJsonPath('data.found', false)->assertJsonPath('data.suggested_name', null);
    }

    public function test_unknown_barcode_suggests_name_from_open_database(): void
    {
        $farm = Farm::factory()->create();
        Http::fake(['*openfoodfacts*' => Http::response(['status' => 1, 'product' => ['product_name' => 'Vitamin X', 'brands' => 'Acme']], 200)]);

        $this->as($this->member($farm), $farm)->getJson('/api/inventory/barcode/5000112637922')
            ->assertOk()->assertJsonPath('data.found', false)->assertJsonPath('data.suggested_name', 'Vitamin X - Acme');
    }

    public function test_external_lookup_failure_is_soft(): void
    {
        $farm = Farm::factory()->create();
        Http::fake(['*' => Http::response('', 500)]);

        $this->as($this->member($farm), $farm)->getJson('/api/inventory/barcode/5000112637922')
            ->assertOk()->assertJsonPath('data.suggested_name', null);
    }

    public function test_create_item_with_barcode_and_duplicate_blocked(): void
    {
        $farm = Farm::factory()->create();
        $type = ItemType::factory()->medicine()->create();
        $u    = $this->member($farm);
        $body = ['item_type_id' => $type->id, 'name' => 'دواء 1', 'barcode' => '12345678', 'input_unit' => 'علبة', 'unit_value' => 1, 'content_unit' => 'علبة'];

        $this->as($u, $farm)->postJson('/api/inventory/items', $body)->assertCreated();
        $this->as($u, $farm)->postJson('/api/inventory/items', [...$body, 'name' => 'دواء 2'])->assertStatus(422);
    }

    public function test_accountant_cannot_lookup_and_set_barcode_links_existing_item(): void
    {
        $farm = Farm::factory()->create();
        $type = ItemType::factory()->feed()->create();
        $item = Item::factory()->forFarm($farm)->create(['item_type_id' => $type->id]);

        $this->as($this->member($farm, 'accountant'), $farm)->getJson('/api/inventory/barcode/12345678')->assertForbidden();

        $this->as($this->member($farm, 'supervisor'), $farm)
            ->patchJson("/api/inventory/items/{$item->id}/barcode", ['barcode' => '12345678'])->assertOk();
        $this->assertSame('12345678', $item->fresh()->barcode);
    }
}

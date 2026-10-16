<?php

namespace Tests\Feature\Console;

use App\Actions\Inventory\CreateShipmentAction;
use App\Actions\MedicineLog\CreateMedicineLogAction;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\FlockMedicine;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeFlockMedicinesTest extends TestCase
{
    use RefreshDatabase;

    private Farm $farm;

    private Flock $flock;

    private Warehouse $warehouse;

    private Item $medicine;

    private Item $feed;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->farm = Farm::factory()->create();
        $this->user = User::factory()->create();
        $this->flock = Flock::factory()->active()->create(['farm_id' => $this->farm->id]);
        // نفس المستودع الذي يستخدمه تسجيل الدواء (أول مستودع نشط للمزرعة)
        $this->warehouse = Warehouse::where('farm_id', $this->farm->id)->where('is_active', true)->first()
            ?? Warehouse::factory()->create(['farm_id' => $this->farm->id]);

        $medType = ItemType::factory()->medicine()->create();
        $feedType = ItemType::factory()->feed()->create();
        $this->medicine = Item::factory()->forFarm($this->farm)->create(['item_type_id' => $medType->id, 'name' => 'أموكسيسيلين', 'input_unit' => 'عبوة', 'unit_value' => 1]);
        $this->feed = Item::factory()->forFarm($this->farm)->create(['item_type_id' => $feedType->id, 'name' => 'علف مرحلة اولى']);

        // مخزون سابق للدواء (قبل الفوج) = 2 عبوة
        WarehouseItem::factory()->create(['farm_id' => $this->farm->id, 'warehouse_id' => $this->warehouse->id, 'item_id' => $this->medicine->id, 'current_quantity' => 2, 'average_cost' => 5]);
    }

    private function ship(Item $item, float $qty, ?float $unitPrice): InventoryTransaction
    {
        return app(CreateShipmentAction::class)->execute($this->farm->id, $this->user->id, array_filter([
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $item->id,
            'original_quantity' => $qty,
            'transaction_date' => now()->toDateString(),
            'flock_id' => $this->flock->id,
            'unit_price' => $unitPrice,
            'total_amount' => $unitPrice !== null ? $unitPrice * $qty : null,
        ], fn ($v) => $v !== null));
    }

    private function stock(Item $item): float
    {
        return (float) WarehouseItem::where('warehouse_id', $this->warehouse->id)->where('item_id', $item->id)->value('current_quantity');
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->ship($this->medicine, 10, 8);
        app(CreateMedicineLogAction::class)->execute($this->flock, $this->user->id, ['item_id' => $this->medicine->id, 'quantity' => 3]);

        $this->artisan('flock:purge-medicines', ['flock' => $this->flock->id])->assertSuccessful();

        $this->assertSame(1, FlockMedicine::count());
        $this->assertEquals(9, $this->stock($this->medicine));
    }

    public function test_force_removes_medicine_records_and_restores_stock_but_keeps_feed(): void
    {
        $this->ship($this->medicine, 10, 8);
        $this->ship($this->feed, 20, 400);
        app(CreateMedicineLogAction::class)->execute($this->flock, $this->user->id, ['item_id' => $this->medicine->id, 'quantity' => 3]);
        $this->assertEquals(9, $this->stock($this->medicine));

        $this->artisan('flock:purge-medicines', ['flock' => $this->flock->id, '--force' => true])->assertSuccessful();

        $this->assertSame(0, FlockMedicine::where('flock_id', $this->flock->id)->count());
        $this->assertSame(0, InventoryTransaction::where('item_id', $this->medicine->id)->count());
        $this->assertSame(0, Expense::whereHas('linkedInventoryTransaction', fn ($q) => $q->where('item_id', $this->medicine->id))->count());
        // المخزون يرجع لما كان قبل الفوج
        $this->assertEquals(2, $this->stock($this->medicine));

        // العلف لا يُمس
        $this->assertSame(1, InventoryTransaction::where('item_id', $this->feed->id)->count());
        $this->assertSame(1, Expense::whereNotNull('linked_inventory_transaction_id')->count());
    }

    public function test_refuses_when_stock_would_go_negative(): void
    {
        // شحنة الفوج استُهلك منها خارج الفوج (تسوية مخزون مثلاً): بقي 1 فقط من 12
        $this->ship($this->medicine, 10, 8);
        WarehouseItem::where('item_id', $this->medicine->id)->update(['current_quantity' => 1]);

        $this->artisan('flock:purge-medicines', ['flock' => $this->flock->id, '--force' => true])->assertFailed();

        $this->assertSame(1, InventoryTransaction::where('flock_id', $this->flock->id)->count());
    }

    public function test_zero_stock_purges_even_when_stock_would_go_negative_and_zeroes_all_medicines(): void
    {
        $this->ship($this->medicine, 10, 8);
        WarehouseItem::where('item_id', $this->medicine->id)->update(['current_quantity' => 1]);
        $other = Item::factory()->forFarm($this->farm)->create(['item_type_id' => $this->medicine->item_type_id, 'name' => 'فيتامين']);
        WarehouseItem::factory()->create(['farm_id' => $this->farm->id, 'warehouse_id' => $this->warehouse->id, 'item_id' => $other->id, 'current_quantity' => 7]);
        $this->ship($this->feed, 20, 400);
        $feedStock = $this->stock($this->feed);

        $this->artisan('flock:purge-medicines', ['flock' => $this->flock->id, '--force' => true, '--zero-stock' => true])->assertSuccessful();

        $this->assertSame(0, InventoryTransaction::where('item_id', $this->medicine->id)->count());
        $this->assertEquals(0, $this->stock($this->medicine));
        $this->assertEquals(0, $this->stock($other));
        $this->assertEquals($feedStock, $this->stock($this->feed));
    }

    public function test_removes_flock_medicine_debts_whose_shipment_is_not_on_the_flock(): void
    {
        $shipment = $this->ship($this->medicine, 10, 8);
        // الدين على الفوج، والشحنة بلا فوج
        $shipment->update(['flock_id' => null]);

        $this->artisan('flock:purge-medicines', ['flock' => $this->flock->id, '--force' => true, '--zero-stock' => true])->assertSuccessful();

        $this->assertSame(0, Expense::where('flock_id', $this->flock->id)->count());
        $this->assertNull(InventoryTransaction::find($shipment->id));
        $this->assertEquals(0, $this->stock($this->medicine));
    }

    public function test_refuses_closed_flock(): void
    {
        $closed = Flock::factory()->closed()->create(['farm_id' => $this->farm->id]);

        $this->artisan('flock:purge-medicines', ['flock' => $closed->id, '--force' => true])->assertFailed();
    }
}

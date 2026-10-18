<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\FlockWaterLog;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\Warehouse;
use App\Models\WarehouseItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** اختبارات أخطاء محاسبية مُصلَحة: لا يجب أن تعود */
class AccountingFixesTest extends TestCase
{
    use RefreshDatabase;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->farm = Farm::factory()->create();
    }

    private function member(string $role): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->withRole($role)->create(['farm_id' => $this->farm->id, 'user_id' => $user->id, 'status' => 'active']);

        return $user;
    }

    private function as(User $u): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($u, 'sanctum')->withHeaders(['X-Farm-Id' => $this->farm->id]);
    }

    /** مدير يضيف فوجاً، والمحاسب يسعّر الكتاكيت ويدفع نصفها */
    private function pricedFlock(User $admin, User $acct): array
    {
        $flockId = $this->as($admin)->postJson('/api/flocks', ['name' => 'F1', 'start_date' => now()->toDateString(), 'initial_count' => 10000])
            ->assertCreated()->json('data.id');
        $exp = Expense::where('flock_id', $flockId)->firstOrFail();
        $this->as($acct)->patchJson("/api/accounting/review-queue/expense/{$exp->id}", ['unit_price' => 0.5, 'paid_amount' => 2500])->assertOk();

        return [$flockId, $exp];
    }

    public function test_editing_flock_keeps_priced_chick_expense(): void
    {
        $admin = $this->member('farm_admin');
        [$flockId, $exp] = $this->pricedFlock($admin, $this->member('accountant'));

        // نموذج التعديل يرسل العدد دائماً وسعر الفوج فارغ
        $this->as($admin)->putJson("/api/flocks/{$flockId}", ['name' => 'F1 جديد', 'initial_count' => 10000, 'chick_unit_price' => null])->assertOk();

        $e = Expense::find($exp->id);
        $this->assertNotNull($e, 'مصروف الكتاكيت حُذف');
        $this->assertEquals(5000, (float) $e->total_amount);
        $this->assertEquals(2500, (float) $e->paid_amount);
    }

    public function test_changing_chick_count_recomputes_with_expense_price_and_status(): void
    {
        $admin = $this->member('farm_admin');
        [$flockId, $exp] = $this->pricedFlock($admin, $this->member('accountant'));

        $this->as($admin)->putJson("/api/flocks/{$flockId}", ['initial_count' => 12000])->assertOk();

        $e = $exp->fresh();
        $this->assertEquals(6000, (float) $e->total_amount);   // 12000 × 0.5
        $this->assertEquals(3500, (float) $e->remaining_amount);
        $this->assertSame('partial', $e->payment_status);
    }

    public function test_unpriced_water_blocks_flock_close(): void
    {
        $admin = $this->member('farm_admin');
        $flock = Flock::factory()->active()->create(['farm_id' => $this->farm->id]);
        FlockWaterLog::create(['farm_id' => $this->farm->id, 'flock_id' => $flock->id, 'entry_date' => now()->toDateString(), 'quantity' => 5, 'payment_status' => 'unpaid']);

        $this->as($admin)->putJson("/api/flocks/{$flock->id}", ['status' => 'closed'])->assertStatus(422);
        $this->assertSame('active', $flock->fresh()->status);
    }

    public function test_price_cannot_drop_below_paid_amount(): void
    {
        $acct = $this->member('accountant');
        [, $exp] = $this->pricedFlock($this->member('farm_admin'), $acct);

        // مدفوع 2500؛ سعر 0.2 يجعل الإجمالي 2000
        $this->as($acct)->patchJson("/api/accounting/review-queue/expense/{$exp->id}", ['unit_price' => 0.2])->assertStatus(422);
        $this->assertEquals(5000, (float) $exp->fresh()->total_amount);
    }

    public function test_closed_flock_is_locked_for_everyone(): void
    {
        $admin = $this->member('farm_admin');
        [$flockId, $exp] = $this->pricedFlock($admin, $this->member('accountant'));
        Flock::whereKey($flockId)->update(['status' => 'closed']);

        // حتى من يملك صلاحيتي المدير والمحاسب
        $both = User::factory()->create();
        FarmUser::factory()->withRole('farm_admin')->alsoWithRole('accountant')->create(['farm_id' => $this->farm->id, 'user_id' => $both->id, 'status' => 'active']);
        $this->as($both)->patchJson("/api/accounting/review-queue/expense/{$exp->id}", ['unit_price' => 0.6])->assertStatus(403);

        $cat = ExpenseCategory::firstOrCreate(['name' => 'علف', 'farm_id' => null], ['is_system' => true, 'is_active' => true]);
        $this->as($admin)->postJson('/api/expenses', ['flock_id' => $flockId, 'expense_category_id' => $cat->id, 'entry_date' => now()->toDateString(), 'quantity' => 1])
            ->assertStatus(422);
    }

    /** صنف علف كيسه 50 كغ */
    private function feedItem(): array
    {
        $type = ItemType::firstOrCreate(['code' => 'feed'], ['name' => 'علف', 'is_system' => true, 'is_active' => true]);
        $item = Item::factory()->create(['farm_id' => $this->farm->id, 'item_type_id' => $type->id, 'unit_value' => 50, 'status' => 'active']);

        return [$item, Warehouse::where('farm_id', $this->farm->id)->firstOrFail()];
    }

    private function ship(User $admin, Item $item, Warehouse $wh, int $bags): Expense
    {
        $this->as($admin)->postJson('/api/inventory/transactions', ['item_id' => $item->id, 'warehouse_id' => $wh->id, 'transaction_date' => now()->toDateString(), 'original_quantity' => $bags])->assertCreated();

        return Expense::whereNotNull('linked_inventory_transaction_id')->orderByDesc('id')->firstOrFail();
    }

    public function test_late_pricing_recosts_consumption_with_moving_average(): void
    {
        $acct = $this->member('accountant');
        $admin = $this->member('farm_admin');
        $flock = Flock::factory()->active()->create(['farm_id' => $this->farm->id]);
        [$item, $wh] = $this->feedItem();

        $a = $this->ship($admin, $item, $wh, 20); // 1000 كغ
        $b = $this->ship($admin, $item, $wh, 20); // 1000 كغ
        $this->as($admin)->postJson("/api/flocks/{$flock->id}/feed-logs", ['item_id' => $item->id, 'quantity' => 30])->assertCreated(); // 1500 كغ

        $this->as($acct)->patchJson("/api/accounting/review-queue/expense/{$a->id}", ['unit_price' => 25])->assertOk(); // 0.5/كغ
        $this->as($acct)->patchJson("/api/accounting/review-queue/expense/{$b->id}", ['unit_price' => 35])->assertOk(); // 0.7/كغ

        // المشتريات 1200؛ المتوسط 0.6: الاستهلاك 900 والمتبقي 500 كغ × 0.6 = 300
        $cons = InventoryTransaction::where('transaction_type', 'consumption')->firstOrFail();
        $this->assertEqualsWithDelta(900, (float) $cons->total_amount, 0.01);
        $wi = WarehouseItem::where('item_id', $item->id)->firstOrFail();
        $this->assertEqualsWithDelta(300, (float) $wi->current_quantity * (float) $wi->average_cost, 0.01);
    }

    public function test_new_flock_consumption_uses_its_own_shipment_price_not_all_time_average(): void
    {
        $acct = $this->member('accountant');
        $admin = $this->member('farm_admin');
        [$item, $wh] = $this->feedItem();

        $f1 = Flock::factory()->active()->create(['farm_id' => $this->farm->id]);
        $d1 = $this->ship($admin, $item, $wh, 20);
        $this->as($admin)->postJson("/api/flocks/{$f1->id}/feed-logs", ['item_id' => $item->id, 'quantity' => 20])->assertCreated();
        $this->as($acct)->patchJson("/api/accounting/review-queue/expense/{$d1->id}", ['unit_price' => 20])->assertOk(); // 0.4/كغ
        $f1->update(['status' => 'closed', 'close_date' => now()->toDateString()]);
        $c1Before = (float) InventoryTransaction::where('flock_id', $f1->id)->where('transaction_type', 'consumption')->value('total_amount');

        $f2 = Flock::factory()->active()->create(['farm_id' => $this->farm->id]);
        $d2 = $this->ship($admin, $item, $wh, 20);
        $this->as($admin)->postJson("/api/flocks/{$f2->id}/feed-logs", ['item_id' => $item->id, 'quantity' => 20])->assertCreated();
        $this->as($acct)->patchJson("/api/accounting/review-queue/expense/{$d2->id}", ['unit_price' => 30])->assertOk(); // 0.6/كغ

        $c2 = InventoryTransaction::where('flock_id', $f2->id)->where('transaction_type', 'consumption')->firstOrFail();
        $this->assertEqualsWithDelta(600, (float) $c2->total_amount, 0.01);
        // استهلاك الفوج المغلق لا يتغير
        $this->assertEqualsWithDelta($c1Before, (float) InventoryTransaction::where('flock_id', $f1->id)->where('transaction_type', 'consumption')->value('total_amount'), 0.001);
    }
}

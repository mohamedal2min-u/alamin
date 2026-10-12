<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\Partner;
use App\Models\PaymentAuditLog;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingProtectionTest extends TestCase
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

    private function expense(array $o = []): Expense
    {
        $flock = Flock::factory()->create(['farm_id' => $this->farm->id]);
        $cat = ExpenseCategory::firstOrCreate(['name' => 'علف', 'farm_id' => null], ['is_system' => true, 'is_active' => true]);

        return Expense::create(array_merge([
            'farm_id' => $this->farm->id, 'flock_id' => $flock->id, 'expense_category_id' => $cat->id,
            'entry_date' => now()->toDateString(), 'description' => 'علف', 'quantity' => 10, 'unit_price' => 5,
            'total_amount' => 50, 'paid_amount' => 0, 'remaining_amount' => 50, 'payment_status' => 'unpaid',
        ], $o));
    }

    public function test_payment_cannot_exceed_total_and_changes_are_logged(): void
    {
        $manager = $this->member('accountant');
        $e = $this->expense();

        $this->as($manager)->patchJson("/api/accounting/review-queue/expense/{$e->id}", ['paid_amount' => 80])->assertStatus(422);
        $this->assertSame(0.0, (float) $e->fresh()->paid_amount);

        $this->as($manager)->patchJson("/api/accounting/review-queue/expense/{$e->id}", ['paid_amount' => 20])->assertOk();
        $this->as($manager)->patchJson("/api/accounting/review-queue/expense/{$e->id}", ['paid_amount' => 35])->assertOk();

        $logs = PaymentAuditLog::orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame([0.0, 20.0], [$logs[0]->old_amount, $logs[1]->old_amount]);
        $this->assertSame([20.0, 35.0], [$logs[0]->new_amount, $logs[1]->new_amount]);

        $this->expectException(\LogicException::class);
        $logs[0]->delete();
    }

    public function test_payment_audit_endpoint_is_for_manager_and_accountant_only(): void
    {
        $this->as($this->member('farm_admin'))->getJson('/api/accounting/payment-audit')->assertOk();
        $this->as($this->member('accountant'))->getJson('/api/accounting/payment-audit')->assertOk();
        $this->as($this->member('supervisor'))->getJson('/api/accounting/payment-audit')->assertStatus(403);
        $this->as($this->member('partner'))->getJson('/api/accounting/payment-audit')->assertStatus(403);
    }

    public function test_closed_flock_chick_cost_cannot_be_changed_by_editing_count(): void
    {
        $manager = $this->member('farm_admin');
        $flock = Flock::factory()->closed()->create(['farm_id' => $this->farm->id, 'initial_count' => 1000, 'chick_unit_price' => 1, 'total_chick_cost' => 1000]);

        $this->as($manager)->putJson("/api/flocks/{$flock->id}", ['initial_count' => 5000, 'notes' => 'x'])->assertOk();

        $fresh = $flock->fresh();
        $this->assertSame(1000, (int) $fresh->initial_count);
        $this->assertSame(1000.0, (float) $fresh->total_chick_cost);
        $this->assertSame('x', $fresh->notes);
    }

    public function test_initial_count_cannot_drop_below_dead_and_sold(): void
    {
        $manager = $this->member('farm_admin');
        $flock = Flock::factory()->create(['farm_id' => $this->farm->id, 'initial_count' => 1000, 'status' => 'active']);
        \App\Models\FlockMortality::create([
            'farm_id' => $this->farm->id, 'flock_id' => $flock->id, 'entry_date' => now()->toDateString(), 'quantity' => 300,
        ]);

        $this->as($manager)->putJson("/api/flocks/{$flock->id}", ['initial_count' => 200])->assertStatus(422);
        $this->assertSame(1000, (int) $flock->fresh()->initial_count);
    }

    public function test_stock_decrease_requires_a_reason(): void
    {
        $manager = $this->member('farm_admin');
        $type = ItemType::factory()->feed()->create();
        $item = Item::factory()->forFarm($this->farm)->create(['item_type_id' => $type->id]);
        $wh = Warehouse::where('farm_id', $this->farm->id)->first() ?? Warehouse::factory()->create(['farm_id' => $this->farm->id]);
        WarehouseItem::create(['farm_id' => $this->farm->id, 'warehouse_id' => $wh->id, 'item_id' => $item->id, 'current_quantity' => 100, 'average_cost' => 0]);

        $this->as($manager)->patchJson("/api/inventory/items/{$item->id}/adjust-stock", ['quantity' => 60])->assertStatus(422);
        $this->as($manager)->patchJson("/api/inventory/items/{$item->id}/adjust-stock", ['quantity' => 60, 'notes' => 'جرد: تالف'])->assertOk();
        $this->as($manager)->patchJson("/api/inventory/items/{$item->id}/adjust-stock", ['quantity' => 90])->assertOk();
    }

    public function test_partner_cannot_see_other_partners_or_staff_reports(): void
    {
        $this->farm->update(['admin_user_id' => $this->member('farm_admin')->id]);
        $partnerUser = $this->member('partner');
        $mine = Partner::create(['farm_id' => $this->farm->id, 'user_id' => $partnerUser->id, 'name' => 'أنا', 'whatsapp' => '1', 'status' => 'active']);
        $other = Partner::create(['farm_id' => $this->farm->id, 'name' => 'غيري', 'whatsapp' => '2', 'status' => 'active']);

        $this->as($partnerUser)->getJson('/api/partners')->assertStatus(403);
        $this->as($partnerUser)->getJson("/api/partners/{$other->id}")->assertStatus(403);
        $this->as($partnerUser)->getJson("/api/partners/{$other->id}/transactions")->assertStatus(403);
        $this->as($partnerUser)->getJson("/api/partners/{$mine->id}/transactions")->assertOk();
        $this->as($partnerUser)->getJson('/api/partners/my-info')->assertOk();
        $this->as($partnerUser)->getJson('/api/reports/partners-report')->assertStatus(403);
        $this->as($partnerUser)->getJson('/api/reports/workers-report')->assertStatus(403);
    }

    public function test_sale_payment_is_logged(): void
    {
        $manager = $this->member('accountant');
        $flock = Flock::factory()->create(['farm_id' => $this->farm->id, 'initial_count' => 100, 'status' => 'active']);
        $sale = \App\Models\Sale::create([
            'farm_id' => $this->farm->id, 'flock_id' => $flock->id, 'sale_date' => now()->toDateString(),
            'gross_amount' => 100, 'discount_amount' => 0, 'net_amount' => 100, 'received_amount' => 0,
            'remaining_amount' => 100, 'payment_status' => 'debt',
        ]);

        $this->as($manager)->patchJson("/api/sales/{$sale->id}/payment", ['received_amount' => 40])->assertOk();
        $this->as($manager)->patchJson("/api/sales/{$sale->id}/payment", ['received_amount' => 140])->assertStatus(422);

        $this->assertSame(1, PaymentAuditLog::where('record_type', 'sale')->count());
    }

    public function test_pricing_an_unpriced_shipment_reprices_stock_and_prior_consumption(): void
    {
        $manager = $this->member('farm_admin');
        $accountant = $this->member('accountant');
        $this->farm->update(['admin_user_id' => $manager->id]);
        $flock = Flock::factory()->create(['farm_id' => $this->farm->id, 'status' => 'active', 'initial_count' => 1000]);
        $type = ItemType::factory()->feed()->create();
        $item = Item::factory()->forFarm($this->farm)->create(['item_type_id' => $type->id, 'unit_value' => 50, 'input_unit' => 'كيس', 'content_unit' => 'كيلو']);
        $wh = Warehouse::where('farm_id', $this->farm->id)->first();

        // المدير يضيف حمولة بلا سعر ثم يستهلك منها
        $this->as($manager)->postJson('/api/inventory/transactions', [
            'item_id' => $item->id, 'warehouse_id' => $wh->id, 'transaction_date' => now()->toDateString(), 'original_quantity' => 10,
        ])->assertCreated();
        $this->as($manager)->postJson("/api/flocks/{$flock->id}/feed-logs", ['item_id' => $item->id, 'quantity' => 2, 'entry_date' => now()->toDateString()])->assertCreated();

        $consumption = InventoryTransaction::where('transaction_type', 'consumption')->firstOrFail();
        $this->assertTrue(! $consumption->total_amount || (float) $consumption->total_amount <= 0);

        // المحاسب يسعّر الحمولة (دين الشراء): 100 للكيس
        $debt = Expense::whereNotNull('linked_inventory_transaction_id')->firstOrFail();
        $this->as($accountant)->patchJson("/api/accounting/review-queue/expense/{$debt->id}", ['unit_price' => 100])->assertOk();

        $this->assertEqualsWithDelta(2.0, (float) WarehouseItem::where('item_id', $item->id)->first()->average_cost, 0.0001); // 100 / 50 كغ
        $this->assertEqualsWithDelta(200.0, (float) $consumption->fresh()->total_amount, 0.01);                              // 2 كيس × 100
    }

    public function test_edits_notify_the_other_side_with_the_right_tone_kind(): void
    {
        $manager = $this->member('farm_admin');
        $accountant = $this->member('accountant');
        $supervisor = $this->member('supervisor');
        $this->farm->update(['admin_user_id' => $manager->id]);
        $e = $this->expense();

        // المحاسب يسعّر → المدير والمشرف يُبلَّغان بنغمة المحاسب
        $this->as($accountant)->patchJson("/api/accounting/review-queue/expense/{$e->id}", ['unit_price' => 7])->assertOk();
        $this->assertSame(1, \App\Models\UserNotification::where('user_id', $manager->id)->where('kind', 'accountant_edit')->count());
        $this->assertSame(1, \App\Models\UserNotification::where('user_id', $supervisor->id)->where('kind', 'accountant_edit')->count());
        $this->assertSame(0, \App\Models\UserNotification::where('user_id', $accountant->id)->count());

        // المدير يضيف مصروفاً → المحاسب يُبلَّغ بنغمة المدير
        $flock = Flock::factory()->create(['farm_id' => $this->farm->id, 'status' => 'active']);
        $cat = ExpenseCategory::firstOrCreate(['name' => 'علف', 'farm_id' => null], ['is_system' => true, 'is_active' => true]);
        $this->as($manager)->postJson('/api/expenses', ['flock_id' => $flock->id, 'expense_category_id' => $cat->id, 'entry_date' => now()->toDateString(), 'description' => 'اختبار', 'quantity' => 1])->assertSuccessful();
        $this->assertSame(1, \App\Models\UserNotification::where('user_id', $accountant->id)->where('kind', 'manager_edit')->count());

        // التطبيق المفتوح يستعلم عن الجديد
        $first = $this->as($manager)->getJson('/api/auth/notifications/feed')->assertOk()->json('last_id');
        $this->as($accountant)->getJson('/api/auth/notifications/feed?after_id=0')->assertOk()->assertJsonPath('data.0.kind', 'manager_edit');
        $this->assertGreaterThan(0, $first);
    }
}

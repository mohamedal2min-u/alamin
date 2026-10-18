<?php

namespace Tests\Feature\DailyEntry;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\FlockFeedLog;
use App\Models\FlockMedicine;
use App\Models\FlockMortality;
use App\Models\FlockTemperatureLog;
use App\Models\FlockWaterLog;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Warehouse;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** تعديل/إلغاء الإدخالات اليومية: الصلاحيات، عكس المخزون والتكلفة، القفل، الاستبعاد من المجاميع، والسجل */
class DailyEntryCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->farm = Farm::factory()->create();
    }

    private function member(string $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->withRole($role)->create(['farm_id' => ($farm ?? $this->farm)->id, 'user_id' => $user->id, 'status' => 'active']);

        return $user;
    }

    private function as(User $u, ?Farm $farm = null): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($u, 'sanctum')->withHeaders(['X-Farm-Id' => ($farm ?? $this->farm)->id]);
    }

    private function flock(int $initial = 1000): Flock
    {
        return Flock::factory()->active()->create(['farm_id' => $this->farm->id, 'initial_count' => $initial]);
    }

    private function mortality(User $by, Flock $flock, int $qty = 5, ?string $date = null): int
    {
        return $this->as($by)->postJson("/api/flocks/{$flock->id}/mortalities", ['entry_date' => $date ?? now()->toDateString(), 'quantity' => $qty])
            ->assertCreated()->json('data.id');
    }

    /** صنف كيسه 50 كغ */
    private function item(string $code = 'feed'): array
    {
        $type = ItemType::firstOrCreate(['code' => $code], ['name' => $code, 'is_system' => true, 'is_active' => true]);
        $item = Item::factory()->create(['farm_id' => $this->farm->id, 'item_type_id' => $type->id, 'unit_value' => 50, 'status' => 'active']);

        return [$item, Warehouse::where('farm_id', $this->farm->id)->firstOrFail()];
    }

    /** شحنة يسعّرها المحاسب بسعر الكيس */
    private function pricedShipment(User $admin, User $acct, Item $item, Warehouse $wh, int $bags, float $pricePerBag): void
    {
        $this->as($admin)->postJson('/api/inventory/transactions', ['item_id' => $item->id, 'warehouse_id' => $wh->id, 'transaction_date' => now()->toDateString(), 'original_quantity' => $bags])->assertCreated();
        $exp = Expense::whereNotNull('linked_inventory_transaction_id')->orderByDesc('id')->firstOrFail();
        $this->as($acct)->patchJson("/api/accounting/review-queue/expense/{$exp->id}", ['unit_price' => $pricePerBag])->assertOk();
    }

    private function stock(Item $item): float
    {
        return (float) WarehouseItem::where('item_id', $item->id)->value('current_quantity');
    }

    // ─── الصلاحيات ────────────────────────────────────────────────────────────

    public function test_supervisor_edits_own_entry_within_24_hours_only(): void
    {
        $sup = $this->member('supervisor');
        $flock = $this->flock();
        $id = $this->mortality($sup, $flock, 5);

        $this->as($sup)->patchJson("/api/daily-entries/mortality/{$id}", ['quantity' => 3, 'edit_reason' => 'خطأ إدخال'])->assertOk();
        $this->assertSame(3, (int) FlockMortality::find($id)->quantity);

        $this->travel(25)->hours();
        $this->as($sup)->patchJson("/api/daily-entries/mortality/{$id}", ['quantity' => 2])->assertForbidden();
        $this->as($sup)->postJson("/api/daily-entries/mortality/{$id}/void", ['reason' => 'مكرر'])->assertForbidden();
        $this->assertSame(3, (int) FlockMortality::find($id)->quantity);
    }

    public function test_supervisor_cannot_edit_another_users_entry(): void
    {
        $sup = $this->member('supervisor');
        $other = $this->member('supervisor');
        $flock = $this->flock();
        $id = $this->mortality($other, $flock, 5);

        $this->as($sup)->patchJson("/api/daily-entries/mortality/{$id}", ['quantity' => 3])->assertForbidden();
        $this->as($sup)->postJson("/api/daily-entries/mortality/{$id}/void", ['reason' => 'مكرر'])->assertForbidden();
    }

    public function test_farm_admin_can_edit_old_entries_of_anyone(): void
    {
        $admin = $this->member('farm_admin');
        $sup = $this->member('supervisor');
        $flock = $this->flock();
        $id = $this->mortality($sup, $flock, 5);

        $this->travel(5)->days();
        $this->as($admin)->patchJson("/api/daily-entries/mortality/{$id}", ['quantity' => 4])->assertOk();
        $this->as($admin)->postJson("/api/daily-entries/mortality/{$id}/void", ['reason' => 'تسجيل مكرر'])->assertOk();
        $this->assertSoftDeleted('flock_mortalities', ['id' => $id]);
    }

    public function test_accountant_and_partner_cannot_edit_quantities(): void
    {
        $sup = $this->member('supervisor');
        $flock = $this->flock();
        $id = $this->mortality($sup, $flock, 5);

        foreach (['accountant', 'partner'] as $role) {
            $u = $this->member($role);
            $this->as($u)->patchJson("/api/daily-entries/mortality/{$id}", ['quantity' => 1])->assertForbidden();
            $this->as($u)->postJson("/api/daily-entries/mortality/{$id}/void", ['reason' => 'سبب'])->assertForbidden();
        }
        $this->assertSame(5, (int) FlockMortality::find($id)->quantity);
    }

    public function test_closed_flock_entries_are_locked_for_everyone(): void
    {
        $admin = $this->member('farm_admin');
        $flock = $this->flock();
        $id = $this->mortality($admin, $flock, 5);
        $flock->update(['status' => 'closed', 'close_date' => now()->toDateString()]);

        $this->as($admin)->patchJson("/api/daily-entries/mortality/{$id}", ['quantity' => 1])->assertStatus(422);
        $this->as($admin)->postJson("/api/daily-entries/mortality/{$id}/void", ['reason' => 'سبب'])->assertStatus(422);
        $this->assertNull(FlockMortality::find($id)->deleted_at);
    }

    public function test_void_requires_reason_and_other_farm_cannot_touch_entries(): void
    {
        $admin = $this->member('farm_admin');
        $flock = $this->flock();
        $id = $this->mortality($admin, $flock, 5);

        $this->as($admin)->postJson("/api/daily-entries/mortality/{$id}/void", [])->assertStatus(422)->assertJsonValidationErrors('reason');

        $otherFarm = Farm::factory()->create();
        $intruder = $this->member('farm_admin', $otherFarm);
        $this->as($intruder, $otherFarm)->patchJson("/api/daily-entries/mortality/{$id}", ['quantity' => 1])->assertNotFound();
        $this->as($intruder, $otherFarm)->postJson("/api/daily-entries/mortality/{$id}/void", ['reason' => 'سبب'])->assertNotFound();
        $this->assertSame(5, (int) FlockMortality::find($id)->quantity);
    }

    // ─── النفوق ───────────────────────────────────────────────────────────────

    public function test_mortality_edit_keeps_initial_count_rule(): void
    {
        $admin = $this->member('farm_admin');
        $flock = $this->flock(100);
        $this->mortality($admin, $flock, 30);
        $id = $this->mortality($admin, $flock, 10);

        $this->as($admin)->patchJson("/api/daily-entries/mortality/{$id}", ['quantity' => 71])->assertStatus(422);
        $this->as($admin)->patchJson("/api/daily-entries/mortality/{$id}", ['quantity' => 70])->assertOk();
    }

    public function test_voided_mortality_is_excluded_from_totals(): void
    {
        $admin = $this->member('farm_admin');
        $flock = $this->flock(1000);
        $yesterday = now()->subDay()->toDateString();
        $this->mortality($admin, $flock, 7, $yesterday);
        $id = $this->mortality($admin, $flock, 50, $yesterday);

        $this->as($admin)->postJson("/api/daily-entries/mortality/{$id}/void", ['reason' => 'خطأ في العدد'])->assertOk();

        $summary = $this->as($admin)->getJson("/api/flocks/{$flock->id}/today-summary?date={$yesterday}")->assertOk()->json('data');
        $this->assertSame(7, $summary['mortalities']['total']);
        $this->assertCount(1, $summary['mortalities']['entries']);
        $this->assertSame(993, $summary['flock_info']['remaining_count']);
        $this->assertSame(993, $flock->fresh()->computeRemainingCount());
        $this->as($admin)->getJson("/api/flocks/{$flock->id}")->assertOk()
            ->assertJsonPath('data.total_mortality', 7)->assertJsonPath('data.remaining_count', 993);

        $history = $this->as($admin)->getJson("/api/flocks/{$flock->id}/history")->assertOk()->json('data.1');
        $this->assertSame($yesterday, $history['date']);
        $this->assertSame(7, $history['stats']['mortality']);
        $this->assertCount(1, $history['timeline']);

        // باقٍ في قاعدة البيانات مع السبب ومن ألغاه
        $row = FlockMortality::withTrashed()->find($id);
        $this->assertSame('خطأ في العدد', $row->void_reason);
        $this->assertSame($admin->id, (int) $row->voided_by);
    }

    public function test_zero_date_ignores_voided_mortality(): void
    {
        $admin = $this->member('farm_admin');
        $flock = $this->flock(50);
        $all = $this->mortality($admin, $flock, 50);
        $this->assertNotNull((new \App\Actions\Flock\ResolveFlockZeroDateAction)->execute($flock));

        $this->as($admin)->postJson("/api/daily-entries/mortality/{$all}/void", ['reason' => 'خطأ'])->assertOk();
        $this->assertNull((new \App\Actions\Flock\ResolveFlockZeroDateAction)->execute($flock));
    }

    // ─── العلف والدواء: المخزون والتكلفة ──────────────────────────────────────

    public function test_voiding_feed_log_reverses_stock_and_cost(): void
    {
        $admin = $this->member('farm_admin');
        $acct = $this->member('accountant');
        $flock = $this->flock();
        [$item, $wh] = $this->item();
        $this->pricedShipment($admin, $acct, $item, $wh, 20, 25); // 1000 كغ × 0.5

        $id = $this->as($admin)->postJson("/api/flocks/{$flock->id}/feed-logs", ['item_id' => $item->id, 'quantity' => 4])->assertCreated()->json('data.id');
        $this->assertEqualsWithDelta(800, $this->stock($item), 0.001);
        $txnId = FlockFeedLog::find($id)->inventory_transaction_id;
        $this->assertEqualsWithDelta(100, (float) InventoryTransaction::find($txnId)->total_amount, 0.01);

        $this->as($admin)->postJson("/api/daily-entries/feed_log/{$id}/void", ['reason' => 'أكياس خاطئة'])->assertOk();

        $this->assertEqualsWithDelta(1000, $this->stock($item), 0.001);
        $txn = InventoryTransaction::find($txnId);
        $this->assertEqualsWithDelta(0, (float) $txn->computed_quantity, 0.0001);
        $this->assertEqualsWithDelta(0, (float) $txn->total_amount, 0.0001);
        $this->assertSoftDeleted('flock_feed_logs', ['id' => $id]);
        $this->assertEqualsWithDelta(0.5, (float) WarehouseItem::where('item_id', $item->id)->value('average_cost'), 0.000001);

        $show = $this->as($admin)->getJson("/api/flocks/{$flock->id}/today-summary")->assertOk()->json('data');
        $this->assertSame(0, (int) $show['feed']['total']);
    }

    public function test_editing_feed_log_quantity_adjusts_stock_and_cost(): void
    {
        $admin = $this->member('farm_admin');
        $acct = $this->member('accountant');
        $flock = $this->flock();
        [$item, $wh] = $this->item();
        $this->pricedShipment($admin, $acct, $item, $wh, 20, 25);

        $id = $this->as($admin)->postJson("/api/flocks/{$flock->id}/feed-logs", ['item_id' => $item->id, 'quantity' => 4])->assertCreated()->json('data.id');
        $this->as($admin)->patchJson("/api/daily-entries/feed_log/{$id}", ['quantity' => 2])->assertOk();

        $this->assertEqualsWithDelta(900, $this->stock($item), 0.001);
        $txn = InventoryTransaction::find(FlockFeedLog::find($id)->inventory_transaction_id);
        $this->assertEqualsWithDelta(2, (float) $txn->original_quantity, 0.0001);
        $this->assertEqualsWithDelta(100, (float) $txn->computed_quantity, 0.0001);
        $this->assertEqualsWithDelta(50, (float) $txn->total_amount, 0.01);
        $this->assertEquals(2, (float) FlockFeedLog::find($id)->quantity);

        // الزيادة فوق المتوفر تُرفض ولا يتغير شيء
        $this->as($admin)->patchJson("/api/daily-entries/feed_log/{$id}", ['quantity' => 30])->assertStatus(422);
        $this->assertEqualsWithDelta(900, $this->stock($item), 0.001);

        // الزيادة ضمن المتوفر تُخصم من المستودع
        $this->as($admin)->patchJson("/api/daily-entries/feed_log/{$id}", ['quantity' => 6])->assertOk();
        $this->assertEqualsWithDelta(700, $this->stock($item), 0.001);
        $this->assertEqualsWithDelta(150, (float) $txn->fresh()->total_amount, 0.01);
    }

    public function test_feed_auto_purchase_debt_is_reversed_on_void_and_shrunk_on_edit(): void
    {
        $admin = $this->member('farm_admin');
        $acct = $this->member('accountant');
        $flock = $this->flock();
        [$item, $wh] = $this->item();
        $this->pricedShipment($admin, $acct, $item, $wh, 2, 25); // 100 كغ فقط

        // 10 أكياس (500 كغ) والمتوفر 100: شراء تلقائي 400 كغ (8 أكياس × 25 = 200) دَيناً
        $a = $this->as($admin)->postJson("/api/flocks/{$flock->id}/feed-logs", ['item_id' => $item->id, 'quantity' => 10])->assertCreated()->json('data.id');
        $purchaseId = FlockFeedLog::find($a)->auto_purchase_transaction_id;
        $this->assertNotNull($purchaseId);
        $debt = Expense::where('linked_inventory_transaction_id', $purchaseId)->firstOrFail();
        $this->assertEqualsWithDelta(200, (float) $debt->total_amount, 0.01);
        $this->assertEqualsWithDelta(0, $this->stock($item), 0.001);

        // تعديل إلى 5 أكياس (250 كغ): الشراء يصبح 150 كغ (3 أكياس = 75) والمستودع 0
        $this->as($admin)->patchJson("/api/daily-entries/feed_log/{$a}", ['quantity' => 5])->assertOk();
        $purchase = InventoryTransaction::find($purchaseId);
        $this->assertEqualsWithDelta(150, (float) $purchase->computed_quantity, 0.001);
        $this->assertEqualsWithDelta(75, (float) $purchase->total_amount, 0.01);
        $debt->refresh();
        $this->assertEqualsWithDelta(3, (float) $debt->quantity, 0.0001);
        $this->assertEqualsWithDelta(75, (float) $debt->total_amount, 0.01);
        $this->assertEqualsWithDelta(75, (float) $debt->remaining_amount, 0.01);
        $this->assertEqualsWithDelta(0, $this->stock($item), 0.001);

        // الإلغاء: الشراء التلقائي ودَينه يُحذفان والمستودع يعود إلى 100 كغ
        $this->as($admin)->postJson("/api/daily-entries/feed_log/{$a}/void", ['reason' => 'رقم خاطئ'])->assertOk();
        $this->assertNull(InventoryTransaction::find($purchaseId));
        $this->assertNull(Expense::find($debt->id));
        $this->assertEqualsWithDelta(100, $this->stock($item), 0.001);
        $this->assertEqualsWithDelta(0.5, (float) WarehouseItem::where('item_id', $item->id)->value('average_cost'), 0.000001);
    }

    public function test_legacy_feed_log_without_link_still_finds_its_auto_purchase(): void
    {
        $admin = $this->member('farm_admin');
        $acct = $this->member('accountant');
        $flock = $this->flock();
        [$item, $wh] = $this->item();
        $this->pricedShipment($admin, $acct, $item, $wh, 2, 25);

        $a = $this->as($admin)->postJson("/api/flocks/{$flock->id}/feed-logs", ['item_id' => $item->id, 'quantity' => 10])->assertCreated()->json('data.id');
        $purchaseId = FlockFeedLog::find($a)->auto_purchase_transaction_id;
        FlockFeedLog::whereKey($a)->update(['auto_purchase_transaction_id' => null]); // سجل أُنشئ قبل العمود

        $this->as($admin)->postJson("/api/daily-entries/feed_log/{$a}/void", ['reason' => 'خطأ'])->assertOk();
        $this->assertNull(InventoryTransaction::find($purchaseId));
        $this->assertEqualsWithDelta(100, $this->stock($item), 0.001);
    }

    public function test_feed_correction_blocked_when_auto_purchase_debt_is_paid(): void
    {
        $admin = $this->member('farm_admin');
        $acct = $this->member('accountant');
        $flock = $this->flock();
        [$item, $wh] = $this->item();
        $this->pricedShipment($admin, $acct, $item, $wh, 2, 25);

        $a = $this->as($admin)->postJson("/api/flocks/{$flock->id}/feed-logs", ['item_id' => $item->id, 'quantity' => 10])->assertCreated()->json('data.id');
        $debt = Expense::where('linked_inventory_transaction_id', FlockFeedLog::find($a)->auto_purchase_transaction_id)->firstOrFail();
        $this->as($acct)->patchJson("/api/accounting/review-queue/expense/{$debt->id}", ['paid_amount' => 50])->assertOk();

        $this->as($admin)->postJson("/api/daily-entries/feed_log/{$a}/void", ['reason' => 'خطأ'])->assertStatus(422)
            ->assertJsonFragment(['message' => 'هذا السجل أنشأ دين شراء علف تلقائياً (لنقص المخزون) وقد سعّره المحاسب أو دُفع منه. اطلب من المحاسب مراجعة الدين قبل التعديل أو الإلغاء']);
        $this->as($admin)->patchJson("/api/daily-entries/feed_log/{$a}", ['quantity' => 5])->assertStatus(422);

        $this->assertNull(FlockFeedLog::find($a)->deleted_at);
        $this->assertEqualsWithDelta(200, (float) $debt->fresh()->total_amount, 0.01);
        $this->assertEqualsWithDelta(0, $this->stock($item), 0.001);
    }

    public function test_voiding_medicine_log_restores_stock(): void
    {
        $admin = $this->member('farm_admin');
        $acct = $this->member('accountant');
        $flock = $this->flock();
        [$item, $wh] = $this->item('medicine');
        $this->pricedShipment($admin, $acct, $item, $wh, 4, 100); // 200 وحدة × 2

        $id = $this->as($admin)->postJson("/api/flocks/{$flock->id}/medicine-logs", ['item_id' => $item->id, 'quantity' => 1])->assertCreated()->json('data.id');
        $this->assertEqualsWithDelta(150, $this->stock($item), 0.001);

        $this->as($admin)->postJson("/api/daily-entries/medicine_log/{$id}/void", ['reason' => 'لم يُعطَ'])->assertOk();
        $this->assertEqualsWithDelta(200, $this->stock($item), 0.001);
        $this->assertSoftDeleted('flock_medicines', ['id' => $id]);
        $this->assertSame(0, FlockMedicine::where('flock_id', $flock->id)->count());
    }

    // ─── الماء ────────────────────────────────────────────────────────────────

    public function test_priced_water_log_edit_recomputes_total_and_respects_paid(): void
    {
        $admin = $this->member('farm_admin');
        $acct = $this->member('accountant');
        $flock = $this->flock();
        $id = $this->as($admin)->postJson("/api/flocks/{$flock->id}/water-logs", ['quantity' => 2, 'entry_date' => now()->toDateString()])->assertCreated()->json('data.id');
        $this->as($acct)->patchJson("/api/accounting/review-queue/water_log/{$id}", ['unit_price' => 10, 'paid_amount' => 15])->assertOk();

        $this->as($admin)->patchJson("/api/daily-entries/water_log/{$id}", ['quantity' => 1])->assertStatus(422);
        $this->assertEqualsWithDelta(20, (float) FlockWaterLog::find($id)->total_amount, 0.01);

        $this->as($admin)->patchJson("/api/daily-entries/water_log/{$id}", ['quantity' => 3])->assertOk();
        $w = FlockWaterLog::find($id);
        $this->assertEqualsWithDelta(30, (float) $w->total_amount, 0.01);
        $this->assertSame('partial', $w->payment_status);

        // سجل مدفوع لا يُلغى
        $this->as($admin)->postJson("/api/daily-entries/water_log/{$id}/void", ['reason' => 'خطأ'])->assertStatus(422);
    }

    public function test_voided_water_log_excluded_from_flock_cost(): void
    {
        $admin = $this->member('farm_admin');
        $acct = $this->member('accountant');
        $flock = $this->flock();
        $id = $this->as($admin)->postJson("/api/flocks/{$flock->id}/water-logs", ['quantity' => 2, 'entry_date' => now()->toDateString()])->assertCreated()->json('data.id');
        $this->as($acct)->patchJson("/api/accounting/review-queue/water_log/{$id}", ['unit_price' => 10])->assertOk();

        $this->as($admin)->postJson("/api/daily-entries/water_log/{$id}/void", ['reason' => 'مكرر'])->assertOk();

        $this->assertEquals(0, (float) app(\App\Actions\Flock\ShowFlockAction::class)->execute($this->farm->id, $flock->id)->water_logs_sum_total_amount);
        $queue = $this->as($acct)->getJson('/api/accounting/review-queue?type=water_log')->assertOk()->json('data');
        $this->assertCount(0, collect($queue)->where('record_id', $id));
    }

    // ─── الملاحظات والحرارة ───────────────────────────────────────────────────

    public function test_note_and_temperature_edit_and_void(): void
    {
        $sup = $this->member('supervisor');
        $flock = $this->flock();
        $noteId = $this->as($sup)->postJson("/api/flocks/{$flock->id}/notes", ['note_text' => 'نص قديم'])->assertCreated()->json('data.id');
        $this->as($sup)->patchJson("/api/daily-entries/note/{$noteId}", ['note_text' => 'نص مصحح'])->assertOk();
        $this->assertSame('نص مصحح', \App\Models\FlockNote::find($noteId)->note_text);

        $payload = ['log_date' => now()->toDateString(), 'time_of_day' => 'morning', 'temperature' => 30];
        $tId = $this->as($sup)->postJson("/api/flocks/{$flock->id}/temperature-logs", $payload)->assertCreated()->json('data.id');
        $this->as($sup)->patchJson("/api/daily-entries/temperature_log/{$tId}", ['temperature' => 31.5])->assertOk();
        $this->assertEquals(31.5, (float) FlockTemperatureLog::find($tId)->temperature);

        $this->as($sup)->postJson("/api/daily-entries/temperature_log/{$tId}/void", ['reason' => 'مقياس معطل'])->assertOk();
        $this->assertSoftDeleted('flock_temperature_logs', ['id' => $tId]);

        // إعادة إدخال الفترة نفسها بعد الإلغاء لا تصطدم بالفهرس الفريد
        $this->as($sup)->postJson("/api/flocks/{$flock->id}/temperature-logs", ['temperature' => 29] + $payload)->assertCreated();
        $this->assertEquals(29, (float) FlockTemperatureLog::where('flock_id', $flock->id)->value('temperature'));
    }

    // ─── سجل النشاطات والأعلام والإشعار ───────────────────────────────────────

    public function test_activity_log_rows_are_written_and_listed_for_farm_admin_only(): void
    {
        $admin = $this->member('farm_admin');
        $sup = $this->member('supervisor');
        $flock = $this->flock();
        $id = $this->mortality($sup, $flock, 5);
        $this->as($sup)->patchJson("/api/daily-entries/mortality/{$id}", ['quantity' => 4, 'edit_reason' => 'عدّ خاطئ'])->assertOk();
        $this->as($admin)->postJson("/api/daily-entries/mortality/{$id}/void", ['reason' => 'مكرر'])->assertOk();

        $logs = ActivityLog::where('subject_type', 'mortality')->where('subject_id', $id)->orderBy('id')->get();
        $this->assertSame(['created', 'updated', 'voided'], $logs->pluck('action')->all());
        $this->assertSame(5.0, (float) $logs[1]->before['quantity']);
        $this->assertSame(4.0, (float) $logs[1]->after['quantity']);
        $this->assertSame('عدّ خاطئ', $logs[1]->reason);
        $this->assertSame('مكرر', $logs[2]->reason);
        $this->assertSame($admin->id, (int) $logs[2]->user_id);
        $this->assertSame($flock->id, (int) $logs[2]->flock_id);

        $res = $this->as($admin)->getJson('/api/activity-logs?subject_type=mortality&user_id='.$admin->id)->assertOk();
        $this->assertSame(1, $res->json('meta.total'));
        $this->assertSame('voided', $res->json('data.0.action'));
        $this->as($admin)->getJson("/api/activity-logs?flock_id={$flock->id}")->assertOk()->assertJsonPath('meta.total', 3);

        $this->as($sup)->getJson('/api/activity-logs')->assertForbidden();
        $this->as($this->member('accountant'))->getJson('/api/activity-logs')->assertForbidden();

        // مزرعة أخرى لا ترى سجلات هذه المزرعة
        $otherFarm = Farm::factory()->create();
        $otherAdmin = $this->member('farm_admin', $otherFarm);
        $this->as($otherAdmin, $otherFarm)->getJson('/api/activity-logs')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_today_summary_and_history_expose_permission_flags(): void
    {
        $sup = $this->member('supervisor');
        $acct = $this->member('accountant');
        $flock = $this->flock();
        $date = now()->subDay()->toDateString();
        $id = $this->mortality($sup, $flock, 5, $date);

        $entry = $this->as($sup)->getJson("/api/flocks/{$flock->id}/today-summary?date={$date}")->assertOk()->json('data.mortalities.entries.0');
        $this->assertSame($id, $entry['id']);
        $this->assertSame('mortality', $entry['entry_type']);
        $this->assertTrue($entry['can_edit']);
        $this->assertTrue($entry['can_void']);

        $entry = $this->as($acct)->getJson("/api/flocks/{$flock->id}/today-summary?date={$date}")->assertOk()->json('data.mortalities.entries.0');
        $this->assertFalse($entry['can_edit']);

        $item = $this->as($sup)->getJson("/api/flocks/{$flock->id}/history")->assertOk()->json('data.1.timeline.0');
        $this->assertSame('mortality', $item['entry_type']);
        $this->assertSame($id, $item['id']);
        $this->assertTrue($item['can_edit']);
        $this->assertSame(5, $item['values']['quantity']);

        $this->travel(25)->hours();
        $entry = $this->as($sup)->getJson("/api/flocks/{$flock->id}/today-summary?date={$date}")
            ->assertOk()->json('data.mortalities.entries.0');
        $this->assertFalse($entry['can_edit']);
    }

    public function test_corrections_notify_other_members(): void
    {
        $admin = $this->member('farm_admin');
        $sup = $this->member('supervisor');
        $acct = $this->member('accountant');
        $partner = $this->member('partner');
        $flock = $this->flock();
        $id = $this->mortality($admin, $flock, 5);

        $this->as($admin)->postJson("/api/daily-entries/mortality/{$id}/void", ['reason' => 'مكرر'])->assertOk();

        foreach ([$sup, $acct, $partner] as $u) {
            $this->assertSame(1, UserNotification::where('user_id', $u->id)->where('body', 'like', '%ألغى سجلاً يومياً%')->count());
        }
        $this->assertSame(0, UserNotification::where('user_id', $admin->id)->count());
    }
}

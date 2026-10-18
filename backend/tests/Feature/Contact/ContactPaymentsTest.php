<?php

namespace Tests\Feature\Contact;

use App\Models\Contact;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\FlockWaterLog;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Services\PaymentLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContactPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;
    private User $admin;
    private Farm $farm;
    private Flock $flock;
    private Contact $supplier;
    private Contact $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->farm = Farm::factory()->create();
        $this->flock = Flock::factory()->active()->create(['farm_id' => $this->farm->id]);

        $this->accountant = User::factory()->create();
        FarmUser::factory()->withRole('accountant')->create(['farm_id' => $this->farm->id, 'user_id' => $this->accountant->id]);

        $this->admin = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $this->farm->id, 'user_id' => $this->admin->id]);

        $this->supplier = Contact::create(['farm_id' => $this->farm->id, 'name' => 'شركة العلف', 'type' => 'supplier']);
        $this->buyer = Contact::create(['farm_id' => $this->farm->id, 'name' => 'أبو أحمد', 'type' => 'buyer']);
    }

    private function as(User $user)
    {
        return $this->actingAs($user, 'sanctum')->withHeaders(['X-Farm-Id' => $this->farm->id]);
    }

    private function expense(array $attrs = []): Expense
    {
        return Expense::factory()->create(array_merge([
            'farm_id' => $this->farm->id,
            'flock_id' => $this->flock->id,
            'contact_id' => $this->supplier->id,
            'entry_date' => now()->toDateString(),
            'quantity' => 10,
            'unit_price' => 100,
            'total_amount' => 1000,
            'paid_amount' => 0,
            'remaining_amount' => 1000,
            'payment_status' => 'unpaid',
        ], $attrs));
    }

    private function sale(array $attrs = []): Sale
    {
        return Sale::factory()->create(array_merge([
            'farm_id' => $this->farm->id,
            'flock_id' => $this->flock->id,
            'contact_id' => $this->buyer->id,
            'gross_amount' => 2000,
            'net_amount' => 2000,
            'received_amount' => 0,
            'remaining_amount' => 2000,
            'payment_status' => 'debt',
        ], $attrs));
    }

    private function pay(User $user, string $type, int $id, float $amount, string $date = null, string $method = 'cash')
    {
        return $this->as($user)->postJson('/api/accounting/payments', [
            'payable_type' => $type,
            'payable_id' => $id,
            'amount' => $amount,
            'paid_at' => $date ?? now()->toDateString(),
            'method' => $method,
            'note' => 'دفعة',
        ]);
    }

    // ─── Payment math ───────────────────────────────────────────────────────

    public function test_payments_accumulate_and_update_record_consistently(): void
    {
        $expense = $this->expense();

        $this->pay($this->accountant, 'expense', $expense->id, 300, now()->subDays(3)->toDateString(), 'transfer')
            ->assertCreated()
            ->assertJsonPath('data.paid_amount', 300)
            ->assertJsonPath('data.remaining_amount', 700)
            ->assertJsonPath('data.payment_status', 'partial');

        $res = $this->pay($this->accountant, 'expense', $expense->id, 700)->assertCreated();
        $this->assertSame('paid', $res->json('data.payment_status'));
        $this->assertCount(2, $res->json('data.payments'));
        $this->assertSame('transfer', $res->json('data.payments.0.method'));
        $this->assertSame(now()->subDays(3)->toDateString(), $res->json('data.payments.0.paid_at'));

        $expense->refresh();
        $this->assertEquals(1000, (float) $expense->paid_amount);
        $this->assertEquals(0, (float) $expense->remaining_amount);
        $this->assertEquals(1000, (float) Payment::where('payable_type', 'expense')->where('payable_id', $expense->id)->sum('amount'));
        $this->assertSame($this->supplier->id, Payment::where('payable_id', $expense->id)->value('contact_id'));
    }

    public function test_sale_payment_uses_received_amount_and_debt_status(): void
    {
        $sale = $this->sale();

        $this->pay($this->accountant, 'sale', $sale->id, 500)
            ->assertCreated()
            ->assertJsonPath('data.payment_status', 'partial');

        $sale->refresh();
        $this->assertEquals(500, (float) $sale->received_amount);
        $this->assertEquals(1500, (float) $sale->remaining_amount);
    }

    public function test_water_log_payment(): void
    {
        $log = FlockWaterLog::factory()->create([
            'farm_id' => $this->farm->id, 'flock_id' => $this->flock->id, 'contact_id' => $this->supplier->id,
            'quantity' => 2, 'total_amount' => 100, 'paid_amount' => 0, 'payment_status' => 'unpaid',
        ]);

        $this->pay($this->accountant, 'water_log', $log->id, 100)->assertCreated()->assertJsonPath('data.payment_status', 'paid');
        $this->assertEquals(100, (float) $log->fresh()->paid_amount);
    }

    public function test_overpayment_is_rejected_without_writing(): void
    {
        $expense = $this->expense(['paid_amount' => 900, 'remaining_amount' => 100, 'payment_status' => 'partial']);
        $before = Payment::count();

        $this->pay($this->accountant, 'expense', $expense->id, 100.01)->assertStatus(422);

        $this->assertSame($before, Payment::count());
        $this->assertEquals(900, (float) $expense->fresh()->paid_amount);
    }

    public function test_invalid_payment_input_is_rejected(): void
    {
        $expense = $this->expense();

        $this->pay($this->accountant, 'expense', $expense->id, 0)->assertStatus(422);
        $this->pay($this->accountant, 'expense', $expense->id, 10, now()->addDay()->toDateString())->assertStatus(422);
        $this->pay($this->accountant, 'expense', $expense->id, 10, null, 'cheque')->assertStatus(422);
    }

    public function test_closed_flock_is_locked(): void
    {
        $closed = Flock::factory()->closed()->create(['farm_id' => $this->farm->id]);
        $expense = $this->expense(['flock_id' => $closed->id]);

        $this->pay($this->accountant, 'expense', $expense->id, 100)->assertStatus(403);
        $this->assertEquals(0, (float) $expense->fresh()->paid_amount);
    }

    public function test_only_accountant_records_payments(): void
    {
        $expense = $this->expense();

        $this->pay($this->admin, 'expense', $expense->id, 100)->assertStatus(403);
        $this->assertSame(0, Payment::where('payable_id', $expense->id)->count());
    }

    public function test_review_queue_patch_records_difference_as_payment_row(): void
    {
        $expense = $this->expense();

        $this->as($this->accountant)->patchJson("/api/accounting/review-queue/expense/{$expense->id}", ['paid_amount' => 600])->assertOk();
        $res = $this->as($this->accountant)->patchJson("/api/accounting/review-queue/expense/{$expense->id}", ['paid_amount' => 800])->assertOk();

        $amounts = Payment::where('payable_type', 'expense')->where('payable_id', $expense->id)->orderBy('id')->pluck('amount')->all();
        $this->assertEquals([600, 200], $amounts);
        $this->assertCount(2, $res->json('payments'));
        $this->assertSame('partial', $res->json('payment_status'));
    }

    public function test_sale_payment_patch_records_difference(): void
    {
        $sale = $this->sale();

        $this->as($this->accountant)->patchJson("/api/sales/{$sale->id}/payment", ['received_amount' => 1200])->assertOk();

        $this->assertEquals(1200, (float) Payment::where('payable_type', 'sale')->where('payable_id', $sale->id)->sum('amount'));
    }

    public function test_paid_amount_at_creation_becomes_payment_dated_at_entry(): void
    {
        $expense = $this->expense(['entry_date' => '2026-01-05', 'paid_amount' => 250, 'remaining_amount' => 750, 'payment_status' => 'partial']);

        $p = Payment::where('payable_type', 'expense')->where('payable_id', $expense->id)->sole();
        $this->assertEquals(250, $p->amount);
        $this->assertSame('2026-01-05', $p->paid_at->toDateString());
    }

    // ─── Statement & balances ───────────────────────────────────────────────

    public function test_statement_running_balance(): void
    {
        $e1 = $this->expense(['entry_date' => '2026-09-01', 'total_amount' => 1000, 'remaining_amount' => 1000]);
        $e2 = $this->expense(['entry_date' => '2026-09-10', 'total_amount' => 500, 'remaining_amount' => 500]);
        $this->pay($this->accountant, 'expense', $e1->id, 400, '2026-09-05')->assertCreated();
        $this->pay($this->accountant, 'expense', $e2->id, 500, '2026-09-12')->assertCreated();

        $res = $this->as($this->accountant)->getJson("/api/contacts/{$this->supplier->id}/statement")->assertOk();

        $entries = $res->json('entries');
        $this->assertSame(['invoice', 'payment', 'invoice', 'payment'], array_column($entries, 'kind'));
        $this->assertEquals([1000, 600, 1100, 600], array_column($entries, 'running_payable'));
        $this->assertEquals(600, $res->json('closing.payable'));
        $this->assertEquals(1500, $res->json('totals.payable.invoiced'));
        $this->assertEquals(900, $res->json('totals.payable.paid'));

        // نطاق تاريخ: الرصيد الافتتاحي يشمل ما قبله
        $ranged = $this->as($this->accountant)->getJson("/api/contacts/{$this->supplier->id}/statement?from=2026-09-06")->assertOk();
        $this->assertEquals(600, $ranged->json('opening.payable'));
        $this->assertCount(2, $ranged->json('entries'));
        $this->assertEquals(600, $ranged->json('closing.payable'));
    }

    public function test_buyer_statement_tracks_receivable(): void
    {
        $sale = $this->sale(['sale_date' => '2026-09-01']);
        $this->pay($this->accountant, 'sale', $sale->id, 1500, '2026-09-20')->assertCreated();

        $res = $this->as($this->accountant)->getJson("/api/contacts/{$this->buyer->id}/statement")->assertOk();

        $this->assertEquals([2000, 500], array_column($res->json('entries'), 'running_receivable'));
        $this->assertEquals(500, $res->json('closing.net'));
    }

    public function test_balances_with_aging_buckets(): void
    {
        $this->expense(['entry_date' => now()->subDays(10)->toDateString(), 'total_amount' => 100, 'remaining_amount' => 100]);
        $this->expense(['entry_date' => now()->subDays(45)->toDateString(), 'total_amount' => 200, 'remaining_amount' => 200]);
        $this->expense(['entry_date' => now()->subDays(90)->toDateString(), 'total_amount' => 300, 'paid_amount' => 50, 'remaining_amount' => 250, 'payment_status' => 'partial']);
        $this->sale(['sale_date' => now()->subDays(5)->toDateString()]);

        $res = $this->as($this->accountant)->getJson('/api/contacts/balances')->assertOk();
        $rows = collect($res->json('data'))->keyBy('id');

        $sup = $rows[$this->supplier->id]['payable'];
        $this->assertEquals(550, $sup['balance']);
        $this->assertEquals(['d0_30' => 100, 'd31_60' => 200, 'd60_plus' => 250], $sup['aging']);
        $this->assertEquals(2000, $rows[$this->buyer->id]['receivable']['balance']);
        $this->assertEquals(550, $res->json('summary.payable_balance'));
        $this->assertEquals(2000, $res->json('summary.receivable_balance'));
    }

    // ─── Farm isolation & permissions ──────────────────────────────────────

    public function test_farm_isolation(): void
    {
        $otherFarm = Farm::factory()->create();
        $otherFlock = Flock::factory()->active()->create(['farm_id' => $otherFarm->id]);
        $otherContact = Contact::create(['farm_id' => $otherFarm->id, 'name' => 'غريب', 'type' => 'buyer']);
        $otherExpense = Expense::factory()->create([
            'farm_id' => $otherFarm->id, 'flock_id' => $otherFlock->id, 'total_amount' => 100,
            'paid_amount' => 0, 'remaining_amount' => 100, 'payment_status' => 'unpaid', 'quantity' => 1, 'unit_price' => 100,
        ]);

        $this->as($this->accountant)->getJson("/api/contacts/{$otherContact->id}/statement")->assertNotFound();
        $this->as($this->accountant)->putJson("/api/contacts/{$otherContact->id}", ['name' => 'x y'])->assertNotFound();
        $this->pay($this->accountant, 'expense', $otherExpense->id, 50)->assertNotFound();
        $this->assertEquals(0, (float) $otherExpense->fresh()->paid_amount);

        $ids = collect($this->as($this->accountant)->getJson('/api/contacts')->json('data'))->pluck('id');
        $this->assertNotContains($otherContact->id, $ids);

        // جهة من مزرعة أخرى لا تُربط بسجل هنا
        $this->as($this->admin)->postJson("/api/flocks/{$this->flock->id}/sales", [
            'sale_date' => now()->toDateString(),
            'contact_id' => $otherContact->id,
            'items' => [['birds_count' => 5, 'total_weight_kg' => 10]],
        ])->assertStatus(422)->assertJsonValidationErrors('contact_id');
    }

    public function test_contact_crud_rules(): void
    {
        $this->as($this->admin)->postJson('/api/contacts', ['name' => 'شركة العلف', 'type' => 'supplier'])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        // الاسم نفسه مسموح في مزرعة أخرى
        $other = Farm::factory()->create();
        Contact::create(['farm_id' => $other->id, 'name' => 'صهريج الماء', 'type' => 'supplier']);
        $id = $this->as($this->admin)->postJson('/api/contacts', ['name' => 'صهريج الماء', 'type' => 'supplier', 'phone' => '0999'])
            ->assertCreated()->json('data.id');

        $partner = User::factory()->create();
        FarmUser::factory()->withRole('partner')->create(['farm_id' => $this->farm->id, 'user_id' => $partner->id]);
        $this->as($partner)->postJson('/api/contacts', ['name' => 'جديد', 'type' => 'buyer'])->assertForbidden();
        $this->as($partner)->deleteJson("/api/contacts/{$id}")->assertForbidden();
        $this->as($partner)->getJson('/api/contacts/balances')->assertOk();

        // المستخدم لا يحذف جهة لها سجلات
        $this->expense();
        $this->as($this->admin)->deleteJson("/api/contacts/{$this->supplier->id}")->assertStatus(422);
        $this->as($this->admin)->deleteJson("/api/contacts/{$id}")->assertOk();
    }

    public function test_contact_name_syncs_free_text(): void
    {
        $res = $this->as($this->admin)->postJson("/api/flocks/{$this->flock->id}/sales", [
            'sale_date' => now()->toDateString(),
            'buyer_name' => 'نص قديم',
            'contact_id' => $this->buyer->id,
            'items' => [['birds_count' => 5, 'total_weight_kg' => 10]],
        ])->assertCreated();
        $saleId = $res->json('data.id');
        $this->assertSame('أبو أحمد', Sale::find($saleId)->buyer_name);

        $this->as($this->admin)->putJson("/api/contacts/{$this->buyer->id}", ['name' => 'أبو أحمد الحلبي'])->assertOk();
        $this->assertSame('أبو أحمد الحلبي', Sale::find($saleId)->buyer_name);
    }

    public function test_review_queue_can_link_contact_and_moves_payments(): void
    {
        $expense = $this->expense(['contact_id' => null, 'paid_amount' => 100, 'remaining_amount' => 900, 'payment_status' => 'partial']);

        $this->as($this->admin)->patchJson("/api/accounting/review-queue/expense/{$expense->id}", ['contact_id' => $this->supplier->id])
            ->assertOk()->assertJsonPath('contact_name', 'شركة العلف');

        $this->assertSame($this->supplier->id, Payment::where('payable_id', $expense->id)->where('payable_type', 'expense')->value('contact_id'));
    }

    // ─── Backfill ───────────────────────────────────────────────────────────

    public function test_backfill_creates_single_opening_row_and_is_idempotent(): void
    {
        $category = ExpenseCategory::factory()->create();
        // سجلات قديمة أُدخلت قبل سجل الدفعات (بلا أحداث النموذج)
        $expenseId = DB::table('expenses')->insertGetId([
            'farm_id' => $this->farm->id, 'flock_id' => $this->flock->id, 'expense_category_id' => $category->id,
            'entry_date' => '2026-03-01', 'total_amount' => 1000, 'paid_amount' => 400, 'remaining_amount' => 600,
            'payment_status' => 'partial', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $unpaidId = DB::table('expenses')->insertGetId([
            'farm_id' => $this->farm->id, 'flock_id' => $this->flock->id, 'expense_category_id' => $category->id,
            'entry_date' => '2026-03-02', 'total_amount' => 50, 'paid_amount' => 0, 'remaining_amount' => 50,
            'payment_status' => 'unpaid', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $saleId = DB::table('sales')->insertGetId([
            'farm_id' => $this->farm->id, 'flock_id' => $this->flock->id, 'sale_date' => '2026-03-10',
            'contact_id' => $this->buyer->id,
            'gross_amount' => 800, 'net_amount' => 800, 'received_amount' => 800, 'remaining_amount' => 0,
            'payment_status' => 'paid', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = app(PaymentLedgerService::class);
        $this->assertSame(2, $service->backfillOpening());
        $this->assertSame(0, $service->backfillOpening());

        $p = Payment::where('payable_type', 'expense')->where('payable_id', $expenseId)->sole();
        $this->assertEquals(400, $p->amount);
        $this->assertSame('2026-03-01', $p->paid_at->toDateString());
        $this->assertTrue($p->is_opening);
        $this->assertSame(0, Payment::where('payable_type', 'expense')->where('payable_id', $unpaidId)->count());

        $s = Payment::where('payable_type', 'sale')->where('payable_id', $saleId)->sole();
        $this->assertEquals(800, $s->amount);
        $this->assertSame('2026-03-10', $s->paid_at->toDateString());
        $this->assertSame($this->buyer->id, $s->contact_id);
    }
}

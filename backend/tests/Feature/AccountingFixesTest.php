<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\FlockWaterLog;
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
}

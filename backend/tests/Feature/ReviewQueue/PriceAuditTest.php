<?php

namespace Tests\Feature\ReviewQueue;

use App\Models\Expense;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\PriceAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceAuditTest extends TestCase
{
    use RefreshDatabase;

    private Farm $farm;

    private User $accountant;

    private User $admin;

    private Expense $expense;

    protected function setUp(): void
    {
        parent::setUp();

        $this->farm = Farm::factory()->create();
        $this->accountant = $this->member('accountant');
        $this->admin = $this->member('farm_admin');
        $flock = Flock::factory()->active()->create(['farm_id' => $this->farm->id]);

        $this->expense = Expense::factory()->create([
            'farm_id' => $this->farm->id,
            'flock_id' => $flock->id,
            'quantity' => 10,
            'unit_price' => null,
            'total_amount' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'payment_status' => 'unpaid',
        ]);
    }

    private function member(string $role): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->withRole($role)->create([
            'farm_id' => $this->farm->id, 'user_id' => $user->id, 'status' => 'active',
        ]);

        return $user;
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'sanctum')->withHeaders(['X-Farm-Id' => $this->farm->id]);
    }

    private function setPrice(User $user, float $price)
    {
        return $this->as($user)->patchJson("/api/accounting/review-queue/expense/{$this->expense->id}", [
            'unit_price' => $price,
        ]);
    }

    public function test_first_price_is_logged_without_old_price(): void
    {
        $this->setPrice($this->accountant, 5)->assertOk()
            ->assertJsonPath('unit_price', 5)
            ->assertJsonPath('price_history.0.old_price', null)
            ->assertJsonPath('price_history.0.new_price', 5);

        $this->assertDatabaseHas('price_audit_logs', [
            'record_type' => 'expense', 'record_id' => $this->expense->id,
            'user_id' => $this->accountant->id, 'old_price' => null,
        ]);
    }

    public function test_editing_a_price_keeps_the_old_price_in_history(): void
    {
        $this->setPrice($this->accountant, 5)->assertOk();
        $this->setPrice($this->accountant, 8)->assertOk()
            ->assertJsonPath('unit_price', 8)
            ->assertJsonCount(2, 'price_history')
            ->assertJsonPath('price_history.1.old_price', 5)
            ->assertJsonPath('price_history.1.new_price', 8)
            ->assertJsonPath('price_history.1.by', $this->accountant->name);

        $this->assertSame(2, PriceAuditLog::count());
    }

    public function test_unchanged_price_is_not_logged(): void
    {
        $this->setPrice($this->accountant, 5)->assertOk();
        $this->setPrice($this->accountant, 5)->assertOk();

        $this->assertSame(1, PriceAuditLog::count());
    }

    public function test_priced_and_paid_record_can_still_be_edited_via_scope_all(): void
    {
        $this->setPrice($this->accountant, 5)->assertOk();
        $this->as($this->admin)->patchJson("/api/accounting/review-queue/expense/{$this->expense->id}", [
            'paid_amount' => 50,
        ])->assertOk();

        // مدفوع بالكامل ومسعّر: لا يظهر في قائمة المراجعة العادية
        $this->as($this->accountant)->getJson('/api/accounting/review-queue')
            ->assertJsonPath('meta.total', 0);

        // لكنه يظهر في كل الأسعار ويمكن تعديله
        $this->as($this->accountant)->getJson('/api/accounting/review-queue?scope=all')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.price_history.0.new_price', 5);
    }

    public function test_price_cannot_be_cleared(): void
    {
        $this->setPrice($this->accountant, 5)->assertOk();

        $this->as($this->accountant)
            ->patchJson("/api/accounting/review-queue/expense/{$this->expense->id}", ['unit_price' => null])
            ->assertStatus(422);
    }

    public function test_accountant_cannot_record_payments_but_admin_can(): void
    {
        $this->as($this->accountant)
            ->patchJson("/api/accounting/review-queue/expense/{$this->expense->id}", ['paid_amount' => 10])
            ->assertStatus(403);

        $this->setPrice($this->accountant, 5)->assertOk();

        $this->as($this->admin)
            ->patchJson("/api/accounting/review-queue/expense/{$this->expense->id}", ['paid_amount' => 10])
            ->assertOk();
    }

    public function test_audit_log_is_append_only(): void
    {
        $this->setPrice($this->accountant, 5)->assertOk();
        $log = PriceAuditLog::firstOrFail();

        $this->expectException(\LogicException::class);
        $log->update(['new_price' => 1]);
    }

    public function test_audit_log_cannot_be_deleted(): void
    {
        $this->setPrice($this->accountant, 5)->assertOk();

        $this->expectException(\LogicException::class);
        PriceAuditLog::firstOrFail()->delete();
    }

    public function test_only_admin_can_read_the_audit_log(): void
    {
        $this->setPrice($this->accountant, 5)->assertOk();
        $this->setPrice($this->accountant, 9)->assertOk();

        $this->as($this->accountant)->getJson('/api/accounting/price-audit')->assertStatus(403);

        $this->as($this->admin)->getJson('/api/accounting/price-audit')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.old_price', 5)
            ->assertJsonPath('data.0.new_price', 9)
            ->assertJsonPath('data.0.is_edit', true)
            ->assertJsonPath('data.0.by', $this->accountant->name);

        $this->as($this->admin)->getJson('/api/accounting/price-audit?edits_only=1')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_audit_log_is_isolated_per_farm(): void
    {
        $this->setPrice($this->accountant, 5)->assertOk();

        $other = Farm::factory()->create();
        $otherAdmin = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $other->id, 'user_id' => $otherAdmin->id, 'status' => 'active']);

        $this->app['auth']->forgetGuards();
        $this->actingAs($otherAdmin, 'sanctum')->withHeaders(['X-Farm-Id' => $other->id])
            ->getJson('/api/accounting/price-audit')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }
}

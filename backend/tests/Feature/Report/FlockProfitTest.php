<?php

namespace Tests\Feature\Report;

use App\Models\Expense;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlockProfitTest extends TestCase
{
    use RefreshDatabase;

    private Farm $farm;

    private User $admin;

    private User $accountant;

    private Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->farm = Farm::factory()->create();
        $this->admin = $this->member('farm_admin');
        $this->accountant = $this->member('accountant');
        $this->farm->update(['admin_user_id' => $this->admin->id]);

        $this->partner = Partner::create([
            'farm_id' => $this->farm->id, 'name' => 'شريك أ', 'whatsapp' => '0999', 'status' => 'active',
        ]);
        // 30% للشريك، والمدير يأخذ الباقي 70% تلقائياً
        $this->as($this->accountant)->patchJson("/api/partners/{$this->partner->id}/share", ['share_percent' => 30])->assertOk();
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

    private function flockWith(float $sales, float $expenses): Flock
    {
        $flock = Flock::factory()->active()->create(['farm_id' => $this->farm->id]);
        Sale::factory()->forFlock($flock)->create(['gross_amount' => $sales, 'discount_amount' => 0, 'net_amount' => $sales, 'received_amount' => $sales, 'remaining_amount' => 0]);
        Expense::factory()->create(['farm_id' => $this->farm->id, 'flock_id' => $flock->id, 'quantity' => 1, 'unit_price' => $expenses, 'total_amount' => $expenses, 'paid_amount' => $expenses]);

        return $flock;
    }

    public function test_accountant_sees_live_profit_and_each_partner_share(): void
    {
        $flock = $this->flockWith(1000, 400);

        $res = $this->as($this->accountant)->getJson("/api/reports/flock-profit?flock_id={$flock->id}")->assertOk();

        $this->assertEquals(1000, $res->json('sales'));
        $this->assertEquals(400, $res->json('total_expenses'));
        $this->assertEquals(600, $res->json('net'));
        $this->assertFalse($res->json('recorded'));
        $this->assertEquals(100, $res->json('shares_total'));

        $byPartner = collect($res->json('partners'))->keyBy('partner_id');
        $this->assertEquals(180, $byPartner[$this->partner->id]['amount']);
        $this->assertEquals(600, collect($res->json('partners'))->sum('amount'));
    }

    public function test_loss_is_shown_as_negative_shares(): void
    {
        $flock = $this->flockWith(300, 500);

        $res = $this->as($this->accountant)->getJson("/api/reports/flock-profit?flock_id={$flock->id}")->assertOk();

        $this->assertEquals(-200, $res->json('net'));
        $this->assertEquals(-60, collect($res->json('partners'))->firstWhere('partner_id', $this->partner->id)['amount']);
        $this->assertEquals(-200, collect($res->json('partners'))->sum('amount'));
    }

    public function test_shown_shares_match_what_closing_the_flock_records(): void
    {
        $flock = $this->flockWith(1000, 400);
        $live = collect($this->as($this->accountant)->getJson("/api/reports/flock-profit?flock_id={$flock->id}")->json('partners'))
            ->mapWithKeys(fn ($p) => [$p['partner_id'] => $p['amount']]);

        $this->as($this->admin)->putJson("/api/flocks/{$flock->id}", ['status' => 'closed'])->assertOk();

        $recorded = PartnerTransaction::where('flock_id', $flock->id)->where('transaction_type', 'profit')
            ->pluck('amount', 'partner_id')->map(fn ($a) => (float) $a);
        $this->assertEquals($live->sort()->all(), $recorded->sort()->all());

        $after = $this->as($this->accountant)->getJson("/api/reports/flock-profit?flock_id={$flock->id}")->assertOk();
        $this->assertTrue($after->json('recorded'));
        $this->assertEquals(600, collect($after->json('partners'))->sum('amount'));
    }

    public function test_supervisor_cannot_see_partner_shares(): void
    {
        $supervisor = $this->member('supervisor');
        $flock = $this->flockWith(1000, 400);

        $this->as($supervisor)->getJson("/api/reports/flock-profit?flock_id={$flock->id}")->assertForbidden();
    }
}

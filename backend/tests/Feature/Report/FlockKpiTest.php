<?php

namespace Tests\Feature\Report;

use App\Models\Expense;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\FlockMortality;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlockKpiTest extends TestCase
{
    use RefreshDatabase;

    private Farm $farm;

    private User $admin;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->farm = Farm::factory()->create();
        $this->admin = $this->member('farm_admin');
        $this->warehouse = Warehouse::factory()->create(['farm_id' => $this->farm->id]);
    }

    private function member(string $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->withRole($role)->create([
            'farm_id' => ($farm ?? $this->farm)->id, 'user_id' => $user->id, 'status' => 'active',
        ]);

        return $user;
    }

    private function as(User $user, ?Farm $farm = null): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'sanctum')->withHeaders(['X-Farm-Id' => ($farm ?? $this->farm)->id]);
    }

    private function sale(Flock $flock, int $birds, float $kg, float $net, string $date): void
    {
        $sale = Sale::factory()->create([
            'farm_id' => $flock->farm_id, 'flock_id' => $flock->id, 'sale_date' => $date,
            'gross_amount' => $net, 'discount_amount' => 0, 'net_amount' => $net, 'received_amount' => $net, 'remaining_amount' => 0,
        ]);
        SaleItem::factory()->forSale($sale)->create([
            'birds_count' => $birds, 'total_weight_kg' => $kg, 'avg_weight_kg' => round($kg / $birds, 3),
            'unit_price_per_kg' => $net > 0 ? round($net / $kg, 4) : null, 'line_total' => $net,
        ]);
    }

    private function consume(Flock $flock, string $typeCode, string $unit, float $qty, ?float $total): void
    {
        $type = ItemType::where('code', $typeCode)->first() ?? ItemType::factory()->create(['code' => $typeCode]);
        $item = Item::factory()->create(['farm_id' => $this->farm->id, 'item_type_id' => $type->id, 'content_unit' => $unit]);
        InventoryTransaction::create([
            'farm_id' => $this->farm->id, 'warehouse_id' => $this->warehouse->id, 'item_id' => $item->id, 'flock_id' => $flock->id,
            'transaction_date' => now()->toDateString(), 'transaction_type' => 'consumption', 'direction' => 'out',
            'source_module' => $typeCode === 'feed' ? 'flock_feed' : 'flock_medicine',
            'original_quantity' => $qty, 'computed_quantity' => $qty, 'content_unit' => $unit, 'total_amount' => $total,
        ]);
    }

    private function expense(Flock $flock, float $amount): void
    {
        Expense::factory()->create(['farm_id' => $flock->farm_id, 'flock_id' => $flock->id, 'quantity' => 1, 'unit_price' => $amount, 'total_amount' => $amount, 'paid_amount' => $amount]);
    }

    private function mortality(Flock $flock, int $qty): void
    {
        FlockMortality::factory()->create(['farm_id' => $flock->farm_id, 'flock_id' => $flock->id, 'quantity' => $qty, 'entry_date' => $flock->start_date]);
    }

    /** فوج مغلق بأرقام مستديرة: 1000 كتكوت، 50 نافق، 950 مباع بوزن 2375 كغ، 4037.5 كغ علف، عمر 40 يوماً */
    private function closedFlock(): Flock
    {
        $flock = Flock::factory()->create([
            'farm_id' => $this->farm->id, 'status' => 'closed', 'initial_count' => 1000,
            'start_date' => '2026-01-01', 'close_date' => '2026-02-09',
        ]);
        $this->mortality($flock, 50);
        $this->sale($flock, 500, 1250, 4000, '2026-02-05');
        $this->sale($flock, 450, 1125, 4075, '2026-02-09');
        $this->consume($flock, 'feed', 'كغ', 4000, 1900);
        $this->consume($flock, 'feed', 'جرام', 37500, 100);      // 37.5 كغ
        $this->consume($flock, 'medicine', 'مل', 10, null);       // ليس علفاً: لا يدخل في الكيلوغرامات
        $this->expense($flock, 5000);

        return $flock;
    }

    public function test_formulas_on_a_closed_flock(): void
    {
        $flock = $this->closedFlock();

        $k = $this->as($this->admin)->getJson("/api/flocks/{$flock->id}/kpis")->assertOk()->json('data');

        $this->assertSame(1000, $k['initial_birds']);
        $this->assertSame(50, $k['mortality']);
        $this->assertEquals(5, $k['mortality_pct']);
        $this->assertEquals(95, $k['livability_pct']);
        $this->assertSame(950, $k['birds_sold']);
        $this->assertSame(0, $k['birds_remaining']);
        $this->assertEquals(2375, $k['sold_weight_kg']);
        $this->assertEquals(2.5, $k['avg_weight_kg']);
        $this->assertSame(40, $k['age_days']);
        $this->assertEquals(4037.5, $k['feed_kg']);
        $this->assertEquals(1.7, $k['fcr']);                         // 4037.5 / 2375
        $this->assertEquals(62.5, $k['adg_g']);                       // 2500 / 40
        $this->assertEquals(349, $k['epef']);                         // 95 × 2.5 / (40 × 1.7) × 100
        $this->assertEquals(7000, $k['total_cost']);                  // 5000 + 1900 + 100
        $this->assertEquals(8075, $k['revenue']);
        $this->assertEquals(7, $k['cost_per_bird']);
        $this->assertEquals(2.9474, $k['cost_per_kg']);               // 7000 / 2375
        $this->assertEquals(2000, $k['feed_cost']);
        $this->assertEquals(28.57, $k['feed_cost_share_pct']);
        $this->assertEquals(3.4, $k['revenue_per_kg']);               // 8075 / 2375
        $this->assertEquals(0.4526, $k['margin_per_kg']);
        $this->assertNull($k['break_even_price_per_kg']);            // لم يبقَ طيور
        $this->assertNull($k['cost_per_live_bird']);
        $this->assertSame([], $k['missing']);
    }

    public function test_total_cost_matches_the_profit_page(): void
    {
        $flock = $this->closedFlock();
        $accountant = $this->member('accountant');

        $profit = $this->as($accountant)->getJson("/api/reports/flock-profit?flock_id={$flock->id}")->assertOk();
        $kpis = $this->as($accountant)->getJson("/api/flocks/{$flock->id}/kpis")->assertOk();

        $this->assertEquals($profit->json('total_expenses'), $kpis->json('data.total_cost'));
        $this->assertEquals($profit->json('sales'), $kpis->json('data.revenue'));
    }

    public function test_active_flock_estimates_birds_in_house_and_break_even(): void
    {
        $flock = Flock::factory()->active()->create(['farm_id' => $this->farm->id, 'initial_count' => 1000]);
        $this->mortality($flock, 20);
        $this->sale($flock, 100, 180, 0, now()->subDays(2)->toDateString());   // غير مسعّر
        $this->sale($flock, 100, 200, 0, now()->toDateString());               // آخر بيع: 2.0 كغ للطير
        $this->expense($flock, 3920);

        $k = $this->as($this->admin)->getJson("/api/flocks/{$flock->id}/kpis")->assertOk()->json('data');

        $this->assertSame(780, $k['birds_remaining']);
        $this->assertEquals(1.9, $k['avg_weight_kg']);               // 380 / 200
        $this->assertEquals(2.0, $k['current_weight_kg']);
        $this->assertEquals(1560, $k['estimated_remaining_kg']);     // 780 × 2.0
        $this->assertEquals(1940, $k['live_weight_kg']);             // 380 + 1560
        $this->assertEquals(2.0206, $k['cost_per_kg']);              // 3920 / 1940
        $this->assertEquals(2.5128, $k['break_even_price_per_kg']); // 3920 / 1560
        $this->assertEquals(5.0256, $k['cost_per_live_bird']);       // 3920 / 780
        $this->assertNull($k['revenue_per_kg']);
        $this->assertNull($k['margin_per_kg']);
        $this->assertNull($k['fcr']);
        $this->assertNull($k['epef']);
        $codes = array_column($k['missing'], 'code');
        $this->assertContains('feed', $codes);
        $this->assertContains('sale_prices', $codes);
        $this->assertNotContains('sales', $codes);
    }

    public function test_break_even_subtracts_revenue_already_received(): void
    {
        $flock = Flock::factory()->active()->create(['farm_id' => $this->farm->id, 'initial_count' => 1000]);
        $this->sale($flock, 500, 1000, 2000, now()->toDateString());
        $this->expense($flock, 5000);

        $k = $this->as($this->admin)->getJson("/api/flocks/{$flock->id}/kpis")->assertOk()->json('data');

        $this->assertEquals(3000, $k['unrecovered_cost']);
        $this->assertEquals(3, $k['break_even_price_per_kg']);      // 3000 / (500 × 2.0)
        $this->assertEquals(6, $k['cost_per_live_bird']);
    }

    public function test_empty_flock_returns_nulls_and_explains_why(): void
    {
        $flock = Flock::factory()->active()->create(['farm_id' => $this->farm->id, 'initial_count' => 500]);

        $k = $this->as($this->admin)->getJson("/api/flocks/{$flock->id}/kpis")->assertOk()->json('data');

        $this->assertEquals(0, $k['mortality_pct']);
        $this->assertEquals(100, $k['livability_pct']);
        $this->assertSame(500, $k['birds_remaining']);
        foreach (['avg_weight_kg', 'live_weight_kg', 'feed_kg', 'fcr', 'adg_g', 'epef', 'cost_per_bird', 'cost_per_kg',
            'feed_cost_share_pct', 'revenue_per_kg', 'margin_per_kg', 'break_even_price_per_kg', 'cost_per_live_bird'] as $key) {
            $this->assertNull($k[$key], $key);
        }
        $codes = array_column($k['missing'], 'code');
        foreach (['sales', 'feed', 'costs', 'current_weight'] as $code) {
            $this->assertContains($code, $codes);
        }
        $this->assertNotEmpty($k['missing'][0]['message']);
    }

    public function test_feed_in_a_non_weight_unit_is_flagged_not_counted(): void
    {
        $flock = $this->closedFlock();
        $this->consume($flock, 'feed', 'عبوة', 5, 50);

        $k = $this->as($this->admin)->getJson("/api/flocks/{$flock->id}/kpis")->assertOk()->json('data');

        $this->assertEquals(4037.5, $k['feed_kg']);
        $this->assertEquals(2050, $k['feed_cost']);
        $this->assertSame(['عبوة'], $k['feed_unknown_units']);
        $this->assertContains('feed_unit', array_column($k['missing'], 'code'));
    }

    public function test_unpriced_feed_is_flagged(): void
    {
        $flock = $this->closedFlock();
        $this->consume($flock, 'feed', 'كغ', 100, null);

        $k = $this->as($this->admin)->getJson("/api/flocks/{$flock->id}/kpis")->assertOk()->json('data');

        $this->assertEquals(4137.5, $k['feed_kg']);
        $this->assertContains('feed_prices', array_column($k['missing'], 'code'));
    }

    public function test_partner_and_supervisor_can_read_flock_kpis(): void
    {
        $flock = $this->closedFlock();

        $this->as($this->member('partner'))->getJson("/api/flocks/{$flock->id}/kpis")->assertOk();
        $this->as($this->member('supervisor'))->getJson("/api/flocks/{$flock->id}/kpis")->assertOk();
    }

    public function test_other_farm_cannot_read_kpis(): void
    {
        $flock = $this->closedFlock();
        $otherFarm = Farm::factory()->create();
        $outsider = $this->member('farm_admin', $otherFarm);

        $this->as($outsider, $otherFarm)->getJson("/api/flocks/{$flock->id}/kpis")->assertNotFound();
        // ولا بتمرير معرّف هذه المزرعة دون عضوية فيها
        $this->as($outsider)->getJson("/api/flocks/{$flock->id}/kpis")->assertForbidden();
    }

    public function test_comparison_lists_active_and_closed_flocks_of_this_farm_only(): void
    {
        $old = $this->closedFlock();
        $active = Flock::factory()->active()->create(['farm_id' => $this->farm->id, 'initial_count' => 800]);
        Flock::factory()->draft()->create(['farm_id' => $this->farm->id]);
        Flock::factory()->cancelled()->create(['farm_id' => $this->farm->id, 'start_date' => now()->subDays(3)->toDateString()]);
        $otherFarm = Farm::factory()->create();
        Flock::factory()->closed()->create(['farm_id' => $otherFarm->id]);

        $rows = $this->as($this->member('partner'))->getJson('/api/reports/flock-comparison')->assertOk()->json('data');

        $this->assertSame([$active->id, $old->id], array_column(array_column($rows, 'flock'), 'id'));
        $this->assertEquals(1.7, $rows[1]['fcr']);
    }

    public function test_comparison_is_limited_to_twenty_flocks(): void
    {
        for ($i = 0; $i < 22; $i++) {
            Flock::factory()->create(['farm_id' => $this->farm->id, 'status' => 'closed', 'initial_count' => 100,
                'start_date' => now()->subDays(100 + $i)->toDateString(), 'close_date' => now()->subDays(60 + $i)->toDateString()]);
        }

        $rows = $this->as($this->member('accountant'))->getJson('/api/reports/flock-comparison')->assertOk()->json('data');

        $this->assertCount(20, $rows);
    }

    public function test_supervisor_cannot_open_the_comparison(): void
    {
        $this->as($this->member('supervisor'))->getJson('/api/reports/flock-comparison')->assertForbidden();
    }
}

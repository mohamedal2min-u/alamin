<?php

namespace Tests\Feature\Statement;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\FarmPartnerShare;
use App\Models\Flock;
use App\Models\FlockMortality;
use App\Models\FlockWaterLog;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Warehouse;
use App\Services\FlockProfitService;
use App\Services\Statements\StatementDataBuilder;
use App\Services\Statements\StatementPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * أرقام الكشوف تُختبر منفصلة عن توليد PDF: يجب أن تطابق FlockProfitService حرفياً.
 */
class StatementDataBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Farm $farm;

    private Flock $flock;

    private Partner $a;

    private Partner $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->farm = Farm::factory()->create();
        $this->flock = Flock::factory()->active()->create(['farm_id' => $this->farm->id, 'initial_count' => 1000]);

        $this->a = Partner::create(['farm_id' => $this->farm->id, 'name' => 'أ', 'status' => 'active']);
        $this->b = Partner::create(['farm_id' => $this->farm->id, 'name' => 'ب', 'status' => 'active']);
        FarmPartnerShare::create(['farm_id' => $this->farm->id, 'partner_id' => $this->a->id, 'share_percent' => 60, 'is_active' => true]);
        FarmPartnerShare::create(['farm_id' => $this->farm->id, 'partner_id' => $this->b->id, 'share_percent' => 40, 'is_active' => true]);

        // مبيعات
        $sale = Sale::factory()->forFlock($this->flock)->create(['gross_amount' => 5000, 'discount_amount' => 0, 'net_amount' => 5000, 'received_amount' => 5000, 'remaining_amount' => 0]);
        SaleItem::factory()->create(['sale_id' => $sale->id, 'farm_id' => $this->farm->id, 'flock_id' => $this->flock->id, 'birds_count' => 900, 'total_weight_kg' => 2250, 'unit_price_per_kg' => 2.2222, 'line_total' => 5000]);
        FlockMortality::factory()->create(['farm_id' => $this->farm->id, 'flock_id' => $this->flock->id, 'quantity' => 40]);

        // كتاكيت + مصروف آخر + دين شراء مخزون (مستثنى)
        $chicks = ExpenseCategory::create(['name' => 'شراء كتاكيت', 'farm_id' => null, 'is_system' => true, 'is_active' => true]);
        $bedding = ExpenseCategory::factory()->bedding()->create(['farm_id' => null]);
        $exp = fn (array $a) => Expense::factory()->create(array_merge(['farm_id' => $this->farm->id, 'flock_id' => $this->flock->id, 'quantity' => 1, 'paid_amount' => 0], $a));
        $exp(['expense_category_id' => $chicks->id, 'unit_price' => 1000, 'total_amount' => 1000]);
        $exp(['expense_category_id' => $bedding->id, 'unit_price' => 150, 'total_amount' => 150]);

        // استهلاك مخزون: علف ودواء وفحم
        $wh = Warehouse::factory()->create(['farm_id' => $this->farm->id]);
        $consume = function (string $code, float $amount) use ($wh) {
            $type = ItemType::firstWhere('code', $code) ?? ItemType::factory()->create(['code' => $code, 'farm_id' => null]);
            $item = Item::factory()->create(['farm_id' => $this->farm->id, 'item_type_id' => $type->id]);

            return InventoryTransaction::create([
                'farm_id' => $this->farm->id, 'warehouse_id' => $wh->id, 'item_id' => $item->id, 'flock_id' => $this->flock->id,
                'transaction_date' => now()->toDateString(), 'transaction_type' => 'consumption', 'direction' => 'out',
                'computed_quantity' => 1, 'total_amount' => $amount,
            ]);
        };
        $consume('feed', 1800);
        $consume('medicine', 250);
        $consume('charcoal', 70);
        $purchase = $consume('feed', 999); // يُعكس كشراء أدناه
        $purchase->update(['transaction_type' => 'purchase', 'direction' => 'in']);
        $exp(['unit_price' => 999, 'total_amount' => 999, 'linked_inventory_transaction_id' => $purchase->id]);

        FlockWaterLog::factory()->create(['farm_id' => $this->farm->id, 'flock_id' => $this->flock->id, 'total_amount' => 30]);
    }

    private function builder(): StatementDataBuilder
    {
        return app(StatementDataBuilder::class);
    }

    public function test_flock_statement_numbers_equal_flock_profit_service(): void
    {
        $service = app(FlockProfitService::class);
        $summary = $service->summary($this->flock);
        $data = $this->builder()->flock($this->flock);

        $this->assertSame($summary['sales'], $data['revenue']);
        $this->assertSame($summary['total_expenses'], $data['total_expenses']);
        $this->assertSame($summary['net'], $data['net']);
        $this->assertSame($summary['partners'], $data['partners']);
        $this->assertSame($summary['balances'], $data['balances']);

        // الأرقام نفسها التي يعرضها مسار صفحة الربح
        $this->assertEquals(5000, $data['revenue']);
        $this->assertEquals(1000 + 150 + 1800 + 250 + 70 + 30, $data['total_expenses']);
        $this->assertEquals(1700, $data['net']);
        $amounts = collect($data['partners'])->pluck('amount', 'partner_id');
        $this->assertEquals(1020, $amounts[$this->a->id]);
        $this->assertEquals(680, $amounts[$this->b->id]);
    }

    public function test_cost_breakdown_sums_to_total_expenses(): void
    {
        $data = $this->builder()->flock($this->flock);
        $c = $data['costs'];

        $this->assertEquals(1000, $c['chicks']);
        $this->assertEquals(1800, $c['feed']);
        $this->assertEquals(250, $c['medicine']);
        $this->assertEquals(30, $c['water']);
        $this->assertEquals(70, $c['other_inventory']);
        $this->assertEquals(150, $c['other_expenses']);
        $this->assertEqualsWithDelta(
            $data['total_expenses'],
            $c['chicks'] + $c['feed'] + $c['medicine'] + $c['water'] + $c['other_inventory'] + $c['other_expenses'],
            0.001,
        );
    }

    public function test_flock_info(): void
    {
        $f = $this->builder()->flock($this->flock)['flock'];

        $this->assertSame(1000, $f['initial_count']);
        $this->assertSame(40, $f['mortality']);
        $this->assertSame(900, $f['sold_birds']);
        $this->assertEquals(2250, $f['sold_weight_kg']);
        $this->assertEquals(2.5, $f['avg_weight_kg']);
        $this->assertSame(60, $f['remaining']);
    }

    public function test_closed_flock_uses_recorded_amounts(): void
    {
        $this->flock->update(['status' => 'closed', 'close_date' => now()->toDateString()]);
        PartnerTransaction::create(['farm_id' => $this->farm->id, 'partner_id' => $this->a->id, 'flock_id' => $this->flock->id, 'transaction_date' => now()->toDateString(), 'transaction_type' => 'profit', 'amount' => 1111, 'metadata' => ['applied_share_percent' => 60]]);

        $data = $this->builder()->flock($this->flock->fresh());

        $this->assertTrue($data['recorded']);
        $this->assertSame(app(FlockProfitService::class)->summary($this->flock->fresh())['partners'], $data['partners']);
        $this->assertEquals(1111, $data['partners'][0]['amount']);
    }

    public function test_partner_statement_running_and_per_flock_balances_match_service(): void
    {
        $old = Flock::factory()->closed()->create(['farm_id' => $this->farm->id]);
        $tx = fn (array $x) => PartnerTransaction::create(array_merge(['farm_id' => $this->farm->id, 'partner_id' => $this->a->id], $x));
        $tx(['transaction_date' => '2026-01-01', 'flock_id' => null, 'transaction_type' => 'deposit', 'amount' => 1000]);
        $tx(['transaction_date' => '2026-02-01', 'flock_id' => $old->id, 'transaction_type' => 'profit', 'amount' => 500]);
        $tx(['transaction_date' => '2026-03-01', 'flock_id' => $this->flock->id, 'transaction_type' => 'withdraw', 'amount' => 200]);
        $tx(['transaction_date' => '2026-03-02', 'flock_id' => $this->flock->id, 'transaction_type' => 'adjustment', 'amount' => 999]);
        $tx(['transaction_date' => '2026-03-03', 'flock_id' => $old->id, 'transaction_type' => 'loss', 'amount' => 50]);
        $tx(['transaction_date' => '2026-03-04', 'flock_id' => null, 'transaction_type' => 'settlement', 'amount' => 100]);
        // حركة لشريك آخر لا تظهر
        PartnerTransaction::create(['farm_id' => $this->farm->id, 'partner_id' => $this->b->id, 'transaction_date' => '2026-01-01', 'transaction_type' => 'deposit', 'amount' => 7]);

        $data = $this->builder()->partner($this->a);

        $this->assertCount(6, $data['transactions']);
        $this->assertSame([1000.0, 1500.0, 1300.0, 1300.0, 1250.0, 1150.0], array_column($data['transactions'], 'balance'));
        $this->assertFalse($data['transactions'][3]['counted']);
        $this->assertEquals(1150, $data['total_balance']);

        $perFlock = collect($data['per_flock'])->keyBy(fn ($r) => $r['flock_id'] ?? 0);
        $this->assertEquals(900, $perFlock[0]['balance']);
        $this->assertEquals(450, $perFlock[$old->id]['balance']);
        $this->assertEquals(-200, $perFlock[$this->flock->id]['balance']);

        // نفس صيغة FlockProfitService::balances
        $svc = collect(app(FlockProfitService::class)->balances($this->flock))->firstWhere('partner_id', $this->a->id);
        $this->assertEquals($svc['total_balance'], $data['total_balance']);
        $this->assertEquals($svc['flock_balance'], $perFlock[$this->flock->id]['balance']);
    }

    public function test_sale_ticket_weights_and_amounts(): void
    {
        $sale = Sale::factory()->forFlock($this->flock)->create(['buyer_name' => 'تاجر', 'gross_amount' => 600, 'discount_amount' => 10, 'net_amount' => 590, 'received_amount' => 300, 'remaining_amount' => 290]);
        SaleItem::factory()->create(['sale_id' => $sale->id, 'farm_id' => $this->farm->id, 'flock_id' => $this->flock->id, 'birds_count' => 50, 'total_weight_kg' => 120, 'crates_count' => 5, 'crate_weight_kg' => 6, 'gross_weight_kg' => 150, 'unit_price_per_kg' => 2.5, 'line_total' => 300]);
        SaleItem::factory()->create(['sale_id' => $sale->id, 'farm_id' => $this->farm->id, 'flock_id' => $this->flock->id, 'birds_count' => 50, 'total_weight_kg' => 120, 'crates_count' => 4, 'crate_weight_kg' => 5, 'gross_weight_kg' => null, 'unit_price_per_kg' => 2.5, 'line_total' => 300]);

        $d = $this->builder()->sale($sale);

        $this->assertSame('تاجر', $d['sale']['buyer']);
        $this->assertEquals(30, $d['lines'][0]['tare_kg']); // القائم - الصافي
        $this->assertEquals(20, $d['lines'][1]['tare_kg']); // الأقفاص × وزن القفص
        $this->assertSame(['birds' => 100, 'crates' => 9, 'gross_kg' => 150.0, 'tare_kg' => 50.0, 'net_kg' => 240.0], $d['totals']);
        $this->assertTrue($d['priced']);
        $this->assertEquals(590, $d['net_amount']);
        $this->assertEquals(300, $d['received_amount']);
        $this->assertEquals(290, $d['remaining_amount']);
    }

    public function test_unpriced_sale_hides_prices(): void
    {
        $sale = Sale::factory()->forFlock($this->flock)->create(['gross_amount' => 0, 'discount_amount' => 0, 'net_amount' => 0, 'received_amount' => 0, 'remaining_amount' => 0]);
        SaleItem::factory()->create(['sale_id' => $sale->id, 'farm_id' => $this->farm->id, 'flock_id' => $this->flock->id, 'birds_count' => 10, 'total_weight_kg' => 25, 'unit_price_per_kg' => null, 'line_total' => 0]);

        $d = $this->builder()->sale($sale);

        $this->assertFalse($d['priced']);
        $this->assertNull($d['lines'][0]['price_per_kg']);
    }

    public function test_renderer_outputs_pdf_for_each_type(): void
    {
        $renderer = app(StatementPdfRenderer::class);
        $sale = Sale::where('flock_id', $this->flock->id)->first();

        foreach (['flock' => $this->builder()->flock($this->flock), 'partner' => $this->builder()->partner($this->a), 'sale' => $this->builder()->sale($sale)] as $type => $data) {
            $this->assertStringStartsWith('%PDF', $renderer->render($type, $data));
        }
    }
}

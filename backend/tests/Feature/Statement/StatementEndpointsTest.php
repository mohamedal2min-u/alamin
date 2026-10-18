<?php

namespace Tests\Feature\Statement;

use App\Models\Expense;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatementEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private Farm $farm;

    private Farm $otherFarm;

    private Flock $flock;

    private Sale $sale;

    private Partner $partner;

    private User $partnerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->farm = Farm::factory()->create(['name' => 'مزرعة الأمين']);
        $this->otherFarm = Farm::factory()->create();

        $this->flock = Flock::factory()->active()->create(['farm_id' => $this->farm->id]);
        $this->sale = Sale::factory()->forFlock($this->flock)->create([
            'buyer_name' => 'تاجر', 'gross_amount' => 1000, 'discount_amount' => 0, 'net_amount' => 1000,
            'received_amount' => 600, 'remaining_amount' => 400, 'payment_status' => 'partial',
        ]);
        SaleItem::factory()->create([
            'sale_id' => $this->sale->id, 'farm_id' => $this->farm->id, 'flock_id' => $this->flock->id,
            'birds_count' => 100, 'total_weight_kg' => 250, 'crates_count' => 10, 'crate_weight_kg' => 5,
            'gross_weight_kg' => 300, 'unit_price_per_kg' => 4, 'line_total' => 1000,
        ]);
        Expense::factory()->create(['farm_id' => $this->farm->id, 'flock_id' => $this->flock->id, 'quantity' => 1, 'unit_price' => 300, 'total_amount' => 300, 'paid_amount' => 300]);

        $this->partnerUser = $this->member('partner');
        $this->partner = Partner::create(['farm_id' => $this->farm->id, 'user_id' => $this->partnerUser->id, 'name' => 'شريك أ', 'status' => 'active']);
        PartnerTransaction::create([
            'farm_id' => $this->farm->id, 'partner_id' => $this->partner->id, 'transaction_date' => now()->toDateString(),
            'transaction_type' => 'deposit', 'amount' => 500,
        ]);
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

    private function linkFor(User $user, string $uri): string
    {
        $res = $this->as($user)->getJson($uri)->assertOk()->assertJsonStructure(['url', 'path', 'expires_in']);
        $this->assertStringContainsString('signature=', $res->json('url'));
        $this->app['auth']->forgetGuards();

        return $res->json('path');
    }

    // ── الصلاحيات ──────────────────────────────────────────────────────────────

    public function test_flock_statement_roles(): void
    {
        foreach (['farm_admin', 'accountant', 'partner'] as $role) {
            $this->as($this->member($role))->getJson("/api/statements/flock/{$this->flock->id}")->assertOk();
        }
        $this->as($this->member('supervisor'))->getJson("/api/statements/flock/{$this->flock->id}")->assertForbidden();
    }

    public function test_sale_ticket_roles(): void
    {
        foreach (['farm_admin', 'supervisor', 'accountant'] as $role) {
            $this->as($this->member($role))->getJson("/api/statements/sale/{$this->sale->id}")->assertOk();
        }
        $this->as($this->partnerUser)->getJson("/api/statements/sale/{$this->sale->id}")->assertForbidden();
    }

    public function test_partner_statement_staff_and_own_partner_only(): void
    {
        foreach (['farm_admin', 'accountant'] as $role) {
            $this->as($this->member($role))->getJson("/api/statements/partner/{$this->partner->id}")->assertOk();
        }
        $this->as($this->member('supervisor'))->getJson("/api/statements/partner/{$this->partner->id}")->assertForbidden();

        // الشريك يطلب كشفه
        $this->as($this->partnerUser)->getJson("/api/statements/partner/{$this->partner->id}")->assertOk();

        // ولا يطلب كشف شريك آخر
        $other = Partner::create(['farm_id' => $this->farm->id, 'user_id' => $this->member('partner')->id, 'name' => 'شريك ب', 'status' => 'active']);
        $this->as($this->partnerUser)->getJson("/api/statements/partner/{$other->id}")->assertForbidden();
    }

    public function test_other_farm_records_are_not_found(): void
    {
        $outsider = $this->member('farm_admin', $this->otherFarm);

        $this->as($outsider, $this->otherFarm)->getJson("/api/statements/flock/{$this->flock->id}")->assertNotFound();
        $this->as($outsider, $this->otherFarm)->getJson("/api/statements/partner/{$this->partner->id}")->assertNotFound();
        $this->as($outsider, $this->otherFarm)->getJson("/api/statements/sale/{$this->sale->id}")->assertNotFound();

        // ولا يستطيع طلب مزرعة ليس عضواً فيها
        $this->as($outsider, $this->farm)->getJson("/api/statements/flock/{$this->flock->id}")->assertForbidden();
    }

    public function test_guest_cannot_request_a_link(): void
    {
        $this->getJson("/api/statements/flock/{$this->flock->id}")->assertUnauthorized();
    }

    // ── الرابط الموقّع ─────────────────────────────────────────────────────────

    public function test_signed_links_serve_pdf_without_auth(): void
    {
        $admin = $this->member('farm_admin');
        $uris = [
            "/api/statements/flock/{$this->flock->id}",
            "/api/statements/partner/{$this->partner->id}",
            "/api/statements/sale/{$this->sale->id}",
        ];

        foreach ($uris as $uri) {
            $path = $this->linkFor($admin, $uri);

            $res = $this->get($path); // بلا ترويسات مصادقة
            $res->assertOk();
            $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
            $this->assertStringStartsWith('%PDF', $res->getContent());
        }
    }

    public function test_tampered_signed_link_is_rejected(): void
    {
        $path = $this->linkFor($this->member('farm_admin'), "/api/statements/sale/{$this->sale->id}");

        $otherFarmSale = Sale::factory()->forFlock(Flock::factory()->active()->create(['farm_id' => $this->otherFarm->id]))->create();

        // تغيير المعرّف أو المزرعة أو التوقيع يُبطل الرابط
        $this->get(str_replace("/sale/{$this->sale->id}?", "/sale/{$otherFarmSale->id}?", $path))->assertForbidden();
        $this->get(str_replace("farm={$this->farm->id}", "farm={$this->otherFarm->id}", $path))->assertForbidden();
        $this->get(preg_replace('/signature=[0-9a-f]+/', 'signature=deadbeef', $path))->assertForbidden();
        $this->get("/api/statements/download/sale/{$this->sale->id}?farm={$this->farm->id}")->assertForbidden();
    }

    public function test_signed_link_expires_after_30_minutes(): void
    {
        $path = $this->linkFor($this->member('accountant'), "/api/statements/flock/{$this->flock->id}");

        $this->travel(29)->minutes();
        $this->get($path)->assertOk();

        $this->travel(2)->minutes();
        $this->get($path)->assertForbidden();
    }
}

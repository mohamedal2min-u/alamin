<?php

namespace Tests\Feature\Sale;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleCreateTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsMember(Farm $farm): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    private function salePayload(array $overrides = []): array
    {
        return array_merge([
            'sale_date' => now()->toDateString(),
            'buyer_name' => 'أحمد التاجر',
            'items' => [
                [
                    'birds_count'       => 100,
                    'total_weight_kg'   => 250.0,
                    'unit_price_per_kg' => 15.0,
                ],
            ],
        ], $overrides);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_creates_sale_successfully(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/sales", $this->salePayload())
            ->assertStatus(201)
            ->assertJsonPath('message', 'تم تسجيل البيع بنجاح')
            ->assertJsonStructure([
                'data' => [
                    'id', 'farm_id', 'flock_id', 'sale_date',
                    'gross_amount', 'net_amount', 'payment_status', 'items',
                ],
            ]);

        $this->assertDatabaseHas('sales', [
            'flock_id'   => $flock->id,
            'farm_id'    => $farm->id,
            'buyer_name' => 'أحمد التاجر',
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseHas('sale_items', [
            'flock_id'    => $flock->id,
            'birds_count' => 100,
        ]);
    }

    public function test_computes_amounts_correctly(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        // line_total = 250 * 15 = 3750
        // gross = 3750, discount = 200, net = 3550, received = 1000, remaining = 2550
        $payload = $this->salePayload([
            'discount_amount' => 200,
            'received_amount' => 1000,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/sales", $payload)
            ->assertStatus(201);

        $this->assertEquals(3750, $response->json('data.gross_amount'));
        $this->assertEquals(3550, $response->json('data.net_amount'));
        $this->assertEquals(2550, $response->json('data.remaining_amount'));
        $this->assertEquals('partial', $response->json('data.payment_status'));
    }

    public function test_payment_status_is_paid_when_fully_received(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        // net = 3750, received = 3750 → paid
        $payload = $this->salePayload(['received_amount' => 3750]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/sales", $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.payment_status', 'paid');
    }

    public function test_payment_status_is_debt_when_nothing_received(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $payload = $this->salePayload(['received_amount' => 0]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/sales", $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.payment_status', 'debt');
    }

    public function test_creates_sale_with_multiple_items(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $payload = [
            'sale_date' => now()->toDateString(),
            'items' => [
                ['birds_count' => 100, 'total_weight_kg' => 250.0, 'unit_price_per_kg' => 15.0],
                ['birds_count' => 50,  'total_weight_kg' => 120.0, 'unit_price_per_kg' => 16.0],
            ],
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/sales", $payload)
            ->assertStatus(201);

        // gross = 250*15 + 120*16 = 3750 + 1920 = 5670
        $this->assertEquals(5670, $response->json('data.gross_amount'));
        $this->assertCount(2, $response->json('data.items'));
    }

    public function test_cannot_create_sale_on_closed_flock(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->closed()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/sales", $this->salePayload())
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن تسجيل بيع على فوج غير نشط');
    }

    public function test_requires_at_least_one_item(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/sales", [
                'sale_date' => now()->toDateString(),
                'items'     => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_requires_sale_date(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $payload = $this->salePayload();
        unset($payload['sale_date']);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/sales", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sale_date']);
    }

    public function test_returns_404_for_wrong_farm_flock(): void
    {
        $farm1 = Farm::factory()->create();
        $farm2 = Farm::factory()->create();
        $user  = $this->actingAsMember($farm1);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm2->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm1->id])
            ->postJson("/api/flocks/{$flock->id}/sales", $this->salePayload())
            ->assertStatus(404);
    }

    public function test_requires_authentication(): void
    {
        $farm  = Farm::factory()->create();
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/sales", $this->salePayload())
            ->assertStatus(401);
    }
}

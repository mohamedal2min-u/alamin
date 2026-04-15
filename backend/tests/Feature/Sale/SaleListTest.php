<?php

namespace Tests\Feature\Sale;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleListTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsMember(Farm $farm): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    private function createSaleWithItem(Farm $farm, Flock $flock, array $saleOverrides = []): Sale
    {
        $sale = Sale::factory()->forFlock($flock)->create($saleOverrides);
        SaleItem::factory()->forSale($sale)->create();
        return $sale;
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_list_by_flock_returns_flock_sales(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->createSaleWithItem($farm, $flock);
        $this->createSaleWithItem($farm, $flock);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/flocks/{$flock->id}/sales")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_list_by_flock_does_not_return_other_flock_sales(): void
    {
        $farm   = Farm::factory()->create();
        $user   = $this->actingAsMember($farm);
        $flock1 = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        // فوج واحد نشط فقط مسموح لكل مزرعة — نستخدم closed للثاني
        $flock2 = Flock::factory()->closed()->create(['farm_id' => $farm->id]);

        $this->createSaleWithItem($farm, $flock1);
        $this->createSaleWithItem($farm, $flock2);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/flocks/{$flock1->id}/sales")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_list_all_sales_returns_farm_sales(): void
    {
        $farm   = Farm::factory()->create();
        $user   = $this->actingAsMember($farm);
        $flock1 = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        // فوج واحد نشط فقط مسموح لكل مزرعة — نستخدم closed للثاني
        $flock2 = Flock::factory()->closed()->create(['farm_id' => $farm->id]);

        $this->createSaleWithItem($farm, $flock1);
        $this->createSaleWithItem($farm, $flock2);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson('/api/sales')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_list_all_sales_does_not_return_other_farm_sales(): void
    {
        $farm1  = Farm::factory()->create();
        $farm2  = Farm::factory()->create();
        $user   = $this->actingAsMember($farm1);
        $flock1 = Flock::factory()->active()->create(['farm_id' => $farm1->id]);
        $flock2 = Flock::factory()->active()->create(['farm_id' => $farm2->id]);

        $this->createSaleWithItem($farm1, $flock1);
        $this->createSaleWithItem($farm2, $flock2);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm1->id])
            ->getJson('/api/sales')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_show_returns_sale_with_items(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $sale  = $this->createSaleWithItem($farm, $flock);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson("/api/sales/{$sale->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $sale->id)
            ->assertJsonStructure([
                'data' => [
                    'id', 'farm_id', 'flock_id', 'sale_date',
                    'gross_amount', 'net_amount', 'payment_status',
                    'items' => [['id', 'birds_count', 'total_weight_kg', 'line_total']],
                ],
            ]);
    }

    public function test_show_returns_404_for_wrong_farm(): void
    {
        $farm1 = Farm::factory()->create();
        $farm2 = Farm::factory()->create();
        $user  = $this->actingAsMember($farm1);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm2->id]);
        $sale  = $this->createSaleWithItem($farm2, $flock);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm1->id])
            ->getJson("/api/sales/{$sale->id}")
            ->assertStatus(404);
    }

    public function test_update_payment_changes_status_to_paid(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $sale  = Sale::factory()->forFlock($flock)->debt()->create();

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->patchJson("/api/sales/{$sale->id}/payment", [
                'received_amount' => $sale->net_amount,
            ])
            ->assertStatus(200)
            ->assertJsonPath('message', 'تم تحديث حالة الدفع بنجاح')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.remaining_amount', 0);
    }

    public function test_update_payment_changes_status_to_partial(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $sale  = Sale::factory()->forFlock($flock)->debt()->create(['net_amount' => 1000, 'gross_amount' => 1000]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->patchJson("/api/sales/{$sale->id}/payment", [
                'received_amount' => 400,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.payment_status', 'partial')
            ->assertJsonPath('data.remaining_amount', 600);
    }

    public function test_list_requires_authentication(): void
    {
        $farm = Farm::factory()->create();

        $this->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson('/api/sales')
            ->assertStatus(401);
    }

    public function test_sale_resource_has_expected_fields(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);
        $this->createSaleWithItem($farm, $flock);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->getJson('/api/sales')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    [
                        'id', 'farm_id', 'flock_id', 'sale_date',
                        'buyer_name', 'reference_no',
                        'gross_amount', 'discount_amount', 'net_amount',
                        'received_amount', 'remaining_amount', 'payment_status',
                        'notes', 'created_at', 'updated_at',
                    ],
                ],
            ]);
    }
}

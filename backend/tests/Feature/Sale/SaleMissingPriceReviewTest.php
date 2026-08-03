<?php

namespace Tests\Feature\Sale;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\User;
use App\Services\ReviewQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleMissingPriceReviewTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsMember(Farm $farm): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    public function test_sale_without_price_is_created_and_flows_to_review_then_can_be_priced(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/sales", [
                'sale_date' => now()->toDateString(),
                'items' => [
                    ['birds_count' => 5, 'total_weight_kg' => 20],
                ],
            ])
            ->assertStatus(201);

        $saleId = $response->json('data.id');

        $this->assertDatabaseHas('sales', [
            'id'             => $saleId,
            'gross_amount'   => 0,
            'net_amount'     => 0,
            'payment_status' => 'debt',
        ]);

        $svc   = app(ReviewQueueService::class);
        $queue = $svc->getQueue($farm->id);

        $saleRow = collect($queue['data'])->firstWhere('record_id', $saleId);
        $this->assertNotNull($saleRow, 'unpriced sale should appear in the review queue');
        $this->assertContains('missing_price', $saleRow['review_reasons']);
        $this->assertContains('blocking_flock_closure', $saleRow['review_reasons']);

        // Fill in the price from the review queue.
        $updated = $svc->updateRecord($farm->id, 'sale', $saleId, ['unit_price' => 15.5]);

        $this->assertEquals(310.0, $updated['total_amount']); // 20kg * 15.5
        $this->assertEquals('debt', $updated['payment_status']); // still unpaid, but priced now
        $this->assertNotContains('missing_price', $updated['review_reasons']);
        $this->assertNotContains('blocking_flock_closure', $updated['review_reasons']);

        $this->assertDatabaseHas('sale_items', [
            'sale_id'           => $saleId,
            'unit_price_per_kg' => 15.5,
            'line_total'        => 310.0,
        ]);
    }
}

<?php

namespace Tests\Feature\ReviewQueue;

use App\Models\Expense;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewQueueIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Farm $farm;
    private Flock $activeFlock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user        = User::factory()->create();
        $this->farm        = Farm::factory()->create();
        $this->activeFlock = Flock::factory()->active()->create(['farm_id' => $this->farm->id]);

        FarmUser::factory()->create([
            'farm_id' => $this->farm->id,
            'user_id' => $this->user->id,
            'status'  => 'active',
        ]);
    }

    private function asUser(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->getJson('/api/accounting/review-queue');
    }

    public function test_returns_summary_with_correct_keys(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->getJson('/api/accounting/review-queue')
            ->assertOk()
            ->assertJsonStructure([
                'summary' => [
                    'unpaid_count',
                    'partial_payment_count',
                    'missing_price_count',
                    'missing_payment_status_count',
                    'inconsistent_financial_state_count',
                    'blocking_flock_closure_count',
                ],
                'data',
                'meta' => ['total', 'current_page', 'per_page'],
            ]);
    }

    public function test_unpaid_expense_appears_in_queue(): void
    {
        // Provide quantity + unit_price so only 'unpaid' triggers, not 'missing_price'
        Expense::factory()->create([
            'farm_id'        => $this->farm->id,
            'flock_id'       => $this->activeFlock->id,
            'payment_status' => 'unpaid',
            'paid_amount'    => 0,
            'total_amount'   => 3000,
            'quantity'       => 10,
            'unit_price'     => 300,
            'remaining_amount' => 3000,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->getJson('/api/accounting/review-queue')
            ->assertOk();

        $this->assertGreaterThanOrEqual(1, $response->json('summary.unpaid_count'));
        $this->assertGreaterThanOrEqual(1, $response->json('meta.total'));
    }

    public function test_blocking_count_only_for_active_flock(): void
    {
        // Active flock + unpaid → blocking
        Expense::factory()->create([
            'farm_id'        => $this->farm->id,
            'flock_id'       => $this->activeFlock->id,
            'payment_status' => 'unpaid',
            'paid_amount'    => 0,
            'total_amount'   => 1000,
            'quantity'       => 5,
            'unit_price'     => 200,
            'remaining_amount' => 1000,
        ]);

        $closedFlock = Flock::factory()->create(['farm_id' => $this->farm->id, 'status' => 'closed']);

        // Closed flock + unpaid → NOT blocking
        Expense::factory()->create([
            'farm_id'        => $this->farm->id,
            'flock_id'       => $closedFlock->id,
            'payment_status' => 'unpaid',
            'paid_amount'    => 0,
            'total_amount'   => 500,
            'quantity'       => 5,
            'unit_price'     => 100,
            'remaining_amount' => 500,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->getJson('/api/accounting/review-queue')
            ->assertOk();

        $this->assertEquals(1, $response->json('summary.blocking_flock_closure_count'));
    }

    public function test_fully_paid_priced_expense_excluded_from_queue(): void
    {
        Expense::factory()->create([
            'farm_id'          => $this->farm->id,
            'flock_id'         => $this->activeFlock->id,
            'payment_status'   => 'paid',
            'paid_amount'      => 1000,
            'total_amount'     => 1000,
            'remaining_amount' => 0,
            'quantity'         => 10,
            'unit_price'       => 100,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->getJson('/api/accounting/review-queue')
            ->assertOk();

        $this->assertEquals(0, $response->json('meta.total'));
    }

    public function test_filter_by_type_expense_excludes_sales(): void
    {
        Expense::factory()->create([
            'farm_id'          => $this->farm->id,
            'flock_id'         => $this->activeFlock->id,
            'payment_status'   => 'unpaid',
            'paid_amount'      => 0,
            'total_amount'     => 500,
            'quantity'         => 5,
            'unit_price'       => 100,
            'remaining_amount' => 500,
        ]);

        Sale::factory()->create([
            'farm_id'          => $this->farm->id,
            'flock_id'         => $this->activeFlock->id,
            'payment_status'   => 'unpaid',
            'received_amount'  => 0,
            'remaining_amount' => 2000,
            'net_amount'       => 2000,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->getJson('/api/accounting/review-queue?type=expense')
            ->assertOk();

        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('expense', $response->json('data.0.type'));
    }

    public function test_missing_price_triggers_missing_price_reason(): void
    {
        Expense::factory()->create([
            'farm_id'        => $this->farm->id,
            'flock_id'       => $this->activeFlock->id,
            'payment_status' => 'paid',
            'paid_amount'    => 0,
            'total_amount'   => 0,
            'quantity'       => 10,
            'unit_price'     => null,
            'remaining_amount' => 0,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->getJson('/api/accounting/review-queue')
            ->assertOk();

        $this->assertGreaterThanOrEqual(1, $response->json('summary.missing_price_count'));
        $this->assertGreaterThanOrEqual(1, $response->json('summary.blocking_flock_closure_count'));
    }
}

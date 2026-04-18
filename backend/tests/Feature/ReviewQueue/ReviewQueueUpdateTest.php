<?php

namespace Tests\Feature\ReviewQueue;

use App\Models\Expense;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewQueueUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Farm $farm;
    private Flock $flock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user  = User::factory()->create();
        $this->farm  = Farm::factory()->create();
        $this->flock = Flock::factory()->active()->create(['farm_id' => $this->farm->id]);

        FarmUser::factory()->create([
            'farm_id' => $this->farm->id,
            'user_id' => $this->user->id,
            'status'  => 'active',
        ]);
    }

    public function test_patch_updates_paid_amount_and_recalculates(): void
    {
        $expense = Expense::factory()->create([
            'farm_id'          => $this->farm->id,
            'flock_id'         => $this->flock->id,
            'total_amount'     => 1000,
            'paid_amount'      => 0,
            'remaining_amount' => 1000,
            'payment_status'   => 'unpaid',
            'quantity'         => 10,
            'unit_price'       => 100,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->patchJson("/api/accounting/review-queue/expense/{$expense->id}", [
                'paid_amount' => 600,
            ])
            ->assertOk();

        $this->assertEquals(600.0, $response->json('paid_amount'));
        $this->assertEquals(400.0, $response->json('remaining_amount'));
        $this->assertEquals('partial', $response->json('payment_status'));
        $this->assertContains('partial_payment', $response->json('review_reasons'));
    }

    public function test_patch_full_payment_clears_reasons(): void
    {
        $expense = Expense::factory()->create([
            'farm_id'          => $this->farm->id,
            'flock_id'         => $this->flock->id,
            'total_amount'     => 1000,
            'paid_amount'      => 0,
            'remaining_amount' => 1000,
            'payment_status'   => 'unpaid',
            'quantity'         => 10,
            'unit_price'       => 100,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->patchJson("/api/accounting/review-queue/expense/{$expense->id}", [
                'paid_amount' => 1000,
            ])
            ->assertOk();

        $this->assertEquals('paid', $response->json('payment_status'));
        $this->assertEmpty($response->json('review_reasons'));
    }

    public function test_patch_expense_unit_price_recalculates_total(): void
    {
        $expense = Expense::factory()->create([
            'farm_id'          => $this->farm->id,
            'flock_id'         => $this->flock->id,
            'quantity'         => 10,
            'unit_price'       => null,
            'total_amount'     => 0,
            'paid_amount'      => 0,
            'remaining_amount' => 0,
            'payment_status'   => 'unpaid',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->patchJson("/api/accounting/review-queue/expense/{$expense->id}", [
                'unit_price' => 50,
            ])
            ->assertOk();

        $this->assertEquals(500.0, $response->json('total_amount'));
        $this->assertNotContains('missing_price', $response->json('review_reasons'));
    }

    public function test_invalid_type_returns_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->patchJson('/api/accounting/review-queue/inventory/1', ['paid_amount' => 100])
            ->assertStatus(422);
    }
}

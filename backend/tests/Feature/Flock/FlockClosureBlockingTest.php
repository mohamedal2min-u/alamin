<?php

namespace Tests\Feature\Flock;

use App\Models\Expense;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlockClosureBlockingTest extends TestCase
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

    private function tryClose(array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $this->farm->id])
            ->putJson("/api/flocks/{$this->flock->id}", array_merge(['status' => 'closed'], $data));
    }

    public function test_cannot_close_flock_with_unpaid_expense(): void
    {
        Expense::factory()->create([
            'farm_id'          => $this->farm->id,
            'flock_id'         => $this->flock->id,
            'payment_status'   => 'unpaid',
            'paid_amount'      => 0,
            'total_amount'     => 2000,
            'quantity'         => 10,
            'unit_price'       => 200,
            'remaining_amount' => 2000,
        ]);

        $this->tryClose()
            ->assertStatus(422)
            ->assertJsonFragment(['blocking_count' => 1]);
    }

    public function test_cannot_close_flock_with_partial_sale(): void
    {
        Sale::factory()->create([
            'farm_id'          => $this->farm->id,
            'flock_id'         => $this->flock->id,
            'payment_status'   => 'partial',
            'received_amount'  => 500,
            'remaining_amount' => 1500,
            'net_amount'       => 2000,
        ]);

        $this->tryClose()
            ->assertStatus(422)
            ->assertJsonFragment(['blocking_count' => 1]);
    }

    public function test_cannot_close_flock_with_missing_price_expense(): void
    {
        // Expense with unit_price=null (paid status) still blocks via missing_price rule
        Expense::factory()->create([
            'farm_id'          => $this->farm->id,
            'flock_id'         => $this->flock->id,
            'payment_status'   => 'paid',
            'paid_amount'      => 0,
            'total_amount'     => 0,
            'unit_price'       => null,
            'quantity'         => 10,
            'remaining_amount' => 0,
        ]);

        $this->tryClose()
            ->assertStatus(422)
            ->assertJsonFragment(['blocking_count' => 1]);
    }

    public function test_cannot_close_flock_with_zero_total_sale(): void
    {
        Sale::factory()->create([
            'farm_id'          => $this->farm->id,
            'flock_id'         => $this->flock->id,
            'payment_status'   => 'paid',
            'received_amount'  => 0,
            'remaining_amount' => 0,
            'net_amount'       => 0,
        ]);

        $this->tryClose()
            ->assertStatus(422)
            ->assertJsonFragment(['blocking_count' => 1]);
    }

    public function test_can_close_flock_when_all_records_paid_and_priced(): void
    {
        Expense::factory()->create([
            'farm_id'          => $this->farm->id,
            'flock_id'         => $this->flock->id,
            'payment_status'   => 'paid',
            'paid_amount'      => 1000,
            'total_amount'     => 1000,
            'remaining_amount' => 0,
            'unit_price'       => 10,
            'quantity'         => 100,
        ]);

        $this->tryClose()->assertOk();
    }
}

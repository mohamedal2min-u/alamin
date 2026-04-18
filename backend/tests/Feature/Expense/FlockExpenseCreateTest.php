<?php

namespace Tests\Feature\Expense;

use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlockExpenseCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ExpenseCategory::factory()->water()->create();
        ExpenseCategory::factory()->bedding()->create();
        ExpenseCategory::factory()->other()->create();
    }

    private function actingAsMember(Farm $farm): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->create(['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    public function test_creates_expense_successfully(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/expenses", [
                'expense_type' => 'water',
                'quantity'     => 10,
                'unit_price'   => 15,
                'total_amount' => 150,
                'notes'        => 'مياه شرب',
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'تم تسجيل المصروف بنجاح');

        $this->assertDatabaseHas('expenses', [
            'flock_id'       => $flock->id,
            'expense_type'   => 'water',
            'total_amount'   => 150,
            'payment_status' => 'paid',
            'created_by'     => $user->id,
        ]);
    }

    public function test_creates_bedding_expense(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/expenses", [
                'expense_type' => 'bedding',
                'quantity'     => 5,
                'total_amount' => 500,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('expenses', ['flock_id' => $flock->id, 'expense_type' => 'bedding']);
    }

    public function test_cannot_add_expense_on_closed_flock(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->closed()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/expenses", ['expense_type' => 'water', 'quantity' => 5, 'total_amount' => 100])
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن تسجيل مصروف على فوج غير نشط');
    }

    public function test_rejects_invalid_expense_type(): void
    {
        $farm  = Farm::factory()->create();
        $user  = $this->actingAsMember($farm);
        $flock = Flock::factory()->active()->create(['farm_id' => $farm->id]);

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Farm-Id' => $farm->id])
            ->postJson("/api/flocks/{$flock->id}/expenses", ['expense_type' => 'invalid', 'total_amount' => 100])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['expense_type']);
    }
}

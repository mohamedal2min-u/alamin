<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\Flock;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 50, 1000);
        return [
            'farm_id'             => Farm::factory(),
            'flock_id'            => Flock::factory(),
            'expense_category_id' => ExpenseCategory::factory(),
            'entry_date'          => now()->toDateString(),
            'expense_type'        => 'other',
            'quantity'            => null,
            'unit_price'          => null,
            'total_amount'        => $amount,
            'paid_amount'         => $amount,
            'remaining_amount'    => 0,
            'payment_status'      => 'paid',
            'notes'               => null,
            'created_by'          => null,
            'updated_by'          => null,
        ];
    }
}

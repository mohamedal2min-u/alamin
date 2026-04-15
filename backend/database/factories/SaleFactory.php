<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sale>
 */
class SaleFactory extends Factory
{
    public function definition(): array
    {
        $grossAmount    = $this->faker->randomFloat(2, 500, 50000);
        $discountAmount = 0;
        $netAmount      = $grossAmount - $discountAmount;
        $receivedAmount = $netAmount;
        $remainingAmount = 0;

        return [
            'farm_id'                 => Farm::factory(),
            'flock_id'                => Flock::factory(),
            'sale_date'               => now()->toDateString(),
            'reference_no'            => null,
            'buyer_name'              => $this->faker->name(),
            'invoice_attachment_path' => null,
            'gross_amount'            => $grossAmount,
            'discount_amount'         => $discountAmount,
            'net_amount'              => $netAmount,
            'received_amount'         => $receivedAmount,
            'remaining_amount'        => $remainingAmount,
            'payment_status'          => 'paid',
            'notes'                   => null,
            'created_by'              => null,
            'updated_by'              => null,
        ];
    }

    public function forFlock(Flock $flock): static
    {
        return $this->state([
            'farm_id'  => $flock->farm_id,
            'flock_id' => $flock->id,
        ]);
    }

    public function debt(): static
    {
        $grossAmount = $this->faker->randomFloat(2, 500, 50000);
        return $this->state([
            'gross_amount'    => $grossAmount,
            'discount_amount' => 0,
            'net_amount'      => $grossAmount,
            'received_amount' => 0,
            'remaining_amount' => $grossAmount,
            'payment_status'  => 'debt',
        ]);
    }

    public function partial(): static
    {
        $grossAmount    = $this->faker->randomFloat(2, 1000, 50000);
        $receivedAmount = round($grossAmount / 2, 2);
        return $this->state([
            'gross_amount'    => $grossAmount,
            'discount_amount' => 0,
            'net_amount'      => $grossAmount,
            'received_amount' => $receivedAmount,
            'remaining_amount' => round($grossAmount - $receivedAmount, 2),
            'payment_status'  => 'partial',
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Flock;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SaleItem>
 */
class SaleItemFactory extends Factory
{
    public function definition(): array
    {
        $birdsCount    = $this->faker->numberBetween(50, 500);
        $totalWeightKg = round($birdsCount * $this->faker->randomFloat(3, 1.5, 3.5), 3);
        $unitPrice     = $this->faker->randomFloat(4, 10, 25);
        $lineTotal     = round($totalWeightKg * $unitPrice, 2);

        return [
            'sale_id'           => Sale::factory(),
            'farm_id'           => Farm::factory(),
            'flock_id'          => Flock::factory(),
            'birds_count'       => $birdsCount,
            'total_weight_kg'   => $totalWeightKg,
            'avg_weight_kg'     => round($totalWeightKg / $birdsCount, 3),
            'unit_price_per_kg' => $unitPrice,
            'line_total'        => $lineTotal,
            'notes'             => null,
        ];
    }

    public function forSale(Sale $sale): static
    {
        return $this->state([
            'sale_id'  => $sale->id,
            'farm_id'  => $sale->farm_id,
            'flock_id' => $sale->flock_id,
        ]);
    }
}

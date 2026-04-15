<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Flock;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

class FlockMedicineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id'                  => Farm::factory(),
            'flock_id'                 => Flock::factory(),
            'item_id'                  => Item::factory(),
            'entry_date'               => now()->toDateString(),
            'quantity'                 => $this->faker->randomFloat(2, 1, 100),
            'unit_label'               => null,
            'notes'                    => null,
            'worker_id'                => null,
            'inventory_transaction_id' => null,
            'editable_until'           => now()->addMinutes(15),
            'created_by'               => null,
            'updated_by'               => null,
        ];
    }
}

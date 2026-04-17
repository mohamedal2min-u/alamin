<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Flock;
use Illuminate\Database\Eloquent\Factories\Factory;

class FlockWaterLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id'        => Farm::factory(),
            'flock_id'       => Flock::factory(),
            'entry_date'     => now()->toDateString(),
            'quantity'       => $this->faker->randomFloat(2, 10, 500),
            'unit_label'     => 'صهريج',
            'notes'          => null,
            'worker_id'      => null,
            'editable_until' => now()->addMinutes(15),
            'created_by'     => null,
            'updated_by'     => null,
        ];
    }
}

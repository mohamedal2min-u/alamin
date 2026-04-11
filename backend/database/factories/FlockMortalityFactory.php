<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FlockMortality>
 */
class FlockMortalityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id'        => Farm::factory(),
            'flock_id'       => Flock::factory(),
            'entry_date'     => now()->toDateString(),
            'quantity'       => $this->faker->numberBetween(1, 50),
            'reason'         => null,
            'notes'          => null,
            'worker_id'      => null,
            'editable_until' => now()->addMinutes(15),
            'created_by'     => null,
            'updated_by'     => null,
        ];
    }

    public function forFlock(Flock $flock): static
    {
        return $this->state([
            'farm_id'  => $flock->farm_id,
            'flock_id' => $flock->id,
        ]);
    }

    public function withReason(string $reason): static
    {
        return $this->state(['reason' => $reason]);
    }
}

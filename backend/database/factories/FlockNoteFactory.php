<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Flock;
use Illuminate\Database\Eloquent\Factories\Factory;

class FlockNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id'    => Farm::factory(),
            'flock_id'   => Flock::factory(),
            'note_type'  => $this->faker->randomElement(['general', 'instruction', 'operational', 'alert']),
            'note_text'  => $this->faker->sentence(),
            'entry_date' => now()->toDateString(),
            'worker_id'  => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}

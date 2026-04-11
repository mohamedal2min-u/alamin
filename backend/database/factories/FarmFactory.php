<?php

namespace Database\Factories;

use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Farm>
 */
class FarmFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'   => fake()->company() . ' Farm',
            'status' => 'active',
        ];
    }

    public function pendingSetup(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'pending_setup']);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'suspended']);
    }
}

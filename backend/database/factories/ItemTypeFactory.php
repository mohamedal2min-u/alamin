<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ItemTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id'   => null,
            'name'      => $this->faker->word(),
            'code'      => $this->faker->unique()->slug(1),
            'is_system' => true,
            'is_active' => true,
        ];
    }

    public function feed(): static
    {
        return $this->state(['code' => 'feed', 'name' => 'أعلاف']);
    }

    public function medicine(): static
    {
        return $this->state(['code' => 'medicine', 'name' => 'أدوية']);
    }
}

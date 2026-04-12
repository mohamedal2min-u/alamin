<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id'    => null,
            'name'       => $this->faker->word(),
            'code'       => $this->faker->unique()->slug(1),
            'is_system'  => true,
            'is_active'  => true,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function water(): static
    {
        return $this->state(['code' => 'water', 'name' => 'مياه']);
    }

    public function bedding(): static
    {
        return $this->state(['code' => 'bedding', 'name' => 'فرشة']);
    }

    public function other(): static
    {
        return $this->state(['code' => 'other', 'name' => 'أخرى']);
    }
}

<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FarmUser>
 */
class FarmUserFactory extends Factory
{
    protected $model = FarmUser::class;

    public function definition(): array
    {
        return [
            'farm_id'    => Farm::factory(),
            'user_id'    => User::factory(),
            'status'     => 'active',
            'is_primary' => true,
            'joined_at'  => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'inactive']);
    }
}

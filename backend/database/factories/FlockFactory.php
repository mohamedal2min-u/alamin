<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Flock>
 */
class FlockFactory extends Factory
{
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-60 days', 'now');

        return [
            'farm_id'          => Farm::factory(),
            'name'             => 'فوج ' . $this->faker->monthName() . ' ' . $this->faker->year(),
            'status'           => 'draft',
            'start_date'       => $startDate->format('Y-m-d'),
            'close_date'       => null,
            'initial_count'    => $this->faker->numberBetween(1000, 20000),
            'current_age_days' => null,
            'notes'            => null,
            'created_by'       => null,
            'updated_by'       => null,
        ];
    }

    // ── حالات الفوج ───────────────────────────────────────────────────────────

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function active(): static
    {
        return $this->state([
            'status'     => 'active',
            'start_date' => now()->subDays(15)->toDateString(),
        ]);
    }

    public function closed(): static
    {
        $start = now()->subDays(55);
        return $this->state([
            'status'     => 'closed',
            'start_date' => $start->toDateString(),
            'close_date' => now()->subDays(5)->toDateString(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status'     => 'cancelled',
            'close_date' => now()->toDateString(),
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\ItemType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id'       => Farm::factory(),
            'item_type_id'  => ItemType::factory(),
            'name'          => $this->faker->word() . ' ' . $this->faker->randomNumber(2),
            'input_unit'    => 'كيس',
            'unit_value'    => 50,
            'content_unit'  => 'كغ',
            'minimum_stock' => null,
            'default_cost'  => null,
            'status'        => 'active',
            'notes'         => null,
            'created_by'    => null,
            'updated_by'    => null,
        ];
    }

    public function forFarm(Farm $farm): static
    {
        return $this->state(['farm_id' => $farm->id]);
    }
}

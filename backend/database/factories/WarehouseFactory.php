<?php

namespace Database\Factories;

use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id'    => Farm::factory(),
            'name'       => 'المستودع الرئيسي',
            'location'   => null,
            'is_active'  => true,
            'notes'      => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function forFarm(Farm $farm): static
    {
        return $this->state(['farm_id' => $farm->id]);
    }
}

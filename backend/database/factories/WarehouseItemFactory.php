<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Item;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id'          => Farm::factory(),
            'warehouse_id'     => Warehouse::factory(),
            'item_id'          => Item::factory(),
            'current_quantity' => 1000,
            'average_cost'     => null,
            'status'           => 'active',
        ];
    }
}

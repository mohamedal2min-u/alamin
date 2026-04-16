<?php

namespace Database\Seeders;

use App\Models\ItemType;
use Illuminate\Database\Seeder;

class ItemTypeSeeder extends Seeder
{
    /**
     * أنواع الأصناف الافتراضية للنظام (is_system = true, farm_id = null)
     */
    public function run(): void
    {
        $types = [
            ['code' => 'feed',     'name' => 'علف'],
            ['code' => 'medicine', 'name' => 'دواء'],
            ['code' => 'charcoal', 'name' => 'فحم'],
            ['code' => 'water',    'name' => 'ماء'],
            ['code' => 'other',    'name' => 'أخرى'],
        ];

        foreach ($types as $type) {
            ItemType::firstOrCreate(
                ['code' => $type['code'], 'farm_id' => null],
                [
                    'name'      => $type['name'],
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('✔ ItemTypeSeeder: seeded feed / medicine / other');
    }
}

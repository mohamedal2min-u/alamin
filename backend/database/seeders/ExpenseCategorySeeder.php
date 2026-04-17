<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'water',        'name' => 'مياه'],
            ['code' => 'bedding',      'name' => 'نشارة'],
            ['code' => 'farm_wash',    'name' => 'غسيل مدجنة'],
            ['code' => 'disinfectant', 'name' => 'معقم'],
            ['code' => 'other',        'name' => 'أخرى'],
        ];

        foreach ($categories as $cat) {
            ExpenseCategory::firstOrCreate(
                ['code' => $cat['code'], 'farm_id' => null],
                [
                    'name'      => $cat['name'],
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }
    }
}

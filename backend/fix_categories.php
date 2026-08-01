<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Flock;
use App\Models\Expense;
use App\Models\Item;
use App\Models\ExpenseCategory;

$flock = Flock::where('status', 'active')->first();
if (!$flock) {
    echo "No active flock found.\n";
    exit;
}

$feedCat = ExpenseCategory::where('code', 'feed')->first();
$medCat = ExpenseCategory::where('code', 'medicine')->first();

if (!$feedCat || !$medCat) {
    echo "Missing category for feed or medicine.\n";
    exit;
}

$expenses = Expense::where('flock_id', $flock->id)
    ->where('description', 'LIKE', '%دين شراء تلقائي%')
    ->get();

$fixed = 0;
foreach ($expenses as $expense) {
    // Extract item name
    $desc = $expense->description;
    $desc = str_replace('دين شراء تلقائي من استهلاك العلف: ', '', $desc);
    $desc = str_replace('دين شراء تلقائي من استهلاك الدواء: ', '', $desc);
    $desc = str_replace('دين شراء تلقائي: ', '', $desc);
    
    $itemName = trim($desc);
    $item = Item::where('name', $itemName)->first();
    
    if ($item) {
        $updated = false;
        if ($item->category === 'feed') {
            if ($expense->expense_category_id !== $feedCat->id) {
                $expense->expense_category_id = $feedCat->id;
                $updated = true;
            }
            if ($expense->description !== 'دين شراء تلقائي: ' . $itemName) {
                $expense->description = 'دين شراء تلقائي: ' . $itemName;
                $updated = true;
            }
        } elseif ($item->category === 'medicine') {
            if ($expense->expense_category_id !== $medCat->id) {
                $expense->expense_category_id = $medCat->id;
                $updated = true;
            }
            if ($expense->description !== 'دين شراء تلقائي: ' . $itemName) {
                $expense->description = 'دين شراء تلقائي: ' . $itemName;
                $updated = true;
            }
        }
        
        if ($updated) {
            $expense->save();
            echo "Fixed expense for item: {$itemName}\n";
            $fixed++;
        }
    } else {
        echo "Item not found for description: {$expense->description}\n";
    }
}

echo "Fixed categories for {$fixed} auto-purchases.\n";

// Let's also check the missing "علف مرحلة ثانية"
$feed2 = Item::where('name', 'LIKE', '%مرحلة ثانية%')->orWhere('name', 'LIKE', '%مرحله ثانيه%')->first();
if ($feed2) {
    echo "Found stage 2 feed: {$feed2->name} (category: {$feed2->category}, active: {$feed2->is_active})\n";
} else {
    echo "Stage 2 feed NOT FOUND in database. Creating it...\n";
    Item::create([
        'farm_id' => $flock->farm_id,
        'category' => 'feed',
        'name' => 'علف مرحلة ثانية',
        'input_unit' => 'kg',
        'output_unit' => 'kg',
        'unit_value' => 1,
        'default_cost' => 0,
        'is_active' => true,
    ]);
    echo "Created 'علف مرحلة ثانية'.\n";
}

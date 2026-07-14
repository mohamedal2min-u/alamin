<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FlockFeedLog;
use App\Models\FlockMedicine;
use App\Models\Item;
use Illuminate\Support\Str;

$farmId = \App\Models\Farm::first()->id;
$flockId = \App\Models\Flock::where('status', 'active')->first()->id ?? null;
$userId = \App\Models\User::first()->id;

// Ensure categories exist
$feedCat = ExpenseCategory::firstOrCreate(
    ['code' => 'feed', 'farm_id' => null],
    ['name' => 'علف', 'is_system' => true, 'is_active' => true, 'created_by' => $userId, 'updated_by' => $userId]
);

$medCat = ExpenseCategory::firstOrCreate(
    ['code' => 'medicine', 'farm_id' => null],
    ['name' => 'دواء', 'is_system' => true, 'is_active' => true, 'created_by' => $userId, 'updated_by' => $userId]
);

// Get total feed consumed
$feedLogs = FlockFeedLog::selectRaw('item_id, SUM(quantity) as total')->groupBy('item_id')->get();
foreach($feedLogs as $log) {
    $item = Item::find($log->item_id);
    if(!$item) continue;
    
    $existing = Expense::where('description', 'LIKE', '%تلقائي من استهلاك العلف: ' . $item->name . '%')
        ->first();
        
    if(!$existing && $log->total > 0) {
        Expense::create([
            'farm_id' => $log->first()->farm_id ?? $farmId,
            'flock_id' => $flockId,
            'expense_category_id' => $feedCat->id,
            'entry_date' => now()->toDateString(),
            'description' => 'دين شراء تلقائي من استهلاك العلف: ' . $item->name,
            'quantity' => $log->total,
            'unit_price' => 0, // Unpriced
            'total_amount' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'payment_status' => 'unpaid',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
        echo "Created feed expense for " . $item->name . "\n";
    }
}

// Get total medicine consumed
$medicineLogs = FlockMedicine::selectRaw('item_id, SUM(quantity) as total')->groupBy('item_id')->get();
foreach($medicineLogs as $log) {
    $item = Item::find($log->item_id);
    if(!$item) continue;
    
    $existing = Expense::where('description', 'LIKE', '%تلقائي من استهلاك الدواء: ' . $item->name . '%')
        ->first();
        
    if(!$existing && $log->total > 0) {
        Expense::create([
            'farm_id' => $log->first()->farm_id ?? $farmId,
            'flock_id' => $flockId,
            'expense_category_id' => $medCat->id,
            'entry_date' => now()->toDateString(),
            'description' => 'دين شراء تلقائي من استهلاك الدواء: ' . $item->name,
            'quantity' => $log->total,
            'unit_price' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'payment_status' => 'unpaid',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
        echo "Created medicine expense for " . $item->name . "\n";
    }
}

echo "Done.\n";

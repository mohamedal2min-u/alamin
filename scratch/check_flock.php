<?php
require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Flock;

$f = Flock::find(1);
if (!$f) {
    echo "Flock not found".PHP_EOL;
    exit;
}

echo "--- Flock 1 breakdown ---" . PHP_EOL;
echo "Chick Cost: " . $f->total_chick_cost . PHP_EOL;
echo "Expenses Sum: " . $f->expenses()->sum('total_amount') . PHP_EOL;
echo "Water Sum: " . $f->waterLogs()->sum('total_amount') . PHP_EOL;
echo "Inventory Sum: " . $f->inventoryTransactions()->where('direction', 'out')->where('transaction_type', 'consumption')->sum('total_amount') . PHP_EOL;
echo "-------------------------" . PHP_EOL;
echo "Total Calculated: " . ($f->total_chick_cost + $f->expenses()->sum('total_amount') + $f->waterLogs()->sum('total_amount') + $f->inventoryTransactions()->where('direction', 'out')->where('transaction_type', 'consumption')->sum('total_amount')) . PHP_EOL;

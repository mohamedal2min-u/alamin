<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$collection = collect([
    ['id' => 1, 'item_id' => null, 'quantity' => 5, 'item' => null]
]);

$grouped = $collection->groupBy('item_id');

$dailyMeds = [];
$dayMedsTotal = 0;
foreach ($grouped as $entries) {
    $first = $entries->first();
    $qty = $entries->sum('quantity');
    $dailyMeds[] = [
        'name' => $first['item'] ? $first['item']['name'] : 'صنف غير معروف',
        'quantity' => (float) $qty,
        'unit' => $first['unit_label'] ?? 'عبوة'
    ];
    $dayMedsTotal += $qty;
}

echo json_encode(['dailyMeds' => $dailyMeds, 'total' => $dayMedsTotal], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

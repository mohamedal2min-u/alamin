<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$farmId = App\Models\Farm::first()->id;
$queue = (new App\Services\ReviewQueueService())->getQueue($farmId);
foreach($queue['data'] as $item) {
    echo $item['type'] . ' - ' . $item['description'] . " (Qty: " . ($item['quantity'] ?? 'null') . " " . ($item['quantity_unit'] ?? '') . ")\n";
}

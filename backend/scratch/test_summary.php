<?php
// backend/scratch/test_summary.php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use App\Actions\Flock\TodaySummaryAction;
use App\Models\Flock;

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$flock = Flock::where('status', 'active')->first();
if (!$flock) {
    echo "No active flock found.\n";
    exit;
}

$action = new TodaySummaryAction();
$summary = $action->execute($flock);

echo "--- Summary for Flock: " . $flock->name . " ---\n";
echo "Date: " . $summary['date'] . "\n";
echo "Mortality Total: " . $summary['mortalities']['total'] . "\n";
echo "Mortality Entries Count: " . count($summary['mortalities']['entries']) . "\n";
echo "Feed Total: " . $summary['feed']['total'] . "\n";
echo "JSON Output: " . json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

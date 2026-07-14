<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cats = \App\Models\ExpenseCategory::all();
foreach($cats as $cat) {
    echo $cat->id . " | " . $cat->name . " | " . $cat->code . "\n";
}

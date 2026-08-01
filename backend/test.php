<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$action = app(\App\Actions\Auth\LoginAction::class);
try {
    $action->execute(['login' => 'test@test.com', 'password' => 'password']);
    echo "Success\n";
} catch (\Exception $e) {
    echo "Exception Code: " . $e->getCode() . "\n";
    echo "Exception Message: " . $e->getMessage() . "\n";
}

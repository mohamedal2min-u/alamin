<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

echo "--- User List ---\n";
try {
    $users = User::all();
    foreach ($users as $user) {
        echo "Name: {$user->name} | WhatsApp: {$user->whatsapp} | Email: {$user->email}\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "--- End ---\n";

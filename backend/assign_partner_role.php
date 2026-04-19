<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

echo "--- Assigning Role ---\n";
try {
    $user = User::where('whatsapp', '46720450450')->first();
    if ($user) {
        $user->syncRoles(['partner']);
        echo "Role 'partner' successfully assigned to: {$user->name} ({$user->whatsapp})\n";
    } else {
        echo "User not found.\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "--- End ---\n";

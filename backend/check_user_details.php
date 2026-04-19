<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$whatsapp = '46720450450'; // The one I saw in DB
$search = '64720450450'; // The one user asked about

echo "--- User Detail Check ---\n";
try {
    $u1 = User::where('whatsapp', $whatsapp)->first();
    $u2 = User::where('whatsapp', $search)->first();
    
    $user = $u1 ?: $u2;
    
    if ($user) {
        echo "Found User:\n";
        echo "Name: {$user->name}\n";
        echo "WhatsApp: {$user->whatsapp}\n";
        echo "Email: {$user->email}\n";
        echo "Status: {$user->status}\n";
        echo "Roles: " . implode(', ', $user->getRoleNames()->toArray()) . "\n";
    } else {
        echo "No user found with WhatsApp 64720450450 or 46720450450\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "--- End ---\n";

<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "--- Setting up Partner Account ---\n";
try {
    $user = User::updateOrCreate(
        ['whatsapp' => '000111'],
        [
            'name' => 'شريك تجريبي',
            'email' => 'partner_test@alamin.se',
            'password' => Hash::make('12345678'),
            'status' => 'active'
        ]
    );
    // You might need to assign a role if using Spatie
    // $user->assignRole('partner');
    
    echo "Partner account ready:\n";
    echo "WhatsApp: 000111\n";
    echo "Password: 12345678\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "--- End ---\n";

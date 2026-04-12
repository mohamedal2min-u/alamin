<?php

use App\Models\Partner;
use App\Models\Farm;
use App\Models\User;
use App\Models\FarmPartnerShare;
use App\Http\Controllers\Api\Partner\PartnerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// --- Setup ---
$farmId = 1;
$farm = Farm::find($farmId);
$adminUser = User::find($farm->admin_user_id);

$controller = new PartnerController();

function getAdminShare($farmId) {
    $farm = Farm::find($farmId);
    $adminPartner = Partner::where('farm_id', $farmId)->where('user_id', $farm->admin_user_id)->first();
    if (!$adminPartner) return 'N/A';
    $share = $adminPartner->shares()->where('is_active', true)->first();
    return $share ? $share->share_percent : '0';
}

echo "Initial Admin Share: " . getAdminShare($farmId) . "%\n";

// --- Case 1: Add new partner (20%) ---
echo "\nCase 1: Adding Partner A (20%)\n";
try {
    $req1 = new Request([], [
        'name' => 'Partner A',
        'whatsapp' => '0511111111',
        'share_percent' => 20,
    ]);
    $req1->attributes->set('farm_id', $farmId);
    $req1->setMethod('POST');
    $req1->setUserResolver(fn() => $adminUser);
    
    $controller->store($req1);
    echo "Result: Success. Admin Share: " . getAdminShare($farmId) . "%\n";
} catch (\Exception $e) {
    echo "Result: Failed - " . $e->getMessage() . "\n";
}

// --- Case 2: Update partner share (20% -> 50%) ---
echo "\nCase 2: Updating Partner A share to 50%\n";
try {
    $partnerA = Partner::where('name', 'Partner A')->where('farm_id', $farmId)->first();
    $req2 = new Request([], [
        'name' => 'Partner A',
        'whatsapp' => '0511111111',
        'status' => 'active',
        'share_percent' => 50,
    ]);
    $req2->attributes->set('farm_id', $farmId);
    $req2->setMethod('PUT');
    $req2->setUserResolver(fn() => $adminUser);
    
    $controller->update($req2, $partnerA);
    echo "Result: Success. Admin Share: " . getAdminShare($farmId) . "%\n";
} catch (\Exception $e) {
    echo "Result: Failed - " . $e->getMessage() . "\n";
}

// --- Case 3: Attempting to break 100% rule ---
echo "\nCase 3: Attempting to break 100% rule (Partner A = 60%, when Admin has 50%)\n";
try {
    $partnerA = Partner::where('name', 'Partner A')->where('farm_id', $farmId)->first();
    // Admin has 50% (from Case 2). 
    // If I set Partner A to 60%, it needs 10% more from admin. Correct.
    // If I set Partner A to 110%, it should fail validation.
    $req3 = new Request([], [
        'name' => 'Partner A',
        'whatsapp' => '0511111111',
        'status' => 'active',
        'share_percent' => 110,
    ]);
    $req3->attributes->set('farm_id', $farmId);
    $req3->setMethod('PUT');
    $req3->setUserResolver(fn() => $adminUser);
    
    $controller->update($req3, $partnerA);
    echo "Result: Success (SHOULD HAVE FAILED). Admin Share: " . getAdminShare($farmId) . "%\n";
} catch (\Exception $e) {
    echo "Result: Expected Failure - " . $e->getMessage() . "\n";
}

echo "\nCase 3b: Attempting to break 100% rule (Setting Partner A to 70%, when Admin only has 50%)\n";
try {
    $partnerA = Partner::where('name', 'Partner A')->where('farm_id', $farmId)->first();
    $req3b = new Request([], [
        'name' => 'Partner A',
        'whatsapp' => '0511111111',
        'status' => 'active',
        'share_percent' => 101, // Admin has 50. Partner A has 50. Total 100.
    ]);
    $req3b->attributes->set('farm_id', $farmId);
    $req3b->setMethod('PUT');
    $req3b->setUserResolver(fn() => $adminUser);
    
    $controller->update($req3b, $partnerA);
    echo "Result: Success (SHOULD HAVE FAILED). Admin Share: " . getAdminShare($farmId) . "%\n";
} catch (\Exception $e) {
    echo "Result: Expected Failure - " . $e->getMessage() . "\n";
}

// --- Case 4: Delete/Deactivate Partner ---
echo "\nCase 4: Deactivating/Removing Partner A\n";
try {
    $partnerA = Partner::where('name', 'Partner A')->where('farm_id', $farmId)->first();
    $req4 = new Request([], []);
    $req4->attributes->set('farm_id', $farmId);
    $req4->setMethod('DELETE');
    $req4->setUserResolver(fn() => $adminUser);
    
    $controller->destroy($req4, $partnerA);
    echo "Result: Success. Admin Share: " . getAdminShare($farmId) . "%\n";
} catch (\Exception $e) {
    echo "Result: Failed - " . $e->getMessage() . "\n";
}
echo "Done.\n";

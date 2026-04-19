<?php
require '/home/alamin-api/app/backend/vendor/autoload.php';
$app = require_once '/home/alamin-api/app/backend/bootstrap/app.php';
$app->boot();
$user = App\Models\User::first();
$token = $user->createToken('debug-test');
echo $token->plainTextToken . "\n";

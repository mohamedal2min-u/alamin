<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Flock\FlockController;
use App\Http\Controllers\Api\Mortality\MortalityController;
use Illuminate\Support\Facades\Route;

/*
|──────────────────────────────────────────────────────────────────────────────
| Auth Routes — لا تتطلب X-Farm-Id لأنها تسبق اختيار المزرعة
|──────────────────────────────────────────────────────────────────────────────
*/
Route::prefix('auth')->group(function (): void {

    // ── Public ────────────────────────────────────────────────────────────────
    Route::post('login',            [AuthController::class, 'login']);
    Route::post('register-request', [AuthController::class, 'registerRequest']);

    // ── Protected (Sanctum) ───────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout',   [AuthController::class, 'logout']);
        Route::get('me',        [AuthController::class, 'me']);
        Route::put('me',        [AuthController::class, 'updateProfile']);
        Route::put('password',  [AuthController::class, 'changePassword']);
    });
});

/*
|──────────────────────────────────────────────────────────────────────────────
| Farm-Scoped Routes — تتطلب: auth:sanctum + X-Farm-Id header + farm active
| يُضاف هنا باقي الـ routes في الـ modules القادمة
|──────────────────────────────────────────────────────────────────────────────
*/
Route::middleware(['auth:sanctum', 'farm.scope', 'farm.active'])->group(function (): void {

    // ── V1-B: Flocks ─────────────────────────────────────────────────────────
    Route::prefix('flocks')->group(function (): void {
        Route::get('/',         [FlockController::class, 'index']);
        Route::post('/',        [FlockController::class, 'store']);
        Route::get('/{flock}',  [FlockController::class, 'show']);
        Route::put('/{flock}',  [FlockController::class, 'update']);
    });

    // ── V1-C: Mortalities ────────────────────────────────────────────────────
    Route::prefix('flocks/{flock}/mortalities')->group(function (): void {
        Route::get('/',  [MortalityController::class, 'index']);
        Route::post('/', [MortalityController::class, 'store']);
    });
});

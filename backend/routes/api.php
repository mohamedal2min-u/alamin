<?php

use App\Http\Controllers\Api\Admin\AdminFarmController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Expense\ExpenseController;
use App\Http\Controllers\Api\Expense\FlockExpenseController;
use App\Http\Controllers\Api\FeedLog\FeedLogController;
use App\Http\Controllers\Api\Flock\FlockController;
use App\Http\Controllers\Api\Inventory\InventoryController;
use App\Http\Controllers\Api\MedicineLog\MedicineLogController;
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
| Admin Routes — super_admin فقط، بدون X-Farm-Id
|──────────────────────────────────────────────────────────────────────────────
*/
use App\Http\Controllers\Api\Admin\AdminRegistrationRequestController;

Route::middleware(['auth:sanctum', 'super_admin'])->prefix('admin')->group(function (): void {
    Route::get('farms',               [AdminFarmController::class, 'index']);
    Route::post('farms',              [AdminFarmController::class, 'store']);
    Route::put('farms/{farm}/admin',  [AdminFarmController::class, 'assignAdmin']);
    Route::get('users',               [AdminFarmController::class, 'users']);
    Route::get('registration-requests',         [AdminRegistrationRequestController::class, 'index']);
    Route::post('registration-requests/{registrationRequest}/approve', [AdminRegistrationRequestController::class, 'approve']);
    Route::post('registration-requests/{registrationRequest}/reject',  [AdminRegistrationRequestController::class, 'reject']);
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

    // ── V1-D: Inventory ───────────────────────────────────────────────────────
    Route::prefix('inventory')->group(function (): void {
        Route::get('/overview',      [InventoryController::class, 'overview']);   // كل البيانات في طلب واحد
        Route::get('/items',         [InventoryController::class, 'items']);
        Route::post('/items',        [InventoryController::class, 'createItem']);
        Route::get('/item-types',    [InventoryController::class, 'itemTypes']);
        Route::get('/stock',         [InventoryController::class, 'stock']);
        Route::get('/summary',       [InventoryController::class, 'summary']);
        Route::get('/warehouses',    [InventoryController::class, 'warehouses']);
        Route::get('/transactions',  [InventoryController::class, 'transactions']);
        Route::post('/transactions', [InventoryController::class, 'addShipment']);
    });

    // ── Expenses list ─────────────────────────────────────────────────────────
    Route::get('/expenses', [ExpenseController::class, 'index']);

    // ── V1-E: Feed Logs ───────────────────────────────────────────────────────
    Route::post('flocks/{flock}/feed-logs',     [FeedLogController::class, 'store']);

    // ── V1-F: Medicine Logs ───────────────────────────────────────────────────
    Route::post('flocks/{flock}/medicine-logs', [MedicineLogController::class, 'store']);

    // ── V1-G: Flock Expenses ──────────────────────────────────────────────────
    Route::post('flocks/{flock}/expenses',      [FlockExpenseController::class, 'store']);

    // ── V1-H: Temperature Logs ────────────────────────────────────────────────
    Route::get('flocks/{flock}/temperature-logs', [\App\Http\Controllers\Api\Flock\FlockTemperatureLogController::class, 'index']);
    Route::post('flocks/{flock}/temperature-logs', [\App\Http\Controllers\Api\Flock\FlockTemperatureLogController::class, 'store']);

    // ── V1-I: Today Summary ───────────────────────────────────────────────────
    Route::get('flocks/{flock}/today-summary',  [FlockController::class, 'todaySummary']);

    // ── V1-I: Reports ────────────────────────────────────────────────────────
    Route::prefix('reports')->group(function (): void {
        Route::get('summary-kpis',      [\App\Http\Controllers\Api\Report\ReportsController::class, 'summaryKpis']);
        Route::get('flock-report',     [\App\Http\Controllers\Api\Report\ReportsController::class, 'flockReport']);
        Route::get('accounting-summary', [\App\Http\Controllers\Api\Report\ReportsController::class, 'accountingSummary']);
        Route::get('inventory-report', [\App\Http\Controllers\Api\Report\ReportsController::class, 'inventoryReport']);
        Route::get('partners-report', [\App\Http\Controllers\Api\Report\ReportsController::class, 'partnersReport']);
        Route::get('workers-report', [\App\Http\Controllers\Api\Report\ReportsController::class, 'workersReport']);
        Route::get('daily-report', [\App\Http\Controllers\Api\Report\ReportsController::class, 'dailyReport']);
    });

    // ── V1-J: Partners Management ─────────────────────────────────────────────
    Route::apiResource('partners', \App\Http\Controllers\Api\Partner\PartnerController::class);
    Route::get('partners/{partner}/transactions', [\App\Http\Controllers\Api\Partner\PartnerTransactionController::class, 'index']);
    Route::post('partners/{partner}/transactions', [\App\Http\Controllers\Api\Partner\PartnerTransactionController::class, 'store']);

    // ── V1-K: Workers Management ──────────────────────────────────────────────
    Route::get('workers', [\App\Http\Controllers\Api\Worker\WorkerController::class, 'index']);
    Route::post('workers', [\App\Http\Controllers\Api\Worker\WorkerController::class, 'store']);
    Route::delete('workers/{id}', [\App\Http\Controllers\Api\Worker\WorkerController::class, 'destroy']);
});

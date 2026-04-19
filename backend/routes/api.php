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
        Route::post('avatar',   [AuthController::class, 'uploadAvatar']);
    });
});

/*
|──────────────────────────────────────────────────────────────────────────────
| Admin Routes — super_admin فقط، بدون X-Farm-Id
|──────────────────────────────────────────────────────────────────────────────
*/
use App\Http\Controllers\Api\Admin\AdminRegistrationRequestController;

Route::middleware(['auth:sanctum', 'super_admin'])->prefix('admin')->group(function (): void {
    Route::get('farms',                    [AdminFarmController::class, 'index']);
    Route::post('farms',                   [AdminFarmController::class, 'store']);
    Route::put('farms/{farm}/admin',       [AdminFarmController::class, 'assignAdmin']);
    Route::post('farms/{farm}/manager',    [AdminFarmController::class, 'createManager']);
    Route::delete('farms/{farm}',          [AdminFarmController::class, 'destroy']);
    Route::get('farms/{farm}/members',              [AdminFarmController::class, 'farmMembers']);
    Route::put('farms/{farm}/members/{user}/role',  [AdminFarmController::class, 'assignMemberRole']);
    Route::get('users',                    [AdminFarmController::class, 'users']);
    Route::put('users/{user}/password',    [AdminFarmController::class, 'resetUserPassword']);
    Route::put('users/{user}/status',      [AdminFarmController::class, 'toggleUserStatus']);
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

    // ── Expenses ──────────────────────────────────────────────────────────────
    Route::get('/expenses',            [ExpenseController::class, 'index']);
    Route::post('/expenses',           [ExpenseController::class, 'store']);
    Route::get('/expense-categories',  [ExpenseController::class, 'categories']);

    // ── V1-E: Feed Logs ───────────────────────────────────────────────────────
    Route::prefix('flocks/{flock}/feed-logs')->group(function (): void {
        Route::get('/',  [FeedLogController::class, 'index']);
        Route::post('/', [FeedLogController::class, 'store']);
    });

    // ── V1-F: Medicine Logs ───────────────────────────────────────────────────
    Route::prefix('flocks/{flock}/medicine-logs')->group(function (): void {
        Route::get('/',  [MedicineLogController::class, 'index']);
        Route::post('/', [MedicineLogController::class, 'store']);
    });

    // ── V1-G: Flock Expenses ──────────────────────────────────────────────────
    Route::post('flocks/{flock}/expenses',      [FlockExpenseController::class, 'store']);

    // ── V1-H: Temperature Logs ────────────────────────────────────────────────
    Route::get('flocks/{flock}/temperature-logs', [\App\Http\Controllers\Api\Flock\FlockTemperatureLogController::class, 'index']);
    Route::post('flocks/{flock}/temperature-logs', [\App\Http\Controllers\Api\Flock\FlockTemperatureLogController::class, 'store']);

    // ── V1-I: Today Summary ───────────────────────────────────────────────────
    Route::get('flocks/{flock}/today-summary',  [FlockController::class, 'todaySummary']);
    Route::get('flocks/{flock}/history',        [FlockController::class, 'history']);


    // ── V1-I: Reports ────────────────────────────────────────────────────────
    Route::prefix('reports')->group(function (): void {
        Route::get('summary-kpis',      [\App\Http\Controllers\Api\Report\ReportsController::class, 'summaryKpis']);
        Route::get('flock-report',     [\App\Http\Controllers\Api\Report\ReportsController::class, 'flockReport']);
        Route::get('accounting-summary', [\App\Http\Controllers\Api\Report\ReportsController::class, 'accountingSummary']);
        Route::get('inventory-report', [\App\Http\Controllers\Api\Report\ReportsController::class, 'inventoryReport']);
        Route::get('partners-report', [\App\Http\Controllers\Api\Report\ReportsController::class, 'partnersReport']);
        Route::get('workers-report', [\App\Http\Controllers\Api\Report\ReportsController::class, 'workersReport']);
        Route::get('daily-report', [\App\Http\Controllers\Api\Report\ReportsController::class, 'dailyReport']);
        Route::get('flocks/{flock}/daily-summary', [\App\Http\Controllers\Api\Report\ReportsController::class, 'flockDailySummary']);
    });

    // ── Accounting: Review Queue ───────────────────────────────────────────────
    Route::prefix('accounting')->group(function (): void {
        Route::get('review-queue',               [\App\Http\Controllers\Api\Accounting\ReviewQueueController::class, 'index']);
        Route::patch('review-queue/{type}/{id}', [\App\Http\Controllers\Api\Accounting\ReviewQueueController::class, 'update']);
    });

    // ── V1-J: Partners Management ─────────────────────────────────────────────
    Route::get('partners/my-info', [\App\Http\Controllers\Api\Partner\PartnerController::class, 'myInfo']);
    Route::apiResource('partners', \App\Http\Controllers\Api\Partner\PartnerController::class);
    Route::get('partners/{partner}/transactions', [\App\Http\Controllers\Api\Partner\PartnerTransactionController::class, 'index']);
    Route::post('partners/{partner}/transactions', [\App\Http\Controllers\Api\Partner\PartnerTransactionController::class, 'store']);

    // ── V1-K: Workers Management ──────────────────────────────────────────────
    Route::get('workers', [\App\Http\Controllers\Api\Worker\WorkerController::class, 'index']);
    Route::post('workers', [\App\Http\Controllers\Api\Worker\WorkerController::class, 'store']);
    Route::delete('workers/{id}', [\App\Http\Controllers\Api\Worker\WorkerController::class, 'destroy']);

    // ── V1-M: Water Logs ─────────────────────────────────────────────────────
    Route::prefix('flocks/{flock}/water-logs')->group(function (): void {
        Route::get('/',  [\App\Http\Controllers\Api\WaterLog\WaterLogController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\WaterLog\WaterLogController::class, 'store']);
    });

    // ── V1-N: Flock Notes ─────────────────────────────────────────────────────
    Route::prefix('flocks/{flock}/notes')->group(function (): void {
        Route::get('/',  [\App\Http\Controllers\Api\FlockNote\FlockNoteController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\FlockNote\FlockNoteController::class, 'store']);
    });

    // ── V1-L: Sales ───────────────────────────────────────────────────────────
    Route::get('flocks/{flock}/sales',  [\App\Http\Controllers\Api\Sale\SaleController::class, 'index']);
    Route::post('flocks/{flock}/sales', [\App\Http\Controllers\Api\Sale\SaleController::class, 'store']);
    Route::get('sales',                 [\App\Http\Controllers\Api\Sale\SaleController::class, 'indexAll']);
    Route::get('sales/{sale}',          [\App\Http\Controllers\Api\Sale\SaleController::class, 'show']);
    Route::patch('sales/{sale}/payment',[\App\Http\Controllers\Api\Sale\SaleController::class, 'updatePayment']);
});

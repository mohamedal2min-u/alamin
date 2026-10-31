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
    Route::post('login',            [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('register-request', [AuthController::class, 'registerRequest'])->middleware('throttle:5,1');

    // ── Protected (Sanctum) ───────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout',   [AuthController::class, 'logout']);
        Route::get('me',        [AuthController::class, 'me']);
        Route::put('me',        [AuthController::class, 'updateProfile']);
        Route::put('password',  [AuthController::class, 'changePassword']);
        Route::post('avatar',           [AuthController::class, 'uploadAvatar']);
        Route::get('notifications/feed', [\App\Http\Controllers\Api\Auth\NotificationController::class, 'feed']);
        Route::get('notifications', [\App\Http\Controllers\Api\Auth\NotificationController::class, 'index']);
        Route::post('notifications/read-all', [\App\Http\Controllers\Api\Auth\NotificationController::class, 'readAll']);
        Route::get('notifications/{id}', [\App\Http\Controllers\Api\Auth\NotificationController::class, 'show'])->whereNumber('id');
        Route::post('update-fcm-token', [\App\Http\Controllers\Api\Auth\NotificationController::class, 'updateToken']);
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
        Route::get('/',                      [FlockController::class, 'index']);
        Route::post('/',                     [FlockController::class, 'store'])->middleware(['farm.role:farm_admin,supervisor', 'farm.noprice']);
        Route::get('/{flock}/today-summary', [FlockController::class, 'todaySummary']);
        Route::get('/{flock}/today_summary', [FlockController::class, 'todaySummary']); // Compatibility alias
        Route::get('/{flock}',               [FlockController::class, 'show']);
        Route::put('/{flock}',               [FlockController::class, 'update'])->middleware(['farm.role:farm_admin,supervisor', 'farm.noprice'])->middleware('farm.notify:عدّل بيانات فوج');
    });

    // ── V1-C: Mortalities ────────────────────────────────────────────────────
    Route::prefix('flocks/{flock}/mortalities')->group(function (): void {
        Route::get('/',  [MortalityController::class, 'index']);
        Route::post('/', [MortalityController::class, 'store'])->middleware(['farm.role:farm_admin,supervisor', 'idempotent']);
    });

    // ── V1-D: Inventory ───────────────────────────────────────────────────────
    Route::prefix('inventory')->group(function (): void {
        Route::get('/overview',      [InventoryController::class, 'overview']);   // كل البيانات في طلب واحد
        Route::get('/items',         [InventoryController::class, 'items']);
        Route::get('/barcode/{code}', [InventoryController::class, 'lookupBarcode'])->middleware('farm.role:farm_admin,supervisor')->middleware('throttle:60,1');
        Route::post('/items',        [InventoryController::class, 'createItem'])->middleware(['farm.role:farm_admin,supervisor', 'farm.noprice'])->middleware('farm.notify:أضاف صنفاً للمخزون');
        Route::put('/items/{item}',  [InventoryController::class, 'updateItem'])->middleware(['farm.role:farm_admin,supervisor', 'farm.noprice'])->middleware('farm.notify:عدّل صنفاً في المخزون');
        Route::patch('/items/{item}/barcode', [InventoryController::class, 'setBarcode'])->middleware('farm.role:farm_admin,supervisor');
        Route::patch('/items/{item}/adjust-stock', [InventoryController::class, 'adjustStock'])->middleware('farm.role:farm_admin,supervisor')->middleware('farm.notify:صحّح رصيد المخزون');
        Route::get('/item-types',    [InventoryController::class, 'itemTypes']);
        Route::get('/stock',         [InventoryController::class, 'stock']);
        Route::get('/summary',       [InventoryController::class, 'summary']);
        Route::get('/warehouses',    [InventoryController::class, 'warehouses']);
        Route::get('/transactions',  [InventoryController::class, 'transactions']);
        Route::post('/transactions', [InventoryController::class, 'addShipment'])->middleware(['farm.role:farm_admin,supervisor', 'farm.noprice'])->middleware('farm.notify:أضاف حمولة للمخزون');
    });

    // ── Expenses ──────────────────────────────────────────────────────────────
    Route::get('/expenses',            [ExpenseController::class, 'index']);
    Route::post('/expenses',           [ExpenseController::class, 'store'])->middleware(['farm.role:farm_admin,supervisor', 'farm.noprice'])->middleware('farm.notify:أضاف مصروفاً');
    Route::get('/expense-categories',  [ExpenseController::class, 'categories']);

    // ── V1-E: Feed Logs ───────────────────────────────────────────────────────
    Route::prefix('flocks/{flock}/feed-logs')->group(function (): void {
        Route::get('/',  [FeedLogController::class, 'index']);
        Route::post('/', [FeedLogController::class, 'store'])->middleware(['farm.role:farm_admin,supervisor', 'idempotent']);
    });

    // ── V1-F: Medicine Logs ───────────────────────────────────────────────────
    Route::prefix('flocks/{flock}/medicine-logs')->group(function (): void {
        Route::get('/',  [MedicineLogController::class, 'index']);
        Route::post('/', [MedicineLogController::class, 'store'])->middleware(['farm.role:farm_admin,supervisor', 'idempotent']);
    });

    // ── V1-G: Flock Expenses ──────────────────────────────────────────────────
    Route::post('flocks/{flock}/expenses',      [FlockExpenseController::class, 'store'])->middleware(['farm.role:farm_admin,supervisor', 'farm.noprice'])->middleware('farm.notify:أضاف مصروفاً');

    // ── V1-H: Temperature Logs ────────────────────────────────────────────────
    // ── مستندات الفوج (صور الأوراق والفواتير) ─────────────────────────────────
    Route::get('flocks/{flock}/documents',  [\App\Http\Controllers\Api\Flock\FlockDocumentController::class, 'index']);
    Route::post('flocks/{flock}/documents', [\App\Http\Controllers\Api\Flock\FlockDocumentController::class, 'store'])->middleware('farm.role:farm_admin,supervisor,accountant')->middleware('farm.notify:رفع مستنداً للفوج');
    Route::patch('flocks/{flock}/documents/{document}', [\App\Http\Controllers\Api\Flock\FlockDocumentController::class, 'update'])->middleware('farm.role:farm_admin,supervisor,accountant');

    Route::get('flocks/{flock}/temperature-logs', [\App\Http\Controllers\Api\Flock\FlockTemperatureLogController::class, 'index']);
    Route::post('flocks/{flock}/temperature-logs', [\App\Http\Controllers\Api\Flock\FlockTemperatureLogController::class, 'store'])->middleware(['farm.role:farm_admin,supervisor', 'idempotent']);

    // ── V1-I: Today Summary ───────────────────────────────────────────────────
    Route::get('flocks/{flock}/today-summary',  [FlockController::class, 'todaySummary']);
    Route::get('flocks/{flock}/history',        [FlockController::class, 'history']);
    Route::get('flocks/{flock}/kpis',           [\App\Http\Controllers\Api\Report\FlockKpiController::class, 'show']);


    // ── V1-I: Reports ────────────────────────────────────────────────────────
    Route::prefix('reports')->group(function (): void {
        Route::get('summary-kpis',      [\App\Http\Controllers\Api\Report\ReportsController::class, 'summaryKpis']);
        Route::get('flock-report',     [\App\Http\Controllers\Api\Report\ReportsController::class, 'flockReport']);
        Route::get('flock-comparison', [\App\Http\Controllers\Api\Report\FlockKpiController::class, 'comparison'])->middleware('farm.role:farm_admin,accountant,partner');
        Route::get('accounting-summary', [\App\Http\Controllers\Api\Report\ReportsController::class, 'accountingSummary']);
        Route::get('flock-profit', [\App\Http\Controllers\Api\Report\ReportsController::class, 'flockProfit'])->middleware('farm.role:farm_admin,accountant');
        Route::get('inventory-report', [\App\Http\Controllers\Api\Report\ReportsController::class, 'inventoryReport']);
        Route::get('partners-report', [\App\Http\Controllers\Api\Report\ReportsController::class, 'partnersReport'])->middleware('farm.role:farm_admin,accountant');
        Route::get('workers-report', [\App\Http\Controllers\Api\Report\ReportsController::class, 'workersReport'])->middleware('farm.role:farm_admin,accountant');
        Route::get('daily-report', [\App\Http\Controllers\Api\Report\ReportsController::class, 'dailyReport']);
        Route::get('flocks/{flock}/daily-summary', [\App\Http\Controllers\Api\Report\ReportsController::class, 'flockDailySummary']);
    });

    // ── Accounting: Review Queue ───────────────────────────────────────────────
    Route::prefix('accounting')->group(function (): void {
        Route::get('review-queue',               [\App\Http\Controllers\Api\Accounting\ReviewQueueController::class, 'index']);
        Route::patch('review-queue/{type}/{id}', [\App\Http\Controllers\Api\Accounting\ReviewQueueController::class, 'update'])->middleware('farm.role:farm_admin,accountant')->middleware('farm.notify:عدّل سجلاً مالياً (سعر/دفعة)');
        // سجل تعديلات الأسعار — لمدير المزرعة فقط
        Route::get('share-audit',                [\App\Http\Controllers\Api\Accounting\PriceAuditController::class, 'shares'])->middleware('farm.role:farm_admin');
        Route::get('payment-audit',              [\App\Http\Controllers\Api\Accounting\PriceAuditController::class, 'payments'])->middleware('farm.role:farm_admin,accountant');
        Route::get('price-audit',                [\App\Http\Controllers\Api\Accounting\PriceAuditController::class, 'index'])->middleware('farm.role:farm_admin');
    });

    // ── Contacts (suppliers/traders) + dated payments ledger ─────────────────
    // الدفعات للمحاسب فقط؛ الكشوف للمدير والمحاسب والشريك (قراءة)
    Route::get('accounting/payments',  [\App\Http\Controllers\Api\Accounting\PaymentController::class, 'index'])->middleware('farm.role:farm_admin,accountant,partner');
    Route::post('accounting/payments', [\App\Http\Controllers\Api\Accounting\PaymentController::class, 'store'])->middleware('farm.role:accountant')->middleware('farm.notify:سجّل دفعة');
    Route::prefix('contacts')->group(function (): void {
        $c = \App\Http\Controllers\Api\Contact\ContactController::class;
        Route::get('/',                    [$c, 'index']);
        // المشرف يضيف جهة جديدة من نماذج الإدخال («إضافة جديد»)
        Route::post('/',                   [$c, 'store'])->middleware('farm.role:farm_admin,accountant,supervisor');
        Route::get('/balances',            [$c, 'balances'])->middleware('farm.role:farm_admin,accountant,partner');
        Route::get('/{contact}/statement', [$c, 'statement'])->whereNumber('contact')->middleware('farm.role:farm_admin,accountant,partner');
        Route::put('/{contact}',           [$c, 'update'])->whereNumber('contact')->middleware('farm.role:farm_admin,accountant');
        Route::delete('/{contact}',        [$c, 'destroy'])->whereNumber('contact')->middleware('farm.role:farm_admin,accountant');
    });

    // ── V1-J: Partners Management ─────────────────────────────────────────────
    Route::get('partners/my-info', [\App\Http\Controllers\Api\Partner\PartnerController::class, 'myInfo']);
    // قائمة الشركاء وحصصهم وبياناتهم: مدير المزرعة والمحاسب فقط (الشريك يرى سجله وحده عبر my-info)
    Route::apiResource('partners', \App\Http\Controllers\Api\Partner\PartnerController::class)->only(['index', 'show'])->middleware('farm.role:farm_admin,accountant');
    Route::apiResource('partners', \App\Http\Controllers\Api\Partner\PartnerController::class)->only(['store', 'update', 'destroy'])->middleware(['farm.role:farm_admin', 'farm.notify:عدّل بيانات الشركاء']);
    Route::get('partners/{partner}/transactions', [\App\Http\Controllers\Api\Partner\PartnerTransactionController::class, 'index']);
    Route::post('partners/{partner}/transactions', [\App\Http\Controllers\Api\Partner\PartnerTransactionController::class, 'store'])->middleware('farm.role:farm_admin,accountant')->middleware('farm.notify:سجّل حركة لشريك');
    Route::patch('partners/{partner}/share', [\App\Http\Controllers\Api\Partner\PartnerController::class, 'updateShare'])->middleware('farm.role:farm_admin,accountant')->middleware('farm.notify:عدّل نسبة شريك');

    // ── V1-K: Workers Management ──────────────────────────────────────────────
    Route::get('workers', [\App\Http\Controllers\Api\Worker\WorkerController::class, 'index'])->middleware('farm.role:farm_admin');
    Route::post('workers', [\App\Http\Controllers\Api\Worker\WorkerController::class, 'store'])->middleware('farm.role:farm_admin');
    Route::delete('workers/{id}', [\App\Http\Controllers\Api\Worker\WorkerController::class, 'destroy'])->middleware('farm.role:farm_admin');

    // ── V1-M: Water Logs ─────────────────────────────────────────────────────
    Route::prefix('flocks/{flock}/water-logs')->group(function (): void {
        Route::get('/',  [\App\Http\Controllers\Api\WaterLog\WaterLogController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\WaterLog\WaterLogController::class, 'store'])->middleware(['farm.role:farm_admin,supervisor', 'farm.noprice', 'idempotent'])->middleware('farm.notify:سجّل استهلاك ماء');
    });

    // ── V1-N: Flock Notes ─────────────────────────────────────────────────────
    Route::prefix('flocks/{flock}/notes')->group(function (): void {
        Route::get('/',  [\App\Http\Controllers\Api\FlockNote\FlockNoteController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\FlockNote\FlockNoteController::class, 'store'])->middleware(['farm.role:farm_admin,supervisor', 'idempotent']);
    });

    // ── تصحيح الإدخالات اليومية (تعديل/إلغاء) + سجل النشاطات ──────────────────
    Route::patch('daily-entries/{type}/{id}', [\App\Http\Controllers\Api\DailyEntry\DailyEntryController::class, 'update'])->whereNumber('id')->middleware(['farm.role:farm_admin,supervisor', 'farm.noprice', 'farm.notify:عدّل سجلاً يومياً,daily_entry']);
    Route::post('daily-entries/{type}/{id}/void', [\App\Http\Controllers\Api\DailyEntry\DailyEntryController::class, 'void'])->whereNumber('id')->middleware(['farm.role:farm_admin,supervisor', 'farm.notify:ألغى سجلاً يومياً,daily_entry']);
    Route::get('activity-logs', [\App\Http\Controllers\Api\DailyEntry\DailyEntryController::class, 'activity'])->middleware('farm.role:farm_admin');
    // ── Vaccinations: برنامج اللقاحات وفترة السحب ──────────────────────────
    Route::get('vaccine-template',               [\App\Http\Controllers\Api\Vaccination\VaccinationController::class, 'template']);
    Route::put('vaccine-template',               [\App\Http\Controllers\Api\Vaccination\VaccinationController::class, 'updateTemplate'])->middleware('farm.role:farm_admin');
    Route::post('vaccine-template/apply-active', [\App\Http\Controllers\Api\Vaccination\VaccinationController::class, 'applyToActive'])->middleware('farm.role:farm_admin');
    Route::get('vaccinations/active',            [\App\Http\Controllers\Api\Vaccination\VaccinationController::class, 'active']);
    Route::get('flocks/{flock}/vaccinations',    [\App\Http\Controllers\Api\Vaccination\VaccinationController::class, 'index']);
    Route::patch('flocks/{flock}/vaccinations/{vaccination}', [\App\Http\Controllers\Api\Vaccination\VaccinationController::class, 'update'])->middleware('farm.role:farm_admin,supervisor');
    Route::get('flocks/{flock}/withdrawal',      [\App\Http\Controllers\Api\Vaccination\VaccinationController::class, 'withdrawal']);

    // ── V1-L: Sales ───────────────────────────────────────────────────────────
    Route::get('flocks/{flock}/sales',  [\App\Http\Controllers\Api\Sale\SaleController::class, 'index']);
    Route::post('flocks/{flock}/sales', [\App\Http\Controllers\Api\Sale\SaleController::class, 'store'])->middleware(['farm.role:farm_admin,supervisor', 'farm.noprice'])->middleware('farm.notify:سجّل عملية بيع');
    Route::get('sales',                 [\App\Http\Controllers\Api\Sale\SaleController::class, 'indexAll']);
    Route::get('sales/{sale}',          [\App\Http\Controllers\Api\Sale\SaleController::class, 'show']);
    Route::patch('sales/{sale}/payment',[\App\Http\Controllers\Api\Sale\SaleController::class, 'updatePayment'])->middleware('farm.role:accountant');

    // ── كشوف PDF قابلة للمشاركة: تعيد رابطاً موقّعاً لمدة 30 دقيقة ──────────────
    Route::prefix('statements')->middleware('throttle:30,1')->group(function (): void {
        Route::get('flock/{flock}',     [\App\Http\Controllers\Api\Statement\StatementController::class, 'flock'])->whereNumber('flock')->middleware('farm.role:farm_admin,accountant,partner');
        Route::get('partner/{partner}', [\App\Http\Controllers\Api\Statement\StatementController::class, 'partner'])->whereNumber('partner')->middleware('farm.role:farm_admin,accountant,partner');
        Route::get('sale/{sale}',       [\App\Http\Controllers\Api\Statement\StatementController::class, 'sale'])->whereNumber('sale')->middleware('farm.role:farm_admin,supervisor,accountant');
    });
});

// تنزيل الكشف بالرابط الموقّع — بلا مصادقة (يُفتح من المتصفح أو واتساب)
Route::get('statements/download/{type}/{id}', [\App\Http\Controllers\Api\Statement\StatementController::class, 'download'])
    ->whereIn('type', ['flock', 'partner', 'sale'])
    ->whereNumber('id')
    ->middleware(['signed:relative', 'throttle:30,1'])
    ->name('statements.download');

<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReportsController extends Controller
{
    protected $reportService;
    protected $getDailySummaryAction;

    public function __construct(
        ReportService $reportService,
        \App\Actions\Flock\GetDailySummaryAction $getDailySummaryAction
    ) {
        $this->reportService = $reportService;
        $this->getDailySummaryAction = $getDailySummaryAction;
    }

    /**
     * GET reports/summary-kpis
     */
    public function summaryKpis(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        return response()->json($this->reportService->getSummaryKpis($farmId));
    }

    /**
     * GET reports/flock-report?flock_id=1
     */
    public function flockReport(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $flockId = $request->query('flock_id');

        if (!$flockId) {
            return response()->json([
                'status' => 'error',
                'message' => 'flock_id مطلوب لعرض هذا التقرير'
            ], 400);
        }

        try {
            $data = $this->reportService->getFlockReport($farmId, (int) $flockId);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'الفوج غير موجود أو لا ينتمي لهذه المزرعة'
            ], 404);
        }
    }

    /**
     * GET reports/accounting-summary
     *
     * بدون flock_id: يستخدم الفوج النشط تلقائياً، وإن لم يوجد فالأحدث مغلقاً.
     */
    public function accountingSummary(Request $request): JsonResponse
    {
        $farmId    = $request->attributes->get('farm_id');
        $flockId   = $request->query('flock_id');
        $startDate = $request->query('start_date');
        $endDate   = $request->query('end_date');

        // Auto-select flock when not specified
        $flock = null;
        if ($flockId) {
            $flock = \App\Models\Flock::where('farm_id', $farmId)->find((int) $flockId);
        } else {
            // 1. Active flock
            $flock = \App\Models\Flock::where('farm_id', $farmId)->where('status', 'active')->first();
            // 2. Most recently closed flock
            if (!$flock) {
                $flock = \App\Models\Flock::where('farm_id', $farmId)
                    ->where('status', 'closed')
                    ->orderByDesc('close_date')
                    ->orderByDesc('id')
                    ->first();
            }
        }

        if (!$flock) {
            return response()->json([
                'flock'           => null,
                'summary'         => ['total_sales' => 0, 'total_expenses' => 0, 'net_profit' => 0],
                'cash_flow'       => ['total_received' => 0, 'total_paid' => 0, 'balance' => 0],
                'debts'           => ['receivables' => 0, 'payables' => 0],
                'expense_breakdown' => [],
                'currency'        => 'USD',
            ]);
        }

        $data = $this->reportService->getAccountingSummary($farmId, $flock->id, $startDate, $endDate);
        $data['flock'] = [
            'id'     => $flock->id,
            'name'   => $flock->name,
            'status' => $flock->status,
        ];

        return response()->json($data);
    }

    /**
     * GET reports/flock-profit
     * ربح/خسارة الفوج وحصة كل شريك (بنفس معادلة التوزيع عند الإغلاق).
     * بدون flock_id: الفوج النشط، وإلا الأحدث مغلقاً.
     */
    public function flockProfit(Request $request, \App\Services\FlockProfitService $profit, \App\Services\ReviewQueueService $reviewQueue): JsonResponse
    {
        $farmId  = $request->attributes->get('farm_id');
        $flockId = $request->query('flock_id');

        $flock = $flockId
            ? \App\Models\Flock::where('farm_id', $farmId)->find((int) $flockId)
            : (\App\Models\Flock::where('farm_id', $farmId)->where('status', 'active')->first()
                ?? \App\Models\Flock::where('farm_id', $farmId)->where('status', 'closed')
                    ->orderByDesc('close_date')->orderByDesc('id')->first());

        if (! $flock) {
            return response()->json(['flock' => null]);
        }

        $data = $profit->summary($flock);
        // سجلات بلا سعر تجعل الربح غير نهائي
        $queue = $reviewQueue->getQueue($farmId, ['reason' => 'missing_price', 'flock_id' => $flock->id, 'per_page' => 1]);
        $data['unpriced_count'] = (int) ($queue['meta']['total'] ?? 0);

        return response()->json($data);
    }

    /**
     * GET reports/inventory-report
     */
    public function inventoryReport(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        return response()->json($this->reportService->getInventoryReport($farmId));
    }

    /**
     * GET reports/partners-report
     */
    public function partnersReport(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        return response()->json($this->reportService->getPartnersReport($farmId));
    }

    /**
     * GET reports/workers-report
     */
    public function workersReport(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        return response()->json($this->reportService->getWorkersReport($farmId));
    }

    /**
     * GET reports/daily-report
     */
    public function dailyReport(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $date = $request->query('date');
        return response()->json($this->reportService->getDailyReport($farmId, $date));
    }

    /**
     * GET reports/flocks/{flock}/daily-summary
     */
    public function flockDailySummary(Request $request, int $flockId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $flock = \App\Models\Flock::where('farm_id', $farmId)->findOrFail($flockId);

        return response()->json([
            'data' => $this->getDailySummaryAction->execute($flock)
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReportsController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
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
     */
    public function accountingSummary(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $flockId = $request->query('flock_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $data = $this->reportService->getAccountingSummary($farmId, $flockId, $startDate, $endDate);
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
}

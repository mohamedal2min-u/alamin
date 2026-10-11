<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\UpdateReviewItemRequest;
use App\Services\ReviewQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewQueueController extends Controller
{
    public function __construct(private readonly ReviewQueueService $service) {}

    /**
     * GET /accounting/review-queue
     * Query params: type (expense|sale|all), reason, flock_id, page, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $filters = $request->only(['type', 'reason', 'flock_id', 'page', 'per_page', 'category', 'scope']);

        $result = $this->service->getQueue($farmId, $filters);

        return response()->json($result);
    }

    /**
     * PATCH /accounting/review-queue/{type}/{id}
     * Recalculates remaining_amount, payment_status, review_reasons after update.
     */
    public function update(
        UpdateReviewItemRequest $request,
        string $type,
        int $id
    ): JsonResponse {
        if (! in_array($type, ['expense', 'sale', 'water_log'])) {
            return response()->json(['message' => 'النوع غير صالح'], 422);
        }

        $farmId = $request->attributes->get('farm_id');
        $data = $request->validated();

        // تحديد الأسعار من صلاحية المحاسب فقط؛ مدير المزرعة يضيف السجلات دون أسعار
        $canSetPrices = $request->attributes->get('is_super_admin') === true
            || $request->user()->hasRole('accountant');

        if (! $canSetPrices && array_key_exists('unit_price', $data)) {
            return response()->json(['message' => 'تحديد الأسعار من صلاحية المحاسب'], 403);
        }

        // المحاسب لا يعدّل شيئاً على فوج مغلق (يرى فقط)
        $accountantOnly = $request->attributes->get('is_super_admin') !== true
            && $request->user()->hasRole('accountant')
            && ! $request->user()->hasRole('farm_admin');

        if ($accountantOnly && in_array($this->service->flockStatusOf($farmId, $type, $id), ['closed', 'cancelled'], true)) {
            return response()->json(['message' => 'الفوج مغلق: لا يمكن التعديل عليه'], 403);
        }

        $updated = $this->service->updateRecord($farmId, $type, $id, $data, $request->user()->id);

        return response()->json($updated);
    }
}

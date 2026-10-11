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
        $farmId  = $request->attributes->get('farm_id');
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
        $data   = $request->validated();

        // المحاسب يحدد الأسعار فقط؛ تسجيل الدفعات من صلاحية مدير المزرعة
        $isAdmin = $request->attributes->get('is_super_admin') === true
            || $request->user()->hasRole('farm_admin');

        if (! $isAdmin && array_key_exists('paid_amount', $data)) {
            return response()->json(['message' => 'تسجيل المبالغ المدفوعة من صلاحية مدير المزرعة'], 403);
        }

        $updated = $this->service->updateRecord($farmId, $type, $id, $data, $request->user()->id);

        return response()->json($updated);
    }
}

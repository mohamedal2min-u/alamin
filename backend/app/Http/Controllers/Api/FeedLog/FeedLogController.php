<?php

namespace App\Http\Controllers\Api\FeedLog;

use App\Actions\FeedLog\CreateFeedLogAction;
use App\Actions\Flock\ShowFlockAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\FeedLog\StoreFeedLogRequest;
use Illuminate\Http\JsonResponse;

class FeedLogController extends Controller
{
    public function __construct(
        private readonly ShowFlockAction     $showFlockAction,
        private readonly CreateFeedLogAction $createAction,
    ) {}

    // ── POST /api/flocks/{flock}/feed-logs ────────────────────────────────────

    public function store(StoreFeedLogRequest $request, int $flockId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $userId = $request->user()->id;

        try {
            $flock   = $this->showFlockAction->execute($farmId, $flockId);
            $feedLog = $this->createAction->execute($flock, $userId, $request->validated());
        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            return response()->json(
                ['message' => $e->getMessage()],
                $code >= 400 && $code < 600 ? $code : 422
            );
        }

        return response()->json([
            'message' => 'تم تسجيل العلف بنجاح',
            'data'    => [
                'id'       => $feedLog->id,
                'item_id'  => $feedLog->item_id,
                'quantity' => $feedLog->quantity,
            ],
        ], 201);
    }
}

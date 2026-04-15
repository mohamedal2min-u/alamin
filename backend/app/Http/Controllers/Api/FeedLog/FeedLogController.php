<?php

namespace App\Http\Controllers\Api\FeedLog;

use App\Actions\FeedLog\CreateFeedLogAction;
use App\Actions\FeedLog\ListFeedLogsAction;
use App\Actions\Flock\ShowFlockAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\FeedLog\StoreFeedLogRequest;
use App\Http\Resources\FlockFeedLogResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class FeedLogController extends Controller
{
    public function __construct(
        private readonly ShowFlockAction     $showFlockAction,
        private readonly ListFeedLogsAction  $listAction,
        private readonly CreateFeedLogAction $createAction,
    ) {}

    // ── GET /api/flocks/{flock}/feed-logs ─────────────────────────────────────

    public function index(Request $request, int $flockId): ResourceCollection|JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        try {
            $this->showFlockAction->execute($farmId, $flockId);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 404);
        }

        $logs = $this->listAction->execute($farmId, $flockId);

        return FlockFeedLogResource::collection($logs);
    }

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

        $feedLog->load('item:id,name,input_unit');

        return response()->json([
            'message' => 'تم تسجيل العلف بنجاح',
            'data'    => new FlockFeedLogResource($feedLog),
        ], 201);
    }
}

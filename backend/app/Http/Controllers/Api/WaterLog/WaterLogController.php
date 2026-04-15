<?php

namespace App\Http\Controllers\Api\WaterLog;

use App\Actions\Flock\ShowFlockAction;
use App\Actions\WaterLog\CreateWaterLogAction;
use App\Actions\WaterLog\ListWaterLogsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\WaterLog\StoreWaterLogRequest;
use App\Http\Resources\FlockWaterLogResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class WaterLogController extends Controller
{
    public function __construct(
        private readonly ShowFlockAction      $showFlockAction,
        private readonly ListWaterLogsAction  $listAction,
        private readonly CreateWaterLogAction $createAction,
    ) {}

    // ── GET /api/flocks/{flock}/water-logs ────────────────────────────────────

    public function index(Request $request, int $flockId): ResourceCollection|JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        try {
            $this->showFlockAction->execute($farmId, $flockId);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 404);
        }

        $logs = $this->listAction->execute($farmId, $flockId);

        return FlockWaterLogResource::collection($logs);
    }

    // ── POST /api/flocks/{flock}/water-logs ───────────────────────────────────

    public function store(StoreWaterLogRequest $request, int $flockId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $userId = $request->user()->id;

        try {
            $flock    = $this->showFlockAction->execute($farmId, $flockId);
            $waterLog = $this->createAction->execute($flock, $userId, $request->validated());
        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            return response()->json(
                ['message' => $e->getMessage()],
                $code >= 400 && $code < 600 ? $code : 422
            );
        }

        return response()->json([
            'message' => 'تم تسجيل المياه بنجاح',
            'data'    => new FlockWaterLogResource($waterLog),
        ], 201);
    }
}

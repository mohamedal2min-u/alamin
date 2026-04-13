<?php

namespace App\Http\Controllers\Api\Flock;

use App\Actions\Flock\CreateFlockAction;
use App\Actions\Flock\ListFlocksAction;
use App\Actions\Flock\ShowFlockAction;
use App\Actions\Flock\TodaySummaryAction;
use App\Actions\Flock\UpdateFlockAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Flock\StoreFlockRequest;
use App\Http\Requests\Flock\UpdateFlockRequest;
use App\Http\Resources\FlockResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class FlockController extends Controller
{
    public function __construct(
        private readonly ListFlocksAction   $listAction,
        private readonly CreateFlockAction  $createAction,
        private readonly ShowFlockAction    $showAction,
        private readonly UpdateFlockAction  $updateAction,
        private readonly TodaySummaryAction $todaySummaryAction,
    ) {}

    // ── GET /api/flocks ───────────────────────────────────────────────────────

    public function index(Request $request): ResourceCollection
    {
        $farmId = $request->attributes->get('farm_id');
        $flocks = $this->listAction->execute($farmId);

        return FlockResource::collection($flocks);
    }

    // ── POST /api/flocks ──────────────────────────────────────────────────────

    public function store(StoreFlockRequest $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $userId = $request->user()->id;

        $flock = $this->createAction->execute($farmId, $userId, $request->validated());

        return response()->json([
            'message' => 'تم إنشاء الفوج بنجاح',
            'data'    => new FlockResource($flock),
        ], 201);
    }

    // ── GET /api/flocks/{flock} ───────────────────────────────────────────────

    public function show(Request $request, int $flockId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        try {
            $flock = $this->showAction->execute($farmId, $flockId);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 404);
        }

        return response()->json(['data' => new FlockResource($flock)]);
    }

    // ── GET /api/flocks/{flock}/today-summary ────────────────────────────────

    public function todaySummary(Request $request, int $flockId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        try {
            $flock   = $this->showAction->execute($farmId, $flockId);
            $summary = $this->todaySummaryAction->execute($flock, $request->query('date'));
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 404);
        }

        return response()->json(['data' => $summary]);
    }

    // ── PUT /api/flocks/{flock} ───────────────────────────────────────────────

    public function update(UpdateFlockRequest $request, int $flockId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $userId = $request->user()->id;

        try {
            $flock = $this->showAction->execute($farmId, $flockId);
            $flock = $this->updateAction->execute($flock, $userId, $request->validated());
        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            return response()->json(
                ['message' => $e->getMessage()],
                $code >= 400 && $code < 600 ? $code : 422
            );
        }

        return response()->json([
            'message' => 'تم تحديث الفوج بنجاح',
            'data'    => new FlockResource($flock),
        ]);
    }
}

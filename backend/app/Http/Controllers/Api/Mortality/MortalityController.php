<?php

namespace App\Http\Controllers\Api\Mortality;

use App\Actions\Flock\ShowFlockAction;
use App\Actions\Mortality\CreateMortalityAction;
use App\Actions\Mortality\ListMortalitiesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mortality\StoreMortalityRequest;
use App\Http\Resources\FlockMortalityResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MortalityController extends Controller
{
    public function __construct(
        private readonly ShowFlockAction       $showFlockAction,
        private readonly ListMortalitiesAction $listAction,
        private readonly CreateMortalityAction $createAction,
    ) {}

    // ── GET /api/flocks/{flock}/mortalities ───────────────────────────────────

    public function index(Request $request, int $flockId): ResourceCollection|JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        try {
            $this->showFlockAction->execute($farmId, $flockId);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 404);
        }

        $mortalities = $this->listAction->execute($farmId, $flockId);

        return FlockMortalityResource::collection($mortalities);
    }

    // ── POST /api/flocks/{flock}/mortalities ──────────────────────────────────

    public function store(StoreMortalityRequest $request, int $flockId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $userId = $request->user()->id;

        try {
            $flock    = $this->showFlockAction->execute($farmId, $flockId);
            $mortality = $this->createAction->execute($flock, $userId, $request->validated());
        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            return response()->json(
                ['message' => $e->getMessage()],
                $code >= 400 && $code < 600 ? $code : 422
            );
        }

        return response()->json([
            'message' => 'تم تسجيل النفوق بنجاح',
            'data'    => new FlockMortalityResource($mortality),
        ], 201);
    }
}

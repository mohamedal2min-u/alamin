<?php

namespace App\Http\Controllers\Api\Mortality;

use App\Actions\Flock\ShowFlockAction;
use App\Actions\Mortality\ListMortalitiesAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\FlockMortalityResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MortalityController extends Controller
{
    public function __construct(
        private readonly ShowFlockAction       $showFlockAction,
        private readonly ListMortalitiesAction $listAction,
    ) {}

    // ── GET /api/flocks/{flock}/mortalities ───────────────────────────────────

    public function index(Request $request, int $flockId): ResourceCollection|JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        try {
            $this->showFlockAction->execute($farmId, $flockId);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $mortalities = $this->listAction->execute($flockId);

        return FlockMortalityResource::collection($mortalities);
    }

    // store() added in Task 3
    // Add a stub so routes don't throw ReflectionException:
    public function store(Request $request, int $flockId): JsonResponse
    {
        return response()->json(['message' => 'not implemented'], 501);
    }
}

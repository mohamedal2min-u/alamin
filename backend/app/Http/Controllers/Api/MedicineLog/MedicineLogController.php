<?php

namespace App\Http\Controllers\Api\MedicineLog;

use App\Actions\Flock\ShowFlockAction;
use App\Actions\MedicineLog\CreateMedicineLogAction;
use App\Actions\MedicineLog\ListMedicineLogsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\MedicineLog\StoreMedicineLogRequest;
use App\Http\Resources\FlockMedicineResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MedicineLogController extends Controller
{
    public function __construct(
        private readonly ShowFlockAction         $showFlockAction,
        private readonly ListMedicineLogsAction  $listAction,
        private readonly CreateMedicineLogAction $createAction,
    ) {}

    // ── GET /api/flocks/{flock}/medicine-logs ─────────────────────────────────

    public function index(Request $request, int $flockId): ResourceCollection|JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        try {
            $this->showFlockAction->execute($farmId, $flockId);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 404);
        }

        $logs = $this->listAction->execute($farmId, $flockId);

        return FlockMedicineResource::collection($logs);
    }

    // ── POST /api/flocks/{flock}/medicine-logs ────────────────────────────────

    public function store(StoreMedicineLogRequest $request, int $flockId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $userId = $request->user()->id;

        try {
            $flock    = $this->showFlockAction->execute($farmId, $flockId);
            $medicine = $this->createAction->execute($flock, $userId, $request->validated());
        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            return response()->json(
                ['message' => $e->getMessage()],
                $code >= 400 && $code < 600 ? $code : 422
            );
        }

        $medicine->load('item:id,name,input_unit');

        return response()->json([
            'message' => 'تم تسجيل الدواء بنجاح',
            'data'    => new FlockMedicineResource($medicine),
        ], 201);
    }
}

<?php

namespace App\Http\Controllers\Api\MedicineLog;

use App\Actions\Flock\ShowFlockAction;
use App\Actions\MedicineLog\CreateMedicineLogAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\MedicineLog\StoreMedicineLogRequest;
use Illuminate\Http\JsonResponse;

class MedicineLogController extends Controller
{
    public function __construct(
        private readonly ShowFlockAction         $showFlockAction,
        private readonly CreateMedicineLogAction $createAction,
    ) {}

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

        return response()->json([
            'message' => 'تم تسجيل الدواء بنجاح',
            'data'    => [
                'id'       => $medicine->id,
                'item_id'  => $medicine->item_id,
                'quantity' => $medicine->quantity,
            ],
        ], 201);
    }
}

<?php

namespace App\Http\Controllers\Api\Expense;

use App\Actions\Expense\CreateFlockExpenseAction;
use App\Actions\Flock\ShowFlockAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreFlockExpenseRequest;
use Illuminate\Http\JsonResponse;

class FlockExpenseController extends Controller
{
    public function __construct(
        private readonly ShowFlockAction          $showFlockAction,
        private readonly CreateFlockExpenseAction $createAction,
    ) {}

    // ── POST /api/flocks/{flock}/expenses ─────────────────────────────────────

    public function store(StoreFlockExpenseRequest $request, int $flockId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $userId = $request->user()->id;

        try {
            $flock   = $this->showFlockAction->execute($farmId, $flockId);
            $expense = $this->createAction->execute($flock, $userId, $request->validated());
        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            return response()->json(
                ['message' => $e->getMessage()],
                $code >= 400 && $code < 600 ? $code : 422
            );
        }

        return response()->json([
            'message' => 'تم تسجيل المصروف بنجاح',
            'data'    => [
                'id'           => $expense->id,
                'expense_type' => $expense->expense_type,
                'total_amount' => $expense->total_amount,
            ],
        ], 201);
    }
}

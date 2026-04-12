<?php

namespace App\Http\Controllers\Api\Expense;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    // ── GET /api/expenses ─────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        $expenses = Expense::where('farm_id', $farmId)
            ->with(['flock:id,name', 'expenseCategory:id,name,code'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $expenses->map(fn (Expense $e) => [
                'id'             => $e->id,
                'entry_date'     => $e->entry_date->toDateString(),
                'expense_type'   => $e->expense_type,
                'total_amount'   => (float) $e->total_amount,
                'paid_amount'    => (float) $e->paid_amount,
                'remaining_amount' => (float) $e->remaining_amount,
                'payment_status' => $e->payment_status,
                'flock_name'     => $e->flock?->name,
                'category_name'  => $e->expenseCategory?->name,
                'description'    => $e->description,
                'notes'          => $e->notes,
            ]),
            'meta' => [
                'total_amount' => (float) $expenses->sum('total_amount'),
                'count'        => $expenses->count(),
            ],
        ]);
    }
}

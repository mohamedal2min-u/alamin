<?php

namespace App\Http\Controllers\Api\Expense;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Flock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    // ── GET /api/expense-categories ───────────────────────────────────────────

    public function categories(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        $categories = ExpenseCategory::where('is_active', true)
            ->where(function ($q) use ($farmId) {
                $q->whereNull('farm_id')->orWhere('farm_id', $farmId);
            })
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_system']);

        return response()->json(['data' => $categories]);
    }

    // ── POST /api/expenses ────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $userId = $request->user()->id;

        $validated = $request->validate([
            'expense_category_id' => 'required|integer|exists:expense_categories,id',
            'flock_id'            => 'nullable|integer',
            'entry_date'          => 'required|date_format:Y-m-d',
            'quantity'            => 'required|numeric|min:0.001',
            'unit_price'          => 'nullable|numeric|min:0',
            'total_amount'        => 'required|numeric|min:0',
            'paid_amount'         => 'nullable|numeric|min:0',
            'payment_status'      => 'nullable|in:paid,partial,unpaid',
            'description'         => 'nullable|string|max:255',
            'notes'               => 'nullable|string|max:5000',
            'expense_type'        => 'nullable|string|max:50',
        ]);

        // Validate flock belongs to farm
        if (!empty($validated['flock_id'])) {
            $flockExists = Flock::where('id', $validated['flock_id'])
                ->where('farm_id', $farmId)
                ->exists();
            if (!$flockExists) {
                return response()->json(['message' => 'الفوج غير موجود أو لا ينتمي لهذه المزرعة'], 422);
            }
        }

        $totalAmount   = (float) $validated['total_amount'];
        $paidAmount    = isset($validated['paid_amount']) ? (float) $validated['paid_amount'] : $totalAmount;

        // Auto-detect payment status if not provided
        if (isset($validated['payment_status'])) {
            $paymentStatus = $validated['payment_status'];
        } elseif ($totalAmount <= 0) {
            // No price = debt/unpaid
            $paymentStatus = 'unpaid';
            $paidAmount = 0;
        } elseif ($paidAmount >= $totalAmount) {
            $paymentStatus = 'paid';
        } elseif ($paidAmount > 0) {
            $paymentStatus = 'partial';
        } else {
            $paymentStatus = 'unpaid';
        }

        $expense = Expense::create([
            'farm_id'             => $farmId,
            'flock_id'            => $validated['flock_id'] ?? null,
            'expense_category_id' => $validated['expense_category_id'],
            'entry_date'          => $validated['entry_date'],
            'expense_type'        => $validated['expense_type'] ?? null,
            'quantity'            => $validated['quantity'],
            'unit_price'          => $validated['unit_price'] ?? null,
            'total_amount'        => $totalAmount,
            'paid_amount'         => $paidAmount,
            'remaining_amount'    => max(0, $totalAmount - $paidAmount),
            'payment_status'      => $paymentStatus,
            'description'         => $validated['description'] ?? null,
            'notes'               => $validated['notes'] ?? null,
            'created_by'          => $userId,
            'updated_by'          => $userId,
        ]);

        return response()->json([
            'message' => 'تم تسجيل المصروف بنجاح',
            'data'    => [
                'id'             => $expense->id,
                'entry_date'     => $expense->entry_date->toDateString(),
                'total_amount'   => (float) $expense->total_amount,
                'payment_status' => $expense->payment_status,
            ],
        ], 201);
    }

    // ── GET /api/expenses ─────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        $expenses = Expense::where('farm_id', $farmId)
            ->when($request->query('flock_id'), fn($q, $fid) => $q->where('flock_id', $fid))
            ->with(['flock:id,name', 'expenseCategory:id,name,code'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $expenses->map(fn (Expense $e) => [
                'id'               => $e->id,
                'entry_date'       => $e->entry_date->toDateString(),
                'expense_type'     => $e->expense_type,
                'quantity'         => $e->quantity !== null ? (float) $e->quantity : null,
                'unit_price'       => $e->unit_price !== null ? (float) $e->unit_price : null,
                'total_amount'     => (float) $e->total_amount,
                'paid_amount'      => (float) $e->paid_amount,
                'remaining_amount' => (float) $e->remaining_amount,
                'payment_status'   => $e->payment_status,
                'flock_name'       => $e->flock?->name,
                'category_name'    => $e->expenseCategory?->name,
                'category_code'    => $e->expenseCategory?->code,
                'description'      => $e->description,
                'notes'            => $e->notes,
            ]),
            'meta' => [
                'total_amount'  => (float) $expenses->sum('total_amount'),
                'unpaid_total'  => (float) $expenses->where('payment_status', '!=', 'paid')->sum('remaining_amount'),
                'count'         => $expenses->count(),
            ],
        ]);
    }
}

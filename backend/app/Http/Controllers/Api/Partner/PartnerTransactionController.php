<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PartnerTransactionController extends Controller
{
    /**
     * Display a listing of the partner's transactions.
     */
    public function index(Request $request, Partner $partner): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        if ($partner->farm_id != $farmId) {
            abort(403, 'غير مصرح بالوصول إلى هذا المورد');
        }

        $transactions = $partner->transactions()
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($transactions);
    }

    /**
     * Store a newly created partner transaction (deposit, withdraw, adjustment, settlement).
     * Note: "profit" and "loss" should only be created via flock closure logic.
     */
    public function store(Request $request, Partner $partner): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $creatorUserId = $request->user()->id;

        if ($partner->farm_id != $farmId) {
            abort(403, 'غير مصرح بالوصول إلى هذا المورد');
        }

        $validated = $request->validate([
            'transaction_date' => 'required|date',
            'transaction_type' => 'required|string|in:deposit,withdraw,adjustment,settlement',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $transaction = $partner->transactions()->create([
            'farm_id' => $farmId,
            'transaction_date' => $validated['transaction_date'],
            'transaction_type' => $validated['transaction_type'],
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? null,
            'reference_no' => $validated['reference_no'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $creatorUserId,
        ]);

        return response()->json($transaction, 201);
    }
}

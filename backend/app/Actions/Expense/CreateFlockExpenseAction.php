<?php

namespace App\Actions\Expense;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Flock;

class CreateFlockExpenseAction
{
    /**
     * @throws \Exception 422 if flock is not active or system category missing
     */
    public function execute(Flock $flock, int $userId, array $data): Expense
    {
        if ($flock->status !== 'active') {
            throw new \Exception('لا يمكن تسجيل مصروف على فوج غير نشط', 422);
        }

        $category = ExpenseCategory::where('code', $data['expense_type'])
            ->where('is_system', true)
            ->whereNull('farm_id')
            ->first();

        if (! $category) {
            throw new \Exception('تصنيف المصروف غير موجود في النظام', 422);
        }

        $quantity  = (float) ($data['quantity'] ?? 1);
        $unitPrice = isset($data['unit_price']) ? (float) $data['unit_price'] : null;

        // Calculate total: if unit_price is given, total = qty × price; otherwise use provided total or 0
        if ($unitPrice !== null && $unitPrice > 0) {
            $totalAmount = $quantity * $unitPrice;
        } else {
            $totalAmount = isset($data['total_amount']) ? (float) $data['total_amount'] : 0;
        }

        // Auto-detect payment status based on whether a price was actually given
        $hasPrice = $totalAmount > 0;
        $paidAmount    = $hasPrice ? $totalAmount : 0;
        $paymentStatus = $hasPrice ? 'paid' : 'unpaid';

        return Expense::create([
            'farm_id'             => $flock->farm_id,
            'flock_id'            => $flock->id,
            'expense_category_id' => $category->id,
            'entry_date'          => $data['entry_date'] ?? now()->toDateString(),
            'expense_type'        => $data['expense_type'],
            'quantity'            => $quantity,
            'unit_price'          => $unitPrice,
            'total_amount'        => $totalAmount,
            'paid_amount'         => $paidAmount,
            'remaining_amount'    => max(0, $totalAmount - $paidAmount),
            'payment_status'      => $paymentStatus,
            'description'         => $data['description'] ?? null,
            'notes'               => $data['notes'] ?? null,
            'worker_id'           => $userId,
            'created_by'          => $userId,
            'updated_by'          => $userId,
        ]);
    }
}

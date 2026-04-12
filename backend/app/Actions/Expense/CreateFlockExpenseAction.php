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

        return Expense::create([
            'farm_id'             => $flock->farm_id,
            'flock_id'            => $flock->id,
            'expense_category_id' => $category->id,
            'entry_date'          => now()->toDateString(),
            'expense_type'        => $data['expense_type'],
            'quantity'            => $data['quantity'] ?? null,
            'unit_price'          => $data['unit_price'] ?? null,
            'total_amount'        => $data['total_amount'],
            'paid_amount'         => $data['total_amount'],
            'remaining_amount'    => 0,
            'payment_status'      => 'paid',
            'description'         => $data['description'] ?? null,
            'notes'               => $data['notes'] ?? null,
            'worker_id'           => $userId,
            'created_by'          => $userId,
            'updated_by'          => $userId,
        ]);
    }
}

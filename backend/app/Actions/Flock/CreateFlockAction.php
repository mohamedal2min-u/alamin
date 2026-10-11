<?php

namespace App\Actions\Flock;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Flock;
use Illuminate\Support\Facades\DB;

class CreateFlockAction
{
    public function execute(int $farmId, int $userId, array $data): Flock
    {
        return DB::transaction(function () use ($farmId, $userId, $data): Flock {
            $unitPrice  = isset($data['chick_unit_price']) ? (float) $data['chick_unit_price'] : null;
            $count      = (int) $data['initial_count'];
            $totalCost  = ($unitPrice !== null && $count > 0) ? round($unitPrice * $count, 2) : null;
            $paidAmount = isset($data['chick_paid_amount']) ? (float) $data['chick_paid_amount'] : 0;

            $flock = Flock::create([
                'farm_id'           => $farmId,
                'name'              => $data['name'],
                'status'            => 'draft',
                'start_date'        => $data['start_date'],
                'initial_count'     => $count,
                'chick_unit_price'  => $unitPrice,
                'total_chick_cost'  => $totalCost,
                'chick_paid_amount' => $paidAmount,
                'notes'             => $data['notes'] ?? null,
                'created_by'        => $userId,
                'updated_by'        => $userId,
            ]);

            // سجل مصروف الكتاكيت يُنشأ دائماً: إن لم يُحدَّد السعر (مدير المزرعة لا يحدده)
            // يظهر في قائمة المحاسب ليحدد سعره.
            $totalCost ??= 0;
            $remaining = max(0, $totalCost - $paidAmount);
            $paymentStatus = match (true) {
                $totalCost > 0 && $paidAmount >= $totalCost => 'paid',
                $paidAmount > 0 && $totalCost > 0           => 'partial',
                default                                     => 'unpaid',
            };

            $category = ExpenseCategory::firstOrCreate(
                ['name' => 'شراء كتاكيت', 'farm_id' => null],
                ['is_system' => true, 'is_active' => true, 'created_by' => $userId, 'updated_by' => $userId]
            );

            Expense::create([
                'farm_id'             => $farmId,
                'flock_id'            => $flock->id,
                'expense_category_id' => $category->id,
                'entry_date'          => $data['start_date'],
                'description'         => 'تكلفة شراء كتاكيت — ' . $flock->name,
                'quantity'            => $count,
                'unit_price'          => $unitPrice,
                'total_amount'        => $totalCost,
                'paid_amount'         => $totalCost > 0 ? min($paidAmount, $totalCost) : 0,
                'remaining_amount'    => $totalCost > 0 ? $remaining : 0,
                'payment_status'      => $paymentStatus,
                'created_by'          => $userId,
                'updated_by'          => $userId,
            ]);

            return $flock;
        });
    }
}

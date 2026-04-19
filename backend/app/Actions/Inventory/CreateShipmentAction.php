<?php

namespace App\Actions\Inventory;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\WarehouseItem;
use Illuminate\Support\Facades\DB;

class CreateShipmentAction
{
    public function execute(int $farmId, int $userId, array $data): InventoryTransaction
    {
        return DB::transaction(function () use ($farmId, $userId, $data): InventoryTransaction {
            $item        = Item::findOrFail($data['item_id']);
            $originalQty = (float) $data['original_quantity'];
            $computedQty = $originalQty * (float) $item->unit_value;

            $totalAmount = isset($data['total_amount']) ? (float) $data['total_amount'] : null;
            $paidAmount  = (float) ($data['paid_amount'] ?? 0);

            // Auto-determine payment_status from amounts (no 'partial' from frontend)
            $remaining     = ($totalAmount !== null) ? max(0, $totalAmount - $paidAmount) : 0;
            $paymentStatus = match (true) {
                $totalAmount === null || $totalAmount <= 0 => 'paid',
                $paidAmount >= $totalAmount               => 'paid',
                $paidAmount > 0                           => 'partial',
                default                                   => 'unpaid',
            };

            // أضف للمستودع
            $warehouseItem = WarehouseItem::firstOrCreate(
                ['warehouse_id' => $data['warehouse_id'], 'item_id' => $data['item_id'], 'farm_id' => $farmId],
                ['current_quantity' => 0, 'status' => 'active']
            );

            $unitValue      = max((float) $item->unit_value, 0.000001);
            $newCostPerBase = isset($data['unit_price'])
                ? (float) $data['unit_price'] / $unitValue
                : null;

            $oldQty     = (float) $warehouseItem->current_quantity;
            $oldAvg     = (float) ($warehouseItem->average_cost ?? 0);
            $newAvgCost = $newCostPerBase !== null
                ? (($oldQty * $oldAvg) + ($computedQty * $newCostPerBase)) / ($oldQty + $computedQty)
                : $oldAvg;

            $warehouseItem->increment('current_quantity', $computedQty);
            $warehouseItem->update([
                'last_in_at'   => now(),
                'average_cost' => $newAvgCost ?: $warehouseItem->average_cost,
            ]);

            $transaction = InventoryTransaction::create([
                'farm_id'           => $farmId,
                'flock_id'          => $data['flock_id'] ?? null,
                'warehouse_id'      => $data['warehouse_id'],
                'item_id'           => $data['item_id'],
                'transaction_date'  => $data['transaction_date'],
                'transaction_type'  => 'purchase',
                'direction'         => 'in',
                'source_module'     => 'manual',
                'original_quantity' => $originalQty,
                'computed_quantity' => $computedQty,
                'unit_price'        => $data['unit_price'] ?? null,
                'total_amount'      => $totalAmount,
                'paid_amount'       => min($paidAmount, $totalAmount ?? $paidAmount),
                'payment_status'    => $paymentStatus,
                'supplier_name'     => $data['supplier_name'] ?? null,
                'invoice_no'        => $data['invoice_no'] ?? null,
                'notes'             => $data['notes'] ?? null,
                'attachment_path'   => $data['attachment_path'] ?? null,
                'created_by'        => $userId,
                'updated_by'        => $userId,
            ]);

            // إنشاء سجل دين في المصاريف إذا كان هناك مبلغ غير مدفوع
            if ($remaining > 0 && $totalAmount > 0) {
                $category = ExpenseCategory::firstOrCreate(
                    ['name' => 'شراء مخزون', 'farm_id' => null],
                    ['is_system' => true, 'is_active' => true, 'created_by' => $userId, 'updated_by' => $userId]
                );

                $debtStatus = $paidAmount > 0 ? 'partial' : 'unpaid';

                Expense::create([
                    'farm_id'                       => $farmId,
                    'flock_id'                      => $data['flock_id'] ?? null,
                    'expense_category_id'           => $category->id,
                    'entry_date'                    => $data['transaction_date'],
                    'description'                   => 'دين شراء: ' . $item->name . ($data['supplier_name'] ? ' — ' . $data['supplier_name'] : ''),
                    'quantity'                      => $originalQty,
                    'unit_price'                    => $data['unit_price'] ?? null,
                    'total_amount'                  => $totalAmount,
                    'paid_amount'                   => min($paidAmount, $totalAmount),
                    'remaining_amount'              => $remaining,
                    'payment_status'                => $debtStatus,
                    'reference_no'                  => $data['invoice_no'] ?? null,
                    'linked_inventory_transaction_id' => $transaction->id,
                    'created_by'                    => $userId,
                    'updated_by'                    => $userId,
                ]);
            }

            return $transaction;
        });
    }
}

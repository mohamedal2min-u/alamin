<?php

namespace App\Actions\Expense;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Flock;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Warehouse;
use App\Models\WarehouseItem;

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

        $entryDate = $data['entry_date'] ?? now()->toDateString();

        $linkedTransactionId = ! empty($data['item_id'])
            ? $this->recordInventoryMovement($flock, $userId, (int) $data['item_id'], $quantity, $unitPrice, $totalAmount, $entryDate)
            : null;

        return Expense::create([
            'farm_id'                          => $flock->farm_id,
            'flock_id'                          => $flock->id,
            'expense_category_id'               => $category->id,
            'entry_date'                        => $entryDate,
            'expense_type'                      => $data['expense_type'],
            'quantity'                          => $quantity,
            'unit_price'                        => $unitPrice,
            'total_amount'                      => $totalAmount,
            'paid_amount'                       => $paidAmount,
            'remaining_amount'                  => max(0, $totalAmount - $paidAmount),
            'payment_status'                    => $paymentStatus,
            'description'                       => $data['description'] ?? null,
            'notes'                              => $data['notes'] ?? null,
            'linked_inventory_transaction_id'   => $linkedTransactionId,
            'worker_id'                          => $userId,
            'created_by'                         => $userId,
            'updated_by'                         => $userId,
        ]);
    }

    /**
     * Optionally link this expense to an inventory item: adds the quantity to warehouse
     * stock and records a purchase transaction, matching the manual-shipment convention
     * (quantity/unit_price expressed in the item's input unit).
     */
    private function recordInventoryMovement(
        Flock $flock,
        int $userId,
        int $itemId,
        float $quantity,
        ?float $unitPrice,
        float $totalAmount,
        string $entryDate
    ): ?int {
        $item = Item::where('farm_id', $flock->farm_id)->find($itemId);
        $warehouse = Warehouse::where('farm_id', $flock->farm_id)->where('is_active', true)->first();

        if (! $item || ! $warehouse) {
            return null;
        }

        $warehouseItem = WarehouseItem::firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'item_id' => $itemId, 'farm_id' => $flock->farm_id],
            ['current_quantity' => 0, 'average_cost' => $item->default_cost ?? 0]
        );

        $unitValue   = max((float) $item->unit_value, 0.000001);
        $computedQty = $quantity * $unitValue;

        $newCostPerBase = $unitPrice !== null ? $unitPrice / $unitValue : null;
        $oldQty         = (float) $warehouseItem->current_quantity;
        $oldAvg         = (float) ($warehouseItem->average_cost ?? 0);
        $newAvgCost     = $newCostPerBase !== null
            ? (($oldQty * $oldAvg) + ($computedQty * $newCostPerBase)) / ($oldQty + $computedQty)
            : $oldAvg;

        $warehouseItem->increment('current_quantity', $computedQty);
        $warehouseItem->update(['average_cost' => $newAvgCost ?: $warehouseItem->average_cost]);

        $transaction = InventoryTransaction::create([
            'farm_id'          => $flock->farm_id,
            'flock_id'         => $flock->id,
            'warehouse_id'     => $warehouse->id,
            'item_id'          => $itemId,
            'transaction_date' => $entryDate,
            'transaction_type' => 'purchase',
            'direction'        => 'in',
            'source_module'    => 'manual',
            'original_quantity'=> $quantity,
            'computed_quantity'=> $computedQty,
            'unit_price'       => $unitPrice,
            'total_amount'     => $totalAmount > 0 ? $totalAmount : null,
            'payment_status'   => $totalAmount > 0 ? 'paid' : 'unpaid',
            'created_by'       => $userId,
            'updated_by'       => $userId,
        ]);

        return $transaction->id;
    }
}

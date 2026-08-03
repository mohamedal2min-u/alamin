<?php

namespace App\Actions\FeedLog;

use App\Models\Flock;
use App\Models\FlockFeedLog;
use App\Models\InventoryTransaction;
use App\Models\WarehouseItem;
use Illuminate\Support\Facades\DB;

class CreateFeedLogAction
{
    /**
     * @param  array<string, mixed>  $data  validated: item_id, quantity, unit_label?, notes?
     * @throws \Exception 422 if flock is not active or stock insufficient
     */
    public function execute(Flock $flock, int $userId, array $data): FlockFeedLog
    {
        if ($flock->status !== 'active') {
            throw new \Exception('لا يمكن تسجيل علف على فوج غير نشط', 422);
        }

        return DB::transaction(function () use ($flock, $userId, $data): FlockFeedLog {
            $item    = \App\Models\Item::findOrFail($data['item_id']);
            $realQty = $data['quantity'] * ($item->unit_value ?? 1);

            $warehouse = \App\Models\Warehouse::where('farm_id', $flock->farm_id)->where('is_active', true)->first();
            $txnId = null;

            if ($warehouse) {
                // Find or create WarehouseItem, then re-fetch under a row lock so concurrent
                // feed/medicine/shipment writes for the same item can't read a stale
                // current_quantity/average_cost and lose each other's updates.
                $warehouseItem = WarehouseItem::firstOrCreate(
                    ['warehouse_id' => $warehouse->id, 'item_id' => $data['item_id']],
                    ['farm_id' => $flock->farm_id, 'current_quantity' => 0, 'average_cost' => $item->default_cost ?? 0, 'created_by' => $userId, 'updated_by' => $userId]
                );
                $warehouseItem = WarehouseItem::where('id', $warehouseItem->id)->lockForUpdate()->first();

                if ($warehouseItem->current_quantity < $realQty) {
                    $shortage = $realQty - $warehouseItem->current_quantity;
                    $avgCost = (float)($warehouseItem->average_cost ?? 0);
                    $totalCost = $avgCost * $shortage;

                    // Keep quantity/unit_price expressed in the item's input unit (e.g. bags),
                    // matching manual shipments — average_cost/shortage above are per computed unit (e.g. kg).
                    $unitValue           = $item->unit_value ?: 1;
                    $shortageInputQty    = $shortage / $unitValue;
                    $avgCostPerInputUnit = $avgCost * $unitValue;

                    $purchaseTxn = InventoryTransaction::create([
                        'farm_id'           => $flock->farm_id,
                        'warehouse_id'      => $warehouse->id,
                        'item_id'           => $data['item_id'],
                        'transaction_date'  => $data['entry_date'] ?? now()->toDateString(),
                        'transaction_type'  => 'purchase',
                        'direction'         => 'in',
                        'source_module'     => 'auto_purchase',
                        'original_quantity' => $shortageInputQty,
                        'computed_quantity' => $shortage,
                        'unit_price'        => $avgCost > 0 ? $avgCostPerInputUnit : null,
                        'input_unit'        => $item->input_unit,
                        'content_unit'      => $item->content_unit,
                        'total_amount'      => $totalCost > 0 ? $totalCost : null,
                        'created_by'        => $userId,
                        'updated_by'        => $userId,
                    ]);

                    $feedCat = \App\Models\ExpenseCategory::firstOrCreate(
                        ['code' => 'feed', 'farm_id' => null],
                        ['name' => 'علف', 'is_system' => true, 'is_active' => true, 'created_by' => $userId, 'updated_by' => $userId]
                    );
                    \App\Models\Expense::create([
                        'farm_id' => $flock->farm_id,
                        'flock_id' => $flock->id,
                        'expense_category_id' => $feedCat->id,
                        'entry_date' => $data['entry_date'] ?? now()->toDateString(),
                        'expense_type' => 'flock',
                        'quantity' => $shortageInputQty,
                        'unit_price' => $avgCost > 0 ? $avgCostPerInputUnit : 0,
                        'total_amount' => $totalCost > 0 ? $totalCost : 0,
                        'paid_amount' => 0,
                        'remaining_amount' => $totalCost > 0 ? $totalCost : 0,
                        'payment_status' => 'unpaid',
                        'description' => 'دين شراء تلقائي: ' . $item->name,
                        'linked_inventory_transaction_id' => $purchaseTxn->id,
                        'worker_id' => $userId,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);

                    $warehouseItem->increment('current_quantity', $shortage);
                }

                $avgCost         = (float) ($warehouseItem->average_cost ?? 0);
                $consumptionCost = $avgCost * $realQty;

                $txn = InventoryTransaction::create([
                    'farm_id'           => $flock->farm_id,
                    'warehouse_id'      => $warehouse->id,
                    'item_id'           => $data['item_id'],
                    'flock_id'          => $flock->id,
                    'transaction_date'  => $data['entry_date'] ?? now()->toDateString(),
                    'transaction_type'  => 'consumption',
                    'direction'         => 'out',
                    'source_module'     => 'flock_feed',
                    'original_quantity' => $data['quantity'],
                    'computed_quantity' => $realQty,
                    'unit_price'        => $avgCost > 0 ? $avgCost : null,
                    'input_unit'        => $item->input_unit,
                    'content_unit'      => $item->content_unit,
                    'total_amount'      => $consumptionCost > 0 ? $consumptionCost : null,
                    'created_by'        => $userId,
                    'updated_by'        => $userId,
                ]);

                $warehouseItem->decrement('current_quantity', $realQty);
                $txnId = $txn->id;
            }

            return FlockFeedLog::create([
                'farm_id'                  => $flock->farm_id,
                'flock_id'                 => $flock->id,
                'item_id'                  => $data['item_id'],
                'entry_date'               => $data['entry_date'] ?? now()->toDateString(),
                'quantity'                 => $data['quantity'],
                'unit_label'               => $data['unit_label'] ?? $item->input_unit,
                'notes'                    => $data['notes'] ?? null,
                'worker_id'                => $userId,
                'inventory_transaction_id' => $txnId,
                'editable_until'           => now()->addMinutes(15),
                'created_by'               => $userId,
                'updated_by'               => $userId,
            ]);
        });
    }
}

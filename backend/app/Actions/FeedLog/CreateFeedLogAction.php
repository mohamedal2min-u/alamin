<?php

namespace App\Actions\FeedLog;

use App\Models\Flock;
use App\Models\FlockFeedLog;
use App\Models\InventoryTransaction;
use App\Models\WarehouseItem;

class CreateFeedLogAction
{
    /**
     * @param  array<string, mixed>  $data  validated: item_id, quantity, unit_label?, notes?
     * @throws \Exception 422 if flock is not active
     */
    public function execute(Flock $flock, int $userId, array $data): FlockFeedLog
    {
        if ($flock->status !== 'active') {
            throw new \Exception('لا يمكن تسجيل علف على فوج غير نشط', 422);
        }

        $item = \App\Models\Item::findOrFail($data['item_id']);
        $realQty = $data['quantity'] * ($item->unit_value ?? 1);

        // ── Inventory deduction (mandatory if warehouse exists) ───────────
        $warehouseItem = WarehouseItem::where('item_id', $data['item_id'])
            ->whereHas('warehouse', fn ($q) => $q->where('farm_id', $flock->farm_id)->where('is_active', true))
            ->first();

        if (!$warehouseItem) {
            throw new \Exception('هذا الصنف غير متوفر في مخزون المزرعة', 422);
        }

        if ($warehouseItem->current_quantity < $realQty) {
            throw new \Exception('المخزون غير كافٍ لسحب هذه الكمية', 422);
        }

        $txn = InventoryTransaction::create([
            'farm_id'           => $flock->farm_id,
            'warehouse_id'      => $warehouseItem->warehouse_id,
            'item_id'           => $data['item_id'],
            'flock_id'          => $flock->id,
            'transaction_date'  => now()->toDateString(),
            'transaction_type'  => 'consumption',
            'direction'         => 'out',
            'source_module'     => 'flock_feed',
            'computed_quantity' => $realQty,
            'created_by'        => $userId,
            'updated_by'        => $userId,
        ]);

        $warehouseItem->decrement('current_quantity', $realQty);

        return FlockFeedLog::create([
            'farm_id'                  => $flock->farm_id,
            'flock_id'                 => $flock->id,
            'item_id'                  => $data['item_id'],
            'entry_date'               => now()->toDateString(),
            'quantity'                 => $data['quantity'],
            'unit_label'               => $data['unit_label'] ?? $item->input_unit,
            'notes'                    => $data['notes'] ?? null,
            'worker_id'                => $userId,
            'inventory_transaction_id' => $txn->id,
            'editable_until'           => now()->addMinutes(15),
            'created_by'               => $userId,
            'updated_by'               => $userId,
        ]);
    }
}

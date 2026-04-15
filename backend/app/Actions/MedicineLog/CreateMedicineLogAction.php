<?php

namespace App\Actions\MedicineLog;

use App\Models\Flock;
use App\Models\FlockMedicine;
use App\Models\InventoryTransaction;
use App\Models\WarehouseItem;
use Illuminate\Support\Facades\DB;

class CreateMedicineLogAction
{
    /**
     * @throws \Exception 422 if flock is not active or stock insufficient
     */
    public function execute(Flock $flock, int $userId, array $data): FlockMedicine
    {
        if ($flock->status !== 'active') {
            throw new \Exception('لا يمكن تسجيل دواء على فوج غير نشط', 422);
        }

        return DB::transaction(function () use ($flock, $userId, $data): FlockMedicine {
            $item    = \App\Models\Item::findOrFail($data['item_id']);
            $realQty = $data['quantity'] * ($item->unit_value ?? 1);

            // ── lockForUpdate يمنع race condition عند الطلبات المتزامنة ───────
            $warehouseItem = WarehouseItem::where('item_id', $data['item_id'])
                ->whereHas('warehouse', fn ($q) => $q->where('farm_id', $flock->farm_id)->where('is_active', true))
                ->lockForUpdate()
                ->first();

            // ── الـ warehouse اختياري: إذا لم يوجد نسجل بدون حركة مخزون ────
            $txnId = null;

            if ($warehouseItem) {
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
                    'source_module'     => 'flock_medicine',
                    'computed_quantity' => $realQty,
                    'created_by'        => $userId,
                    'updated_by'        => $userId,
                ]);

                $warehouseItem->decrement('current_quantity', $realQty);
                $txnId = $txn->id;
            }

            return FlockMedicine::create([
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

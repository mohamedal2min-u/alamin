<?php

namespace App\Actions\Inventory;

use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\WarehouseItem;
use Illuminate\Support\Facades\DB;

class CreateShipmentAction
{
    /**
     * @param array{
     *   item_id: int,
     *   warehouse_id: int,
     *   transaction_date: string,
     *   original_quantity: float,
     *   unit_price?: float|null,
     *   total_amount?: float|null,
     *   payment_status?: string,
     *   supplier_name?: string|null,
     *   invoice_no?: string|null,
     *   notes?: string|null,
     *   attachment_path?: string|null,
     * } $data
     */
    public function execute(int $farmId, int $userId, array $data): InventoryTransaction
    {
        return DB::transaction(function () use ($farmId, $userId, $data): InventoryTransaction {
            $item        = Item::findOrFail($data['item_id']);
            $originalQty = (float) $data['original_quantity'];
            $computedQty = $originalQty * (float) $item->unit_value;

            // أضف للمستودع — أنشئ السجل إذا لم يوجد
            $warehouseItem = WarehouseItem::firstOrCreate(
                [
                    'warehouse_id' => $data['warehouse_id'],
                    'item_id'      => $data['item_id'],
                    'farm_id'      => $farmId,
                ],
                ['current_quantity' => 0, 'status' => 'active']
            );

            $warehouseItem->increment('current_quantity', $computedQty);
            $warehouseItem->update([
                'last_in_at'   => now(),
                'average_cost' => $data['unit_price'] ?? $warehouseItem->average_cost,
            ]);

            return InventoryTransaction::create([
                'farm_id'           => $farmId,
                'warehouse_id'      => $data['warehouse_id'],
                'item_id'           => $data['item_id'],
                'transaction_date'  => $data['transaction_date'],
                'transaction_type'  => 'purchase',
                'direction'         => 'in',
                'source_module'     => 'manual',
                'original_quantity' => $originalQty,
                'computed_quantity' => $computedQty,
                'unit_price'        => $data['unit_price'] ?? null,
                'total_amount'      => $data['total_amount'] ?? null,
                'payment_status'    => $data['payment_status'] ?? 'paid',
                'supplier_name'     => $data['supplier_name'] ?? null,
                'invoice_no'        => $data['invoice_no'] ?? null,
                'notes'             => $data['notes'] ?? null,
                'attachment_path'   => $data['attachment_path'] ?? null,
                'created_by'        => $userId,
                'updated_by'        => $userId,
            ]);
        });
    }
}

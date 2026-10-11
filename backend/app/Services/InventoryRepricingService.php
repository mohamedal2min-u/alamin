<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\WarehouseItem;

/**
 * عند تسعير شحنة كانت بلا سعر (يحدده المحاسب لاحقاً) يجب أن ينعكس السعر على المخزون:
 * متوسط التكلفة، وما استُهلك منها قبل التسعير (وإلا بقيت تكلفة العلف/الدواء صفراً
 * ولا يمكن إغلاق الفوج).
 */
class InventoryRepricingService
{
    public function repriceItem(int $farmId, int $itemId): void
    {
        $in = InventoryTransaction::where('farm_id', $farmId)
            ->where('item_id', $itemId)
            ->where('direction', 'in')
            ->where('transaction_type', 'purchase')
            ->where('unit_price', '>', 0)
            ->where('total_amount', '>', 0)
            ->where('computed_quantity', '>', 0)
            ->get(['total_amount', 'computed_quantity']);

        $qty = (float) $in->sum('computed_quantity');
        if ($qty <= 0) {
            return;
        }

        // متوسط مرجّح لتكلفة الوحدة الأساسية (كغ/لتر/...) من كل الشحنات المسعَّرة
        $avg = round((float) $in->sum('total_amount') / $qty, 6);

        WarehouseItem::where('farm_id', $farmId)->where('item_id', $itemId)->update(['average_cost' => $avg]);

        // الاستهلاك غير المسعَّر في أفواج لم تُغلق بعد يأخذ المتوسط الجديد
        InventoryTransaction::where('farm_id', $farmId)
            ->where('item_id', $itemId)
            ->where('direction', 'out')
            ->where('transaction_type', 'consumption')
            ->where(function ($q): void {
                $q->whereNull('unit_price')->orWhere('unit_price', '<=', 0)
                    ->orWhereNull('total_amount')->orWhere('total_amount', '<=', 0);
            })
            ->whereHas('flock', fn ($q) => $q->whereNotIn('status', ['closed', 'cancelled']))
            ->get()
            ->each(function (InventoryTransaction $t) use ($avg): void {
                $t->update([
                    'unit_price' => $avg,
                    'total_amount' => round((float) $t->computed_quantity * $avg, 2),
                ]);
            });
    }
}

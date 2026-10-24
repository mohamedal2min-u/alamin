<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\WarehouseItem;

/**
 * عند تسعير شحنة كانت بلا سعر (يحدده المحاسب لاحقاً) يجب أن ينعكس السعر على المخزون.
 * يُعاد بناء متوسط التكلفة المتحرك من أول حركة للصنف بالترتيب الزمني:
 *  - الشراء المسعَّر يضيف قيمته الفعلية، وغير المسعَّر بعد يدخل بالمتوسط الحالي (تقدير يُصحَّح عند تسعيره)
 *  - الاستهلاك في فوج مفتوح يُعاد تسعيره بالمتوسط في لحظته (لا فقط ما كان بلا سعر)
 *  - الاستهلاك في فوج مغلق يبقى كما سُجّل (ربحه وُزِّع) ويُخصم بقيمته المسجّلة
 *  - التسويات تدخل/تخرج بالمتوسط في لحظتها
 * وفي النهاية متوسط المستودع = قيمة المتبقي ÷ كميته.
 */
class InventoryRepricingService
{
    public function repriceItem(int $farmId, int $itemId): void
    {
        $txns = InventoryTransaction::with('flock:id,status')
            ->where('farm_id', $farmId)
            ->where('item_id', $itemId)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $qty = 0.0;
        $value = 0.0;
        $avg = 0.0;

        foreach ($txns as $t) {
            $q = (float) $t->computed_quantity;
            if ($q <= 0) {
                continue;
            }

            if ($t->direction === 'in') {
                $priced = $t->transaction_type === 'purchase' && (float) $t->total_amount > 0;
                $value += $priced ? (float) $t->total_amount : $q * $avg;
                $qty += $q;
            } else {
                $frozen = $t->transaction_type === 'consumption'
                    && in_array($t->flock?->status, ['closed', 'cancelled'], true);

                if ($frozen) {
                    $value -= (float) $t->total_amount;
                } else {
                    if ($t->transaction_type === 'consumption' && $avg > 0) {
                        $total = round($q * $avg, 2);
                        if (abs((float) $t->total_amount - $total) > 0.004 || abs((float) $t->unit_price - $avg) > 0.0000005) {
                            $t->update(['unit_price' => round($avg, 6), 'total_amount' => $total]);
                        }
                    }
                    $value -= $q * $avg;
                }
                $qty -= $q;
            }

            if ($qty > 0.000001) {
                $avg = max(0.0, $value / $qty);
            } else {
                // نفد المخزون: لا قيمة متبقية، والمتوسط الأخير يبقى مرجعاً للحركة التالية
                $qty = 0.0;
                $value = 0.0;
            }
        }

        if ($avg > 0) {
            WarehouseItem::where('farm_id', $farmId)->where('item_id', $itemId)->update(['average_cost' => round($avg, 6)]);
        }
    }
}

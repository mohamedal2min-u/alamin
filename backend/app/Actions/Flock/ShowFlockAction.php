<?php

namespace App\Actions\Flock;

use App\Models\Flock;

class ShowFlockAction
{
    /**
     * تحقق من أن الفوج ينتمي للمزرعة المحددة ثم أعده.
     *
     * @throws \Exception 404 إذا لم يُوجد الفوج في هذه المزرعة
     */
    public function execute(int $farmId, int $flockId): Flock
    {
        $flock = Flock::where('id', $flockId)
            ->where('farm_id', $farmId)
            ->withSum('mortalities', 'quantity')
            ->withSum('expenses', 'total_amount')
            ->withSum('waterLogs', 'total_amount')
            ->withSum(['inventoryTransactions as inventory_consumption_sum' => function ($query) {
                $query->where('direction', 'out')->where('transaction_type', 'consumption');
            }], 'total_amount')
            ->withSum('sales', 'net_amount')
            ->withSum('saleItems', 'birds_count')
            ->first();

        if (! $flock) {
            throw new \Exception('الفوج غير موجود', 404);
        }

        return $flock;
    }
}

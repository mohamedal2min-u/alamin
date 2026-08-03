<?php

namespace App\Actions\Flock;

use App\Models\Flock;
use Illuminate\Database\Eloquent\Collection;

class ListFlocksAction
{
    /**
     * أعد قائمة أفواج المزرعة مرتبة: النشط أولاً، ثم الأحدث.
     *
     * @return Collection<int, Flock>
     */
    public function execute(int $farmId): Collection
    {
        return Flock::where('farm_id', $farmId)
            ->withSum('mortalities', 'quantity')
            ->withSum(['expenses as expenses_sum_total_amount' => function ($query) {
                // Purchase-debt expenses (linked to an inventory purchase) are excluded — their
                // cost is already counted via inventory_consumption_sum once the stock is consumed.
                $query->whereNull('linked_inventory_transaction_id');
            }], 'total_amount')
            ->withSum('waterLogs', 'total_amount')
            ->withSum(['inventoryTransactions as inventory_consumption_sum' => function ($query) {
                $query->where('direction', 'out')->where('transaction_type', 'consumption');
            }], 'total_amount')
            ->withSum('sales', 'net_amount')
            ->withSum('saleItems', 'birds_count')
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
            ->orderByDesc('start_date')
            ->get();
    }
}

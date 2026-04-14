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
            ->withSum('expenses', 'total_amount')
            ->withSum('sales', 'net_amount')
            ->withSum('saleItems', 'birds_count')
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
            ->orderByDesc('start_date')
            ->get();
    }
}

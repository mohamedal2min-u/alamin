<?php

namespace App\Actions\Flock;

use App\Models\Flock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ResolveFlockZeroDateAction
{
    /**
     * أعد التاريخ الذي وصل عنده العدد المتبقي من الفوج إلى صفر (تجميع المبيعات
     * والنفوق يومياً وإيجاد أول يوم يصل فيه المجموع التراكمي إلى initial_count)،
     * أو null إن لم يصل العدد للصفر بعد.
     */
    public function execute(Flock $flock): ?Carbon
    {
        $saleTotals = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sale_items.flock_id', $flock->id)
            ->selectRaw('sales.sale_date as event_date, SUM(sale_items.birds_count) as event_count')
            ->groupBy('sales.sale_date')
            ->get();

        $mortalityTotals = DB::table('flock_mortalities')
            ->where('flock_id', $flock->id)
            ->selectRaw('entry_date as event_date, SUM(quantity) as event_count')
            ->groupBy('entry_date')
            ->get();

        $byDate = [];
        foreach ($saleTotals->concat($mortalityTotals) as $row) {
            $date = (string) $row->event_date;
            $byDate[$date] = ($byDate[$date] ?? 0) + (int) $row->event_count;
        }

        ksort($byDate);

        $cumulative = 0;
        foreach ($byDate as $date => $count) {
            $cumulative += $count;
            if ($cumulative >= $flock->initial_count) {
                return Carbon::parse($date);
            }
        }

        return null;
    }
}

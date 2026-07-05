<?php

namespace App\Actions\Flock;

use App\Models\Flock;
use App\Models\FlockFeedLog;
use App\Models\FlockMedicine;
use App\Models\FlockMortality;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetDailySummaryAction
{
    public function execute(Flock $flock): array
    {
        $startDate = Carbon::parse($flock->start_date)->startOfDay();
        $endDate = $flock->close_date 
            ? Carbon::parse($flock->close_date)->endOfDay() 
            : Carbon::today()->endOfDay();

        // 1. Fetch aggregates grouped by date
        $mortalities = FlockMortality::where('flock_id', $flock->id)
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->select('entry_date', DB::raw('SUM(quantity) as total'))
            ->groupBy('entry_date')
            ->pluck('total', 'entry_date');

        $feedLogs = FlockFeedLog::where('flock_id', $flock->id)
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->select('entry_date', DB::raw('SUM(quantity) as total'))
            ->groupBy('entry_date')
            ->pluck('total', 'entry_date');

        $medicines = FlockMedicine::with('item')
            ->where('flock_id', $flock->id)
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->get()
            ->groupBy(function($item) {
                return \Carbon\Carbon::parse($item->entry_date)->toDateString();
            });

        $expenses = Expense::where('flock_id', $flock->id)
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->select('entry_date', DB::raw('SUM(total_amount) as total'))
            ->groupBy('entry_date')
            ->pluck('total', 'entry_date');

        // 2. Build the result for each day in the cycle
        $summary = [];
        $totalDays = $startDate->diffInDays($endDate) + 1;

        for ($i = 0; $i < $totalDays; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $dateStr = $currentDate->toDateString();
            
            // Age in days relative to start_date
            $ageDays = $i + 1;

            $dailyMeds = [];
            $dayMedsTotal = 0;
            if (isset($medicines[$dateStr])) {
                $grouped = $medicines[$dateStr]->groupBy('item_id');
                foreach ($grouped as $entries) {
                    $first = $entries->first();
                    $qty = $entries->sum('quantity');
                    $dailyMeds[] = [
                        'name' => $first->item ? $first->item->name : 'صنف غير معروف',
                        'quantity' => (float) $qty,
                        'unit' => $first->unit_label ?? 'عبوة'
                    ];
                    $dayMedsTotal += $qty;
                }
            }

            $summary[] = [
                'day' => $ageDays,
                'date' => $dateStr,
                'mortality' => (int) ($mortalities[$dateStr] ?? 0),
                'feed' => (float) ($feedLogs[$dateStr] ?? 0),
                'medicine' => $dayMedsTotal,
                'medicine_details' => $dailyMeds,
                'expense' => (float) ($expenses[$dateStr] ?? 0),
            ];
        }

        // Return latest days first (newest on top)
        return array_reverse($summary);
    }
}

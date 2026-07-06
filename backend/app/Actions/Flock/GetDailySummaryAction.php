<?php

namespace App\Actions\Flock;

use App\Models\Flock;
use App\Models\FlockFeedLog;
use App\Models\FlockMedicine;
use App\Models\FlockMortality;
use App\Models\Expense;
use App\Models\FlockWaterLog;
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

        $feedLogs = FlockFeedLog::with('inventoryTransaction')
            ->where('flock_id', $flock->id)
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->get()
            ->groupBy(function($item) {
                return \Carbon\Carbon::parse($item->entry_date)->toDateString();
            });

        $medicines = FlockMedicine::with(['item', 'inventoryTransaction'])
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

        $waterCosts = FlockWaterLog::where('flock_id', $flock->id)
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
            $dayMedsCost = 0;
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
                }
                $dayMedsCost = $medicines[$dateStr]->sum(function ($log) {
                    return $log->inventoryTransaction ? $log->inventoryTransaction->total_amount : 0;
                });
            }

            $dayFeedQty = 0;
            $dayFeedCost = 0;
            if (isset($feedLogs[$dateStr])) {
                $dayFeedQty = $feedLogs[$dateStr]->sum('quantity');
                $dayFeedCost = $feedLogs[$dateStr]->sum(function ($log) {
                    return $log->inventoryTransaction ? $log->inventoryTransaction->total_amount : 0;
                });
            }

            $expenseTableAmount = (float) ($expenses[$dateStr] ?? 0);
            $waterAmount = (float) ($waterCosts[$dateStr] ?? 0);
            
            // Total daily expense includes generic expenses + feed + medicine + water
            $totalDailyExpense = $expenseTableAmount + $waterAmount + $dayFeedCost + $dayMedsCost;

            $summary[] = [
                'day' => $ageDays,
                'date' => $dateStr,
                'mortality' => (int) ($mortalities[$dateStr] ?? 0),
                'feed' => (float) $dayFeedQty,
                'medicine_cost' => (float) $dayMedsCost,
                'medicine_details' => $dailyMeds,
                'expense' => (float) $totalDailyExpense,
            ];
        }

        // Return latest days first (newest on top)
        return array_reverse($summary);
    }
}

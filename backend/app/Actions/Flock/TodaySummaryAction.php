<?php

namespace App\Actions\Flock;

use App\Models\Expense;
use App\Models\Flock;
use App\Models\FlockFeedLog;
use App\Models\FlockMedicine;
use App\Models\FlockMortality;
use App\Models\FlockWaterLog;

class TodaySummaryAction
{
    public function execute(Flock $flock, ?string $date = null): array
    {
        $today = $date ?: now()->toDateString();

        // ── Flock Stats — use preloaded aggregates from ShowFlockAction to avoid duplicate queries ──
        $totalMortalityAcrossTime = (int) ($flock->mortalities_sum_quantity ?? FlockMortality::where('flock_id', $flock->id)->sum('quantity'));
        $totalBirdsSold = (int) ($flock->sale_items_sum_birds_count ?? \App\Models\SaleItem::where('flock_id', $flock->id)->sum('birds_count'));
        $remainingCount = $flock->initial_count - $totalMortalityAcrossTime - $totalBirdsSold;
        $currentAgeDays = \Carbon\Carbon::parse($flock->start_date)->startOfDay()
            ->diffInDays(\Carbon\Carbon::today()->startOfDay()) + 1;

        $flockInfo = [
            'name'             => $flock->name,
            'initial_count'    => (int) $flock->initial_count,
            'remaining_count'  => (int) $remainingCount,
            'current_age_days' => (int) $currentAgeDays,
            'start_date'       => $flock->start_date?->format('Y-m-d'),
            'status'           => $flock->status,
        ];

        // ── Today's Logs ──────────────────────────────────────────────────────────
        $mortalities = FlockMortality::where('flock_id', $flock->id)
            ->whereDate('entry_date', $today)
            ->get(['quantity', 'reason', 'created_at']);

        $feedLogs = FlockFeedLog::where('flock_id', $flock->id)
            ->whereDate('entry_date', $today)
            ->with('item:id,name,content_unit')
            ->get(['item_id', 'quantity', 'unit_label', 'created_at']);

        // Fetch ALL medicines, including what might be water
        $allMedicines = FlockMedicine::where('flock_id', $flock->id)
            ->whereDate('entry_date', $today)
            ->with(['item:id,name,content_unit,item_type_id', 'item.itemType:id,code'])
            ->get(['item_id', 'quantity', 'unit_label', 'created_at']);

        // Split medicines into "Water" and "Actual Medicines" — use itemType.code
        $waterEntries = $allMedicines->filter(
            fn ($m) => $m->item?->itemType?->code === 'water'
        );

        $medicineEntries = $allMedicines->reject(
            fn ($m) => $m->item?->itemType?->code === 'water'
        );

        $waterLogs = FlockWaterLog::where('flock_id', $flock->id)
            ->whereDate('entry_date', $today)
            ->get(['quantity', 'unit_label', 'created_at']);

        // Exclude system categories (chick purchase, inventory purchase) — not operational daily expenses
        $expenses = Expense::where('flock_id', $flock->id)
            ->whereDate('entry_date', $today)
            ->whereHas('expenseCategory', fn ($q) => $q->where('is_system', false))
            ->get(['expense_type', 'total_amount', 'created_at']);

        $temperatures = \App\Models\FlockTemperatureLog::where('flock_id', $flock->id)
            ->whereDate('log_date', $today)
            ->get(['time_of_day', 'temperature', 'created_at']);

        return [
            'date'        => $today,
            'flock_info'  => $flockInfo,
            'mortalities' => [
                'entries' => $mortalities->map(fn ($m) => [
                    'quantity' => (int) $m->quantity,
                    'reason'   => $m->reason,
                    'time'     => $m->created_at?->format('H:i') ?? '--:--',
                ])->values()->toArray(),
                'total' => (int) $mortalities->sum('quantity'),
            ],
            'feed' => [
                'entries' => $feedLogs->map(fn ($f) => [
                    'item_name'  => $f->item?->name,
                    'quantity'   => (float) $f->quantity,
                    'unit_label' => $f->unit_label ?? $f->item?->content_unit,
                    'time'       => $f->created_at?->format('H:i') ?? '--:--',
                ])->values()->toArray(),
                'total' => (float) $feedLogs->sum('quantity'),
            ],
            'medicines' => [
                'entries' => $medicineEntries->map(fn ($m) => [
                    'item_name'  => $m->item?->name,
                    'quantity'   => (float) $m->quantity,
                    'unit_label' => $m->unit_label ?? $m->item?->content_unit,
                    'time'       => $m->created_at?->format('H:i') ?? '--:--',
                ])->values()->toArray(),
                'total' => (float) $medicineEntries->sum('quantity'),
            ],
            'water' => [
                'entries' => $waterLogs->map(fn ($w) => [
                    'quantity'   => (float) $w->quantity,
                    'unit_label' => $w->unit_label ?? 'صهريج',
                    'time'       => $w->created_at?->format('H:i') ?? '--:--',
                ])->values()->toArray(),
                'total' => (float) $waterLogs->sum('quantity'),
            ],
            'expenses' => [
                'entries' => $expenses->map(fn ($e) => [
                    'type'         => $e->expense_type ?? 'مصروف غير محدد',
                    'total_amount' => (float) ($e->total_amount ?? 0),
                    'time'         => $e->created_at?->format('H:i') ?? '--:--',
                ])->values()->toArray(),
                'total' => (float) $expenses->sum('total_amount'),
            ],
            'temperatures' => [
                'entries' => $temperatures->map(fn ($t) => [
                    'time_of_day' => $t->time_of_day,
                    'temperature' => (float) $t->temperature,
                    'time'        => $t->created_at?->format('H:i') ?? '--:--',
                ])->values()->toArray(),
            ],
        ];
    }
}

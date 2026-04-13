<?php

namespace App\Actions\Flock;

use App\Models\Expense;
use App\Models\Flock;
use App\Models\FlockFeedLog;
use App\Models\FlockMedicine;
use App\Models\FlockMortality;

class TodaySummaryAction
{
    public function execute(Flock $flock, ?string $date = null): array
    {
        $today = $date ?: now()->toDateString();

        // ── Flock Stats ───────────────────────────────────────────────────────────
        $totalMortalityAcrossTime = FlockMortality::where('flock_id', $flock->id)->sum('quantity');
        $remainingCount = $flock->initial_count - $totalMortalityAcrossTime;
        $currentAgeDays = now()->diffInDays($flock->start_date);

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
            ->with('worker:id,name')
            ->get(['quantity', 'reason', 'worker_id', 'created_at']);

        $feedLogs = FlockFeedLog::where('flock_id', $flock->id)
            ->whereDate('entry_date', $today)
            ->with(['item:id,name,content_unit', 'worker:id,name'])
            ->get(['item_id', 'quantity', 'unit_label', 'worker_id', 'created_at']);

        // Fetch ALL medicines, including what might be water
        $allMedicines = FlockMedicine::where('flock_id', $flock->id)
            ->whereDate('entry_date', $today)
            ->with(['item:id,name,content_unit', 'worker:id,name'])
            ->get(['item_id', 'quantity', 'unit_label', 'worker_id', 'created_at']);

        // Split medicines into "Water" and "Actual Medicines"
        $waterEntries = $allMedicines->filter(function ($m) {
            $name = $m->item?->name ?? '';
            return str_contains($name, 'ماء') || str_contains(strtolower($name), 'water');
        });

        $medicineEntries = $allMedicines->reject(function ($m) {
            $name = $m->item?->name ?? '';
            return str_contains($name, 'ماء') || str_contains(strtolower($name), 'water');
        });

        $expenses = Expense::where('flock_id', $flock->id)
            ->whereDate('entry_date', $today)
            ->with('worker:id,name')
            ->get(['expense_type', 'total_amount', 'worker_id', 'created_at']);

        $temperatures = \App\Models\FlockTemperatureLog::where('flock_id', $flock->id)
            ->whereDate('log_date', $today)
            ->with('creator:id,name')
            ->get(['time_of_day', 'temperature', 'created_by', 'created_at']);

        return [
            'date'        => $today,
            'flock_info'  => $flockInfo,
            'mortalities' => [
                'entries' => $mortalities->map(fn ($m) => [
                    'quantity'    => (int) $m->quantity,
                    'reason'      => $m->reason,
                    'worker_name' => $m->worker?->name ?? 'غير معروف',
                    'time'        => $m->created_at?->format('H:i'),
                ])->values()->toArray(),
                'total' => (int) $mortalities->sum('quantity'),
            ],
            'feed' => [
                'entries' => $feedLogs->map(fn ($f) => [
                    'item_name'   => $f->item?->name,
                    'quantity'    => (float) $f->quantity,
                    'unit_label'  => $f->unit_label ?? $f->item?->content_unit,
                    'worker_name' => $f->worker?->name ?? 'غير معروف',
                    'time'        => $f->created_at?->format('H:i'),
                ])->values()->toArray(),
                'total' => (float) $feedLogs->sum('quantity'),
            ],
            'medicines' => [
                'entries' => $medicineEntries->map(fn ($m) => [
                    'item_name'   => $m->item?->name,
                    'quantity'    => (float) $m->quantity,
                    'unit_label'  => $m->unit_label ?? $m->item?->content_unit,
                    'worker_name' => $m->worker?->name ?? 'غير معروف',
                    'time'        => $m->created_at?->format('H:i'),
                ])->values()->toArray(),
                'total' => (float) $medicineEntries->sum('quantity'),
            ],
            'water' => [
                'entries' => $waterEntries->map(fn ($m) => [
                    'quantity'    => (float) $m->quantity,
                    'unit_label'  => $m->unit_label ?? 'لتر',
                    'worker_name' => $m->worker?->name ?? 'غير معروف',
                    'time'        => $m->created_at?->format('H:i'),
                ])->values()->toArray(),
                'total' => (float) $waterEntries->sum('quantity'),
            ],
            'expenses' => [
                'entries' => $expenses->map(fn ($e) => [
                    'type'         => $e->expense_type,
                    'total_amount' => (float) $e->total_amount,
                    'worker_name'  => $e->worker?->name ?? 'غير معروف',
                    'time'         => $e->created_at?->format('H:i'),
                ])->values()->toArray(),
                'total' => (float) $expenses->sum('total_amount'),
            ],
            'temperatures' => [
                'entries' => $temperatures->map(fn ($t) => [
                    'time_of_day' => $t->time_of_day,
                    'temperature' => (float) $t->temperature,
                    'worker_name' => $t->creator?->name ?? 'غير معروف',
                    'time'        => $t->created_at?->format('H:i'),
                ])->values()->toArray(),
            ],
        ];
    }
}

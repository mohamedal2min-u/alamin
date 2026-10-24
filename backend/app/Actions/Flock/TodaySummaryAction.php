<?php

namespace App\Actions\Flock;

use App\Models\Expense;
use App\Models\Flock;
use App\Models\FlockFeedLog;
use App\Models\FlockMedicine;
use App\Models\FlockMortality;
use App\Models\FlockTemperatureLog;
use App\Models\FlockWaterLog;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\DailyEntryCorrectionService;
use Carbon\Carbon;

class TodaySummaryAction
{
    public function execute(Flock $flock, ?string $date = null, ?User $viewer = null, bool $isSuperAdmin = false): array
    {
        $today = $date ?: now()->toDateString();
        // أعلام التعديل/الإلغاء لكل سجل حسب صلاحية المستخدم الحالي
        $corrections = app(DailyEntryCorrectionService::class);
        $entryMeta = fn (string $type, $m) => ['id' => $m->id, 'entry_type' => $type, 'notes' => $m->notes ?? null]
            + $corrections->flags($viewer, $isSuperAdmin, $m, $flock);

        // ── Flock Stats — use preloaded aggregates from ShowFlockAction to avoid duplicate queries ──
        $totalMortalityAcrossTime = (int) ($flock->mortalities_sum_quantity ?? FlockMortality::where('flock_id', $flock->id)->sum('quantity'));
        $totalBirdsSold = (int) ($flock->sale_items_sum_birds_count ?? SaleItem::where('flock_id', $flock->id)->sum('birds_count'));
        $remainingCount = $flock->initial_count - $totalMortalityAcrossTime - $totalBirdsSold;
        // العمر يتوقف عند يوم بيع/نفوق آخر دجاجة بدل الاستمرار مع «اليوم»
        $ageEnd = Carbon::today()->startOfDay();
        if ($remainingCount <= 0) {
            $zeroDate = (new ResolveFlockZeroDateAction)->execute($flock);
            if ($zeroDate) {
                $ageEnd = $zeroDate->startOfDay();
            }
        }
        $currentAgeDays = Carbon::parse($flock->start_date)->startOfDay()->diffInDays($ageEnd) + 1;

        $flockInfo = [
            'name' => $flock->name,
            'initial_count' => (int) $flock->initial_count,
            'remaining_count' => (int) $remainingCount,
            'current_age_days' => (int) $currentAgeDays,
            'start_date' => $flock->start_date?->format('Y-m-d'),
            'status' => $flock->status,
        ];

        // ── Today's Logs ──────────────────────────────────────────────────────────
        $mortalities = FlockMortality::where('flock_id', $flock->id)
            ->whereDate('entry_date', $today)
            ->get(['id', 'farm_id', 'flock_id', 'quantity', 'reason', 'notes', 'created_by', 'created_at']);

        $feedLogs = FlockFeedLog::where('flock_id', $flock->id)
            ->whereDate('entry_date', $today)
            ->with('item:id,name,content_unit')
            ->get(['id', 'farm_id', 'flock_id', 'item_id', 'quantity', 'unit_label', 'notes', 'created_by', 'created_at']);

        // Fetch ALL medicines, including what might be water
        $allMedicines = FlockMedicine::where('flock_id', $flock->id)
            ->whereDate('entry_date', $today)
            ->with(['item:id,name,content_unit,item_type_id', 'item.itemType:id,code'])
            ->get(['id', 'farm_id', 'flock_id', 'item_id', 'quantity', 'unit_label', 'notes', 'created_by', 'created_at']);

        // Split medicines into "Water" and "Actual Medicines" — use itemType.code
        $waterEntries = $allMedicines->filter(
            fn ($m) => $m->item?->itemType?->code === 'water'
        );

        $medicineEntries = $allMedicines->reject(
            fn ($m) => $m->item?->itemType?->code === 'water'
        );

        $waterLogs = FlockWaterLog::where('flock_id', $flock->id)
            ->whereDate('entry_date', $today)
            ->get(['id', 'farm_id', 'flock_id', 'quantity', 'unit_label', 'notes', 'created_by', 'created_at']);

        // Exclude the two one-off capital categories (chick purchase, inventory purchase) —
        // they're the only categories with no code, so that's what identifies them here — and
        // purchase-debt expenses linked to an inventory purchase (feed/medicine shipments or
        // shortage auto-purchases), whose cost already shows up in the feed/medicine/water
        // totals above once the stock is consumed, to avoid double-counting it here too.
        $expenses = Expense::where('flock_id', $flock->id)
            ->whereDate('entry_date', $today)
            ->whereNull('linked_inventory_transaction_id')
            ->whereHas('expenseCategory', fn ($q) => $q->whereNotNull('code'))
            ->get(['expense_type', 'total_amount', 'created_at']);

        $temperatures = FlockTemperatureLog::where('flock_id', $flock->id)
            ->whereDate('log_date', $today)
            ->get(['id', 'farm_id', 'flock_id', 'time_of_day', 'temperature', 'notes', 'created_by', 'created_at']);

        return [
            'date' => $today,
            'flock_info' => $flockInfo,
            'mortalities' => [
                'entries' => $mortalities->map(fn ($m) => $entryMeta('mortality', $m) + [
                    'quantity' => (int) $m->quantity,
                    'reason' => $m->reason,
                    'time' => $m->created_at?->format('H:i') ?? '--:--',
                ])->values()->toArray(),
                'total' => (int) $mortalities->sum('quantity'),
            ],
            'feed' => [
                'entries' => $feedLogs->map(fn ($f) => $entryMeta('feed_log', $f) + [
                    'item_name' => $f->item?->name,
                    'quantity' => (float) $f->quantity,
                    'unit_label' => $f->unit_label ?? $f->item?->content_unit,
                    'time' => $f->created_at?->format('H:i') ?? '--:--',
                ])->values()->toArray(),
                'total' => (float) $feedLogs->sum('quantity'),
            ],
            'medicines' => [
                'entries' => $medicineEntries->map(fn ($m) => $entryMeta('medicine_log', $m) + [
                    'item_name' => $m->item?->name,
                    'quantity' => (float) $m->quantity,
                    'unit_label' => $m->unit_label ?? $m->item?->content_unit,
                    'time' => $m->created_at?->format('H:i') ?? '--:--',
                ])->values()->toArray(),
                'total' => (float) $medicineEntries->sum('quantity'),
            ],
            'water' => [
                'entries' => $waterLogs->map(fn ($w) => $entryMeta('water_log', $w) + [
                    'quantity' => (float) $w->quantity,
                    'unit_label' => $w->unit_label ?? 'صهريج',
                    'time' => $w->created_at?->format('H:i') ?? '--:--',
                ])->values()->toArray(),
                'total' => (float) $waterLogs->sum('quantity'),
            ],
            'expenses' => [
                'entries' => $expenses->map(fn ($e) => [
                    'type' => $e->expense_type ?? 'مصروف غير محدد',
                    'total_amount' => (float) ($e->total_amount ?? 0),
                    'time' => $e->created_at?->format('H:i') ?? '--:--',
                ])->values()->toArray(),
                'total' => (float) $expenses->sum('total_amount'),
            ],
            'temperatures' => [
                'entries' => $temperatures->map(fn ($t) => $entryMeta('temperature_log', $t) + [
                    'time_of_day' => $t->time_of_day,
                    'temperature' => (float) $t->temperature,
                    'time' => $t->created_at?->format('H:i') ?? '--:--',
                ])->values()->toArray(),
            ],
        ];
    }
}

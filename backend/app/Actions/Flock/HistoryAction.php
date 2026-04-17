<?php

namespace App\Actions\Flock;

use App\Models\Flock;
use App\Models\FlockFeedLog;
use App\Models\FlockMedicine;
use App\Models\FlockMortality;
use App\Models\FlockTemperatureLog;
use App\Models\FlockWaterLog;
use Carbon\Carbon;

class HistoryAction
{
    public function execute(Flock $flock): array
    {
        $days = [];
        $today = Carbon::today();
        
        // Fetch logs for the last 7 days in one go to be efficient
        $startDate = $today->copy()->subDays(6)->toDateString();
        $endDate = $today->toDateString();

        $mortalities = FlockMortality::where('flock_id', $flock->id)
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->with('worker:id,name')
            ->get();

        $feedLogs = FlockFeedLog::where('flock_id', $flock->id)
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->with(['item:id,name,content_unit', 'worker:id,name'])
            ->get();

        $medicines = FlockMedicine::where('flock_id', $flock->id)
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->with(['item:id,name,content_unit', 'worker:id,name'])
            ->get();

        $temperatures = FlockTemperatureLog::where('flock_id', $flock->id)
            ->whereBetween('log_date', [$startDate, $endDate])
            ->with('creator:id,name')
            ->get();

        $waterLogs = FlockWaterLog::where('flock_id', $flock->id)
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->with('worker:id,name')
            ->get();

        // Build the structure for each day, starting from Today and going backwards
        for ($i = 0; $i < 7; $i++) {
            $currentDate = $today->copy()->subDays($i);
            $dateStr = $currentDate->toDateString();
            
            $dayMortalities = $mortalities->filter(fn($m) => Carbon::parse($m->entry_date)->toDateString() === $dateStr);
            $dayFeed = $feedLogs->filter(fn($f) => Carbon::parse($f->entry_date)->toDateString() === $dateStr);
            $dayMedicines = $medicines->filter(fn($m) => Carbon::parse($m->entry_date)->toDateString() === $dateStr);
            $dayTemps = $temperatures->filter(fn($t) => Carbon::parse($t->log_date)->toDateString() === $dateStr);
            $dayWater = $waterLogs->filter(fn($w) => Carbon::parse($w->entry_date)->toDateString() === $dateStr);

            // Calculate completion rate based on core tasks (Mortality, Feed, Medicine/Water)
            $tasksCount = 3;
            $completedCount = 0;
            if ($dayMortalities->isNotEmpty()) $completedCount++;
            if ($dayFeed->isNotEmpty()) $completedCount++;
            if ($dayMedicines->isNotEmpty() || $dayWater->isNotEmpty()) $completedCount++;
            
            $completionRate = ($completedCount / $tasksCount) * 100;

            // Generate Timeline
            $timeline = collect();

            $dayMortalities->each(fn($m) => $timeline->push([
                'type' => 'mortality',
                'title' => 'تسجيل نافق',
                'detail' => "العدد: {$m->quantity} طيور - السبب: " . ($m->reason ?: 'غير محدد'),
                'time' => $m->created_at?->format('H:i') ?? '--:--',
                'worker' => $m->worker?->name ?? 'غير معروف'
            ]));

            $dayFeed->each(fn($f) => $timeline->push([
                'type' => 'feed',
                'title' => 'إضافة علف',
                'detail' => "تمت إضافة {$f->quantity} " . ($f->unit_label ?: $f->item?->content_unit ?: 'كجم') . " من {$f->item?->name}",
                'time' => $f->created_at?->format('H:i') ?? '--:--',
                'worker' => $f->worker?->name ?? 'غير معروف'
            ]));

            $dayMedicines->each(fn($m) => $timeline->push([
                'type' => 'medicine',
                'title' => 'تسجيل دواء/إضافات',
                'detail' => "تم صرف {$m->quantity} " . ($m->unit_label ?: $m->item?->content_unit ?: 'وحدة') . " من {$m->item?->name}",
                'time' => $m->created_at?->format('H:i') ?? '--:--',
                'worker' => $m->worker?->name ?? 'غير معروف'
            ]));

            $dayTemps->each(fn($t) => $timeline->push([
                'type' => 'temp',
                'title' => 'قياس حرارة',
                'detail' => "درجة الحرارة: {$t->temperature}° مئوية",
                'time' => $t->created_at?->format('H:i') ?? '--:--',
                'worker' => $t->creator?->name ?? 'غير معروف'
            ]));

            $dayWater->each(fn($w) => $timeline->push([
                'type' => 'medicine', // Water is often grouped with medicine/additives in summary
                'title' => 'تسجيل استهلاك مياه',
                'detail' => "الكمية: {$w->quantity} " . ($w->unit_label ?: 'صهريج'),
                'time' => $w->created_at?->format('H:i') ?? '--:--',
                'worker' => $w->worker?->name ?? 'غير معروف'
            ]));

            $ageDays = Carbon::parse($flock->start_date)->startOfDay()->diffInDays($currentDate->startOfDay(), false) + 1;
            
            $days[] = [
                'date' => $dateStr,
                'age_days' => $ageDays,
                'age_label' => $ageDays > 0 ? "اليوم {$ageDays}" : "تجهيز (" . abs($ageDays - 1) . ")",
                'stats' => [
                    'mortality' => (int) $dayMortalities->sum('quantity'),
                    'feed' => (float) $dayFeed->sum('quantity'),
                    'medicine_count' => $dayMedicines->count(),
                    'completion_rate' => $completionRate,
                ],
                'timeline' => $timeline->sortByDesc('time')->values()->toArray(),
            ];

        }

        // Return empty array if NO DATA at all in the 7 days (as per user request: hide if empty)
        $hasData = collect($days)->contains(fn($d) => !empty($d['timeline']));
        if (!$hasData) return [];

        return $days;
    }
}

<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Actions\Flock\ResolveFlockZeroDateAction;
use App\Models\Flock;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// تحديث عمر الأفواج يومياً عند منتصف الليل — يتجمّد عند اليوم الذي وصل فيه
// العدد المتبقي للصفر بدل الاستمرار في العد بعد بيع/نفوق كل الدجاج.
Schedule::call(function () {
    $resolveZeroDate = new ResolveFlockZeroDateAction();

    $flocks = Flock::whereIn('status', ['active', 'draft'])
        ->withSum('mortalities', 'quantity')
        ->withSum('saleItems', 'birds_count')
        ->get();

    foreach ($flocks as $flock) {
        $remainingCount = $flock->initial_count
            - (int) ($flock->mortalities_sum_quantity ?? 0)
            - (int) ($flock->sale_items_sum_birds_count ?? 0);

        $endDate = Carbon::today();
        if ($remainingCount <= 0) {
            $endDate = $resolveZeroDate->execute($flock) ?? $endDate;
        }

        $age = (int) Carbon::parse($flock->start_date)->startOfDay()
            ->diffInDays($endDate->startOfDay()) + 1;

        $flock->update(['current_age_days' => $age]);
    }
})->dailyAt('00:00');

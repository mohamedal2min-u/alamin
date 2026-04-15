use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Flock;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// تحديث عمر الأفواج يومياً عند منتصف الليل
Schedule::call(function () {
    $flocks = Flock::whereIn('status', ['active', 'draft'])->get();
    foreach ($flocks as $flock) {
        $age = (int) Carbon::parse($flock->start_date)->startOfDay()
            ->diffInDays(Carbon::today()->startOfDay()) + 1;
        
        $flock->update(['current_age_days' => $age]);
    }
})->dailyAt('00:00');

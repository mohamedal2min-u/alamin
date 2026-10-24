<?php

namespace App\Services;

use App\Models\Flock;
use App\Models\FlockMedicine;
use App\Models\FlockVaccination;
use App\Models\VaccineScheduleTemplate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * برنامج اللقاحات: القالب الافتراضي، توليد لقاحات الفوج، وحساب تاريخ «لا بيع قبل».
 */
class VaccinationService
{
    public const EXAMPLE_NAME = 'برنامج لاحم — مثال — عدّله مع الطبيب البيطري';

    // برنامج لاحم شائع كمثال فقط — يجب مراجعته مع الطبيب البيطري
    private const DEFAULT_ITEMS = [
        ['age_day' => 1,  'title' => 'برونشيت (IB) — رش في المفقس', 'method' => 'spray',          'notes' => 'غالباً يُعطى في المفقس — تأكد من شهادة المفقس'],
        ['age_day' => 7,  'title' => 'نيوكاسل + برونشيت',            'method' => 'drinking_water', 'notes' => null],
        ['age_day' => 12, 'title' => 'غامبورو',                      'method' => 'drinking_water', 'notes' => null],
        ['age_day' => 18, 'title' => 'نيوكاسل (جرعة منشطة)',         'method' => 'drinking_water', 'notes' => null],
        ['age_day' => 24, 'title' => 'غامبورو (جرعة منشطة)',         'method' => 'drinking_water', 'notes' => null],
    ];

    /** القالب الفعّال للمزرعة — يُنشأ المثال الافتراضي عند أول وصول إن لم يوجد قالب. */
    public function template(int $farmId): VaccineScheduleTemplate
    {
        $existing = VaccineScheduleTemplate::where('farm_id', $farmId)->where('is_active', true)->orderBy('id')->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($farmId): VaccineScheduleTemplate {
            // قفل المزرعة يمنع إنشاء قالبين عند طلبين متزامنين
            DB::table('farms')->where('id', $farmId)->lockForUpdate()->first();

            $existing = VaccineScheduleTemplate::where('farm_id', $farmId)->orderBy('id')->first();
            if ($existing) {
                if (! $existing->is_active) {
                    $existing->update(['is_active' => true]);
                }
                return $existing;
            }

            $template = VaccineScheduleTemplate::create([
                'farm_id'    => $farmId,
                'name'       => self::EXAMPLE_NAME,
                'is_active'  => true,
                'is_example' => true,
            ]);
            $template->items()->createMany(self::DEFAULT_ITEMS);

            return $template;
        });
    }

    /** تاريخ الاستحقاق: اليوم الأول من العمر = تاريخ بدء الفوج (مثل current_age_days). */
    public function dueDate(Flock $flock, int $ageDay): Carbon
    {
        return Carbon::parse($flock->start_date)->startOfDay()->addDays(max(1, $ageDay) - 1);
    }

    /**
     * يولّد لقاحات الفوج من القالب إن لم يكن له أي لقاح بعد.
     * @return int عدد الصفوف المنشأة
     */
    public function generateIfMissing(Flock $flock): int
    {
        if ($flock->status !== 'active') {
            return 0;
        }
        if (FlockVaccination::where('flock_id', $flock->id)->exists()) {
            return 0;
        }

        $template = $this->template($flock->farm_id);

        return DB::transaction(function () use ($flock, $template): int {
            Flock::whereKey($flock->id)->lockForUpdate()->first();
            if (FlockVaccination::where('flock_id', $flock->id)->exists()) {
                return 0;
            }

            return $this->createRows($flock, $template->items()->get());
        });
    }

    /**
     * «تطبيق على الفوج الحالي»: يحذف الصفوف التي ما زالت مستحقة فقط ويعيد توليدها من القالب.
     * المنفَّذ والمتخطّى يبقى كما هو ولا يُكرَّر.
     */
    public function regenerateDue(Flock $flock): int
    {
        $template = $this->template($flock->farm_id);

        return DB::transaction(function () use ($flock, $template): int {
            Flock::whereKey($flock->id)->lockForUpdate()->first();

            FlockVaccination::where('flock_id', $flock->id)->where('status', 'due')->delete();

            $kept = FlockVaccination::where('flock_id', $flock->id)->get(['age_day', 'title'])
                ->map(fn ($v) => $v->age_day . '|' . $v->title)
                ->flip();

            $items = $template->items()->get()
                ->reject(fn ($i) => $kept->has($i->age_day . '|' . $i->title));

            return $this->createRows($flock, $items);
        });
    }

    /** عند تغيير تاريخ بدء الفوج: يعيد حساب مواعيد اللقاحات المستحقة فقط. */
    public function shiftDueDates(Flock $flock): void
    {
        FlockVaccination::where('flock_id', $flock->id)->where('status', 'due')->get()
            ->each(fn (FlockVaccination $v) => $v->update(['due_date' => $this->dueDate($flock, $v->age_day)->toDateString()]));
    }

    private function createRows(Flock $flock, Collection $items): int
    {
        $now = now();
        $rows = $items->map(fn ($i) => [
            'farm_id'         => $flock->farm_id,
            'flock_id'        => $flock->id,
            'age_day'         => $i->age_day,
            'due_date'        => $this->dueDate($flock, (int) $i->age_day)->toDateString(),
            'title'           => $i->title,
            'method'          => $i->method,
            'notes'           => $i->notes,
            'status'          => 'due',
            'withdrawal_days' => $i->withdrawal_days,
            'created_at'      => $now,
            'updated_at'      => $now,
        ])->values()->all();

        if ($rows) {
            FlockVaccination::insert($rows);
        }

        return count($rows);
    }

    /**
     * تاريخ «لا بيع قبل» = أكبر (تاريخ الإعطاء + أيام السحب) من أدوية الفوج واللقاحات المنفّذة.
     * @return array{no_sale_before: ?string, source: ?string}
     */
    public function withdrawal(Flock $flock): array
    {
        $best = null;
        $source = null;

        $medicines = FlockMedicine::query()
            ->join('items', 'items.id', '=', 'flock_medicines.item_id')
            ->where('flock_medicines.flock_id', $flock->id)
            ->where('items.withdrawal_days', '>', 0)
            ->groupBy('items.id', 'items.name', 'items.withdrawal_days')
            ->selectRaw('items.name as name, items.withdrawal_days as days, MAX(flock_medicines.entry_date) as last_date')
            ->get();

        foreach ($medicines as $m) {
            $date = Carbon::parse($m->last_date)->startOfDay()->addDays((int) $m->days);
            if ($best === null || $date->gt($best)) {
                $best = $date;
                $source = $m->name;
            }
        }

        $vaccines = FlockVaccination::where('flock_id', $flock->id)
            ->where('status', 'done')
            ->whereNotNull('done_at')
            ->where('withdrawal_days', '>', 0)
            ->get(['title', 'done_at', 'withdrawal_days']);

        foreach ($vaccines as $v) {
            $date = $v->done_at->copy()->startOfDay()->addDays((int) $v->withdrawal_days);
            if ($best === null || $date->gt($best)) {
                $best = $date;
                $source = $v->title;
            }
        }

        return ['no_sale_before' => $best?->toDateString(), 'source' => $source];
    }
}

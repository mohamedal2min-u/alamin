<?php

namespace App\Console\Commands;

use App\Models\Farm;
use App\Models\Flock;
use App\Models\FlockVaccination;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\EditNotifier;
use App\Services\PushNotificationService;
use App\Services\VaccinationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * تذكير يومي (07:30 بتوقيت دمشق): إشعار واحد لكل عضو فعّال في مزرعة لديها لقاح مستحق اليوم للفوج النشط.
 * يستخدم قناة manager_edit الموجودة حتى لا نحتاج قناة أندرويد جديدة.
 */
class RemindVaccinations extends Command
{
    protected $signature = 'vaccines:remind {--date= : تاريخ بصيغة Y-m-d بدل اليوم}';

    protected $description = 'إرسال تذكير بلقاحات الفوج النشط المستحقة اليوم لأعضاء المزرعة';

    public function handle(VaccinationService $service, PushNotificationService $push): int
    {
        $today = $this->option('date') ?: Carbon::now('Asia/Damascus')->toDateString();

        $flocks = Flock::where('status', 'active')
            ->whereHas('farm', fn ($q) => $q->where('status', 'active'))
            ->get();

        $sent = 0;
        foreach ($flocks as $flock) {
            // الفوج الذي لم يُفتح برنامجه بعد يُولَّد له الآن
            $service->generateIfMissing($flock);

            $due = FlockVaccination::where('flock_id', $flock->id)
                ->where('status', 'due')
                ->whereDate('due_date', $today)
                ->orderBy('id')
                ->pluck('title');

            if ($due->isEmpty()) {
                continue;
            }

            $farm = Farm::find($flock->farm_id);
            $recipients = $farm->members()
                ->wherePivot('status', 'active')
                ->where('users.status', 'active')
                ->get();

            if ($recipients->isEmpty()) {
                continue;
            }

            $title = 'لقاح اليوم — ' . $flock->name;
            $body = mb_substr($due->implode('، '), 0, 280);

            foreach ($recipients as $r) {
                /** @var User $r */
                UserNotification::create([
                    'farm_id' => $farm->id, 'user_id' => $r->id, 'actor_id' => null,
                    'kind' => EditNotifier::MANAGER, 'title' => $title, 'body' => $body,
                ]);
            }

            $push->sendNotification($recipients, $title, $body, ['type' => 'vaccine', 'flock_id' => $flock->id], EditNotifier::MANAGER, EditNotifier::MANAGER);
            $sent += $recipients->count();
        }

        $this->info("أُرسل التذكير إلى {$sent} مستخدم.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\User;
use App\Models\UserNotification;

/**
 * إشعار بالتعديلات مع نغمة مميّزة:
 *  - المحاسب عدّل  → يُبلَّغ مدير المزرعة والمشرف (نغمة المحاسب)
 *  - المدير/المشرف عدّل → يُبلَّغ المحاسب (نغمة المدير)
 * يُسجَّل داخل التطبيق ويُرسَل دفعاً (FCM) بقناة/صوت خاصّين لكل نوع.
 */
class EditNotifier
{
    public const ACCOUNTANT = 'accountant_edit';
    public const MANAGER = 'manager_edit';

    public function __construct(private readonly PushNotificationService $push) {}

    public const DAILY_ENTRY = 'daily_entry';

    /**
     * @param  array{rows?: array, flock?: ?string, link?: ?string}|null  $details  تفاصيل التعديل (EditDetails)
     */
    public function notify(int $farmId, User $actor, string $what, ?string $entryKind = null, ?array $details = null): void
    {
        $isAccountant = $actor->hasRole('accountant') && ! $actor->hasRole('farm_admin');
        $kind = $isAccountant ? self::ACCOUNTANT : self::MANAGER;
        $targetRoles = $isAccountant ? ['farm_admin', 'supervisor'] : ['accountant'];
        if ($entryKind === self::DAILY_ENTRY) {
            // تصحيح إدخال يومي (تعديل/إلغاء): يُبلَّغ كل الأعضاء الآخرين بالنغمة نفسها
            $targetRoles = ['farm_admin', 'supervisor', 'accountant', 'partner'];
        }

        $farm = Farm::find($farmId);
        if (! $farm) {
            return;
        }

        $recipients = $farm->members()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $targetRoles)->where('model_has_roles.farm_id', $farmId))
            ->get()
            ->reject(fn (User $u) => $u->id === $actor->id)
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        $who = $isAccountant ? 'المحاسب' : 'المدير';
        $title = "تعديل من {$who}";
        $flock = $details['flock'] ?? null;
        // أول تغييرين يظهران في نص الإشعار نفسه، والباقي عند الضغط عليه
        $summary = collect($details['rows'] ?? [])->take(2)
            ->map(fn (array $r) => $r['label'].': '.(isset($r['from']) ? "{$r['from']} ← " : '').$r['to'])
            ->implode('، ');
        $body = mb_substr("{$actor->name}: {$what}".($flock ? " ({$flock})" : '').($summary !== '' ? "\n{$summary}" : ''), 0, 300);

        // كل مستلم له إشعاره المحفوظ؛ الضغط على الإشعار يفتح تفاصيله (notification_id)
        foreach ($recipients as $r) {
            $n = UserNotification::create([
                'farm_id' => $farmId, 'user_id' => $r->id, 'actor_id' => $actor->id,
                'kind' => $kind, 'title' => $title, 'body' => $body,
                'details' => $details ? ['what' => $what] + $details : ['what' => $what],
                'link' => $details['link'] ?? null,
            ]);

            $data = ['type' => $kind, 'notification_id' => $n->id] + ($entryKind ? ['entry' => $entryKind] : []);
            $this->push->sendNotification($r, $title, $body, $data, $kind, $kind);
        }
    }
}

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

    public function notify(int $farmId, User $actor, string $what, ?string $entryKind = null): void
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
        $body = "{$actor->name}: {$what}";

        foreach ($recipients as $r) {
            UserNotification::create([
                'farm_id' => $farmId, 'user_id' => $r->id, 'actor_id' => $actor->id,
                'kind' => $kind, 'title' => $title, 'body' => $body,
            ]);
        }

        $data = ['type' => $kind] + ($entryKind ? ['entry' => $entryKind] : []);
        $this->push->sendNotification($recipients, $title, $body, $data, $kind, $kind);
    }
}

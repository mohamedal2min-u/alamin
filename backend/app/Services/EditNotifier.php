<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\User;
use App\Models\UserNotification;

/**
 * إشعار بالتعديلات لكل أعضاء المزرعة (عدا من عدّل) مع نغمة حسب نوع التعديل:
 *  - السجلات اليومية (نفوق، علف، دواء، ماء، ملاحظة، حرارة) → نغمة بسيطة قصيرة
 *  - المحاسب عدّل (سعر، دفعة، حركة شريك) → نغمة المحاسب
 *  - المدير/المشرف عدّل (مصروف، بيع، مخزون، فوج) → نغمة المدير
 * يُسجَّل داخل التطبيق ويُرسَل دفعاً (FCM) بقناة/صوت خاصّين لكل نوع.
 */
class EditNotifier
{
    public const ACCOUNTANT = 'accountant_edit';
    public const MANAGER = 'manager_edit';
    public const DAILY = 'daily_entry';
    public const DAILY_ENTRY = self::DAILY;

    public function __construct(private readonly PushNotificationService $push) {}

    public function notify(int $farmId, User $actor, string $what, ?string $kind = null): void
    {
        $entry = $kind;
        $isAccountant = $actor->hasRole('accountant') && ! $actor->hasRole('farm_admin');
        $kind = $kind === self::DAILY ? self::DAILY : ($isAccountant ? self::ACCOUNTANT : self::MANAGER);

        $farm = Farm::find($farmId);
        if (! $farm) {
            return;
        }

        $recipients = $farm->members()
            ->wherePivot('status', 'active')
            ->where('users.id', '!=', $actor->id)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $who = $isAccountant ? 'المحاسب' : 'المدير';
        $title = $kind === self::DAILY ? 'تسجيل يومي' : "تعديل من {$who}";
        $body = "{$actor->name}: {$what}";

        foreach ($recipients as $r) {
            UserNotification::create([
                'farm_id' => $farmId, 'user_id' => $r->id, 'actor_id' => $actor->id,
                'kind' => $kind, 'title' => $title, 'body' => $body,
            ]);
        }

        $data = ['type' => $kind] + ($entry ? ['entry' => $entry] : []);
        $this->push->sendNotification($recipients, $title, $body, $data, $kind, $kind);
    }
}

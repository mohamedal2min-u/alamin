<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EditNotifier;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;

/**
 * فحص إشعارات الهاتف: ملف حساب Firebase، ومن عنده رمز جهاز، وإرسال إشعار تجريبي لمستخدم.
 *   php artisan push:check          ← الحالة فقط
 *   php artisan push:check 5        ← + إشعار تجريبي للمستخدم رقم 5 (أو اسم الدخول)
 */
class PushCheck extends Command
{
    protected $signature = 'push:check {user? : رقم المستخدم أو بريده أو اسمه لإرسال إشعار تجريبي}';

    protected $description = 'فحص إعداد إشعارات الهاتف (FCM) وإرسال إشعار تجريبي';

    public function handle(PushNotificationService $push): int
    {
        $path = $push->serviceAccountPath();
        $ok = is_readable($path);
        $this->line('ملف Firebase: '.($ok ? 'موجود ✓' : "غير موجود ✗ ({$path})"));
        $this->line('مشروع Firebase: '.$push->projectId());

        // أرقام فقط بلا أسماء: المخرجات تظهر في سجل GitHub Actions
        $ids = User::whereNotNull('fcm_token')->orderBy('id')->pluck('id');
        $this->line("أجهزة مسجّلة: {$ids->count()} (المستخدمون: ".($ids->implode(', ') ?: '—').')');

        $needle = $this->argument('user');
        if ($needle === null) {
            return $ok ? self::SUCCESS : self::FAILURE;
        }

        $user = is_numeric($needle)
            ? User::find((int) $needle)
            : User::where('email', $needle)->orWhere('name', $needle)->first();
        if (! $user) {
            $this->error("لا يوجد مستخدم: {$needle}");

            return self::FAILURE;
        }

        $res = $push->sendToUser($user, 'إشعار تجريبي', 'إذا وصلك هذا والهاتف مقفل فالإشعارات تعمل ✓', ['type' => 'test'], EditNotifier::MANAGER, EditNotifier::MANAGER);
        if ($res['ok']) {
            $this->info("أُرسل إلى المستخدم #{$user->id} ✓");

            return self::SUCCESS;
        }
        $this->error("فشل الإرسال إلى المستخدم #{$user->id}: {$res['error']}");

        return self::FAILURE;
    }
}
